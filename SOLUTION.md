# SOLUTION.md

This explains what I changed for Stage 4B and why.

## What I did

### 1. Faster queries

I sped up reads in three ways.

**Indexes.** I added 8 indexes on the `profiles` table for the columns I filter and sort by most: `gender`, `country_id`, `age_group`, `age`, `gender_probability`, `country_probability`, `created_at`, plus a combined `gender + country_id` index. Without indexes, SQLite scans every row. With them, it jumps straight to the right ones.

**Redis cache.** Every list and search query checks Redis first. If the result is there, return it. If not, query SQLite, save the result in Redis with a 60-second expiry, then return it. Around 40% of queries repeat, so most users get the fast path.

**Persistent connections and SQLite tuning.** I told PHP to keep the database connection open between requests, and turned on a few SQLite settings: WAL mode, lighter syncs, a bigger memory cache, and in-memory temp storage. WAL mode is the key one because it lets reads run while writes are happening.

### 2. Query normalization

Two queries that mean the same thing should hit the same cache entry. So I built a `QueryNormalizer` that cleans up the filter object before I build the cache key. It:

1. Lowercases and trims string fields like `gender` and `country_id`.
2. Casts numbers so `"25"` and `25` are the same.
3. Drops empty and null values.
4. Drops default values like `page=1` so they do not change the key.
5. Sorts the keys alphabetically.

The cleaned object is hashed with `md5(json_encode(...))` to make the cache key. Two queries that mean the same thing now always produce the same key.

### 3. CSV upload

I added `POST /api/profiles/upload`, admin only. It streams the file in chunks of 1,000 rows, validates each row, and bulk inserts the valid ones in a single SQL statement per chunk. It returns a summary at the end:

```json
{
  "status": "success",
  "total_rows": 50000,
  "inserted": 48231,
  "skipped": 1769,
  "reasons": {
    "duplicate_name": 1203,
    "invalid_age": 312,
    "missing_fields": 254
  }
}
```

I use `INSERT ... ON CONFLICT(name) DO NOTHING` for the bulk insert. SQLite tells me how many rows actually inserted, so I can count duplicates without an extra query.

## Decisions and trade-offs

**Redis instead of in-process cache.** Redis survives restarts and works across workers. One extra service, but Railway runs it for free.

**Cache invalidation by prefix.** When I insert, update, or delete a profile, I clear all `profiles:*` keys. Broader than needed but simple and predictable. The alternative would be more complex for little gain.

**No background queue for uploads.** The upload runs in the same worker process that handles the request. The TRD warned against extra infrastructure. WAL mode keeps reads fast during uploads, so a queue would be overengineering.

**Stay on SQLite.** The TRD said no new database systems. Indexes, persistent connections, and the PRAGMA tuning take SQLite further than most people expect.

**No rollback on partial failures.** Each chunk commits on its own. If the upload dies halfway, what was inserted stays inserted. This matches the TRD spec exactly.

## Before/after performance

All times are server-side, taken from the `X-Server-Time` response header. Tested against the production deployment, query: `GET /api/profiles?limit=5`.

| Scenario                                   | Time  |
| ------------------------------------------ | ----- |
| Cache miss (first run)                     | 39 ms |
| Cache hit (same query)                     | 10 ms |
| Cache hit (different wording, same intent) | 11 ms |
| 50,000-row CSV upload                      | 5.6 s |
| List query during ongoing upload           | 10 ms |

About 4x faster on repeat queries. With ~40% of queries repeating, the average user is on the fast path most of the time.

## How I handle ingestion edge cases

| Case                                             | What happens                                                   |
| ------------------------------------------------ | -------------------------------------------------------------- |
| Wrong column count                               | Counted as `malformed_row`, skipped                            |
| Empty `name` or `country_id`                     | `missing_fields`, skipped                                      |
| Age missing, non-numeric, negative, or above 150 | `invalid_age`, skipped                                         |
| Gender not "male" or "female"                    | `invalid_gender`, skipped                                      |
| Duplicate name                                   | `ON CONFLICT DO NOTHING` skips it, counted as `duplicate_name` |
| Bad CSV header                                   | 400 error, no rows inserted                                    |
| Huge file                                        | Streamed in 1,000-row chunks, memory stays flat                |
| Upload fails midway                              | Already-inserted rows stay, no rollback                        |
| Two uploads at once                              | Each uses its own worker and connection, both run fine         |
| Reads during upload                              | Stay fast thanks to WAL mode                                   |

I tested concurrency directly. While a 50,000-row upload was running, I ran list queries in parallel. Read latency stayed flat the whole time.
