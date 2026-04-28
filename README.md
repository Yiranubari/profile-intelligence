# Insighta Labs+ Backend

This is the backend for Insighta Labs+, the secure platform built on top of the Profile Intelligence System from Stage 2. It handles authentication, role based access control, profile management, and serves both the CLI tool and the web portal from a single source of truth.

Live URL: https://profile-intelligence-production.up.railway.app

## What this backend does

It does five things:

1. Authenticates users through GitHub OAuth, with PKCE support for the CLI.
2. Issues short lived access tokens and rotating refresh tokens.
3. Enforces role based permissions on every protected endpoint.
4. Serves the same API to both the CLI and the web portal, with different auth styles for each (Bearer for CLI, HTTP only cookies for web).
5. Keeps every Stage 2 feature working untouched: filtering, sorting, pagination, natural language search, and CSV export.

## Tech stack

- PHP 8.2 with Slim 4 for routing and middleware
- SQLite for storage, accessed through PDO
- PHP DI for the dependency container
- Monolog for request and error logging
- Firebase JWT for signing and verifying access tokens
- Guzzle for outbound HTTP calls to GitHub
- Respect Validation for input checks
- Symfony UID for UUID v7 generation
- Hosted on Railway with a custom Docker image (PHP FPM behind nginx)

## System architecture

The codebase follows a strict layered architecture. Each layer depends only on the layer below it and never reaches around it.

```
HTTP entry (public/index.php)
  v
Routes (src/Routes)
  v
Middleware pipeline (src/Middleware)
  v
Controllers (src/Controllers)
  v
Services (src/Services)
  v
Repositories (src/Repositories)
  v
Database (database/Database.php)
```

Controllers handle HTTP concerns only. They read query params, parsed bodies, and cookies, then call services. They never contain business logic. Services hold business logic and orchestrate repositories. Repositories are the only place SQL lives. This separation is what made it possible to add the entire authentication system in Stage 3 without rewriting any Stage 2 code.

## Folder structure

```
profile-intelligence/
  config/
    app.php
    dependencies.php
  database/
    Database.php
    seed.php
    profiles.db
  docker/
    nginx.conf
    start.sh
  public/
    index.php
  src/
    Controllers/
    Exceptions/
    Helpers/
    Middleware/
    Parsers/
    Repositories/
    Routes/
    Services/
    Validators/
  tests/
  .github/workflows/ci.yml
  composer.json
  Dockerfile
  README.md
```

## Authentication flow

The backend supports two OAuth flows from the same set of endpoints. The flow type is decided by a `client_type` query parameter on the initial authorize call.

### Web flow

Used by the browser based portal.

1. The portal links the user to `GET /auth/github?client_type=web`.
2. The backend generates a random `state`, stores an `auth_sessions` row, and redirects the browser to GitHub.
3. The user authorizes on GitHub. GitHub redirects to `GET /auth/github/callback?code=X&state=Y`.
4. The backend looks up the session by state, exchanges the GitHub code for a GitHub access token, and fetches the user's profile and email from the GitHub API.
5. The backend creates the user if they do not exist, or updates their stored details if they do.
6. The backend issues an access token (JWT) and a refresh token (opaque random string), then sets three cookies on the browser:
   - `access_token`: HTTP only, Secure, SameSite Lax, 3 minute lifetime
   - `refresh_token`: HTTP only, Secure, SameSite Lax, 5 minute lifetime
   - `csrf_token`: NOT HTTP only, readable by JavaScript, used for double submit CSRF protection
7. The backend redirects the browser to the portal's dashboard.

### CLI flow with PKCE

Used by the command line tool. PKCE is required because the CLI cannot keep a client secret.

1. The CLI generates a `state`, a `code_verifier` (32 random bytes), and a `code_challenge` (SHA 256 of the verifier, base64 url encoded).
2. The CLI starts a temporary local server on a free port, then opens the browser to `GET /auth/github?client_type=cli&code_challenge=X&cli_port=PORT&state=Y`.
3. The backend stores the challenge and port in the `auth_sessions` row and redirects to GitHub.
4. After the user authorizes, GitHub redirects back to the backend's callback. The backend exchanges the code, finds or creates the user, then generates a one time `auth_code`.
5. The backend redirects the browser to `http://localhost:PORT/callback?auth_code=Z&state=Y`. The CLI's local server captures it.
6. The CLI sends `POST /auth/cli/exchange` with `{auth_code, code_verifier}`.
7. The backend looks up the session by `auth_code`, hashes the verifier, and compares it to the stored challenge using a timing safe comparison. If it matches, the session is consumed and tokens are returned in JSON.
8. The CLI saves the tokens to `~/.insighta/credentials.json`.

## Token handling

### Access tokens

Access tokens are JWTs signed with HS256. The payload contains the user id, role, username, issued at, and expires at. They live for 3 minutes. Validation does not touch the database, which keeps protected endpoints fast.

### Refresh tokens

Refresh tokens are opaque 32 byte hex strings. They carry no data. The backend stores only the SHA 256 hash of each refresh token in the `refresh_tokens` table, never the raw token. If the database is ever leaked, an attacker would have hashes that cannot be used as tokens.

Refresh tokens live for 5 minutes and rotate on every use:

1. The client calls `POST /auth/refresh` with the raw token in the body or as a cookie.
2. The backend hashes the token and looks it up.
3. If the row exists, is not expired, and is not revoked, the backend immediately revokes it.
4. The backend issues a brand new access token and refresh token pair.
5. The new pair is returned to the client.

If the same refresh token is ever used twice, the second attempt finds a revoked row and fails. This is the rotation safeguard against token theft.

### Logout

`POST /auth/logout` reads the refresh token from the cookie or body, hashes it, and sets `revoked = 1`. The endpoint is idempotent: logging out an already revoked token is silently fine. For web clients, the response also clears all three cookies by setting them with `Max-Age=0`.

## Role enforcement

Two roles exist:

- `analyst`: read access only. Can list, view, search, and export profiles. Can view their own user info. This is the default for new users.
- `admin`: everything analysts can do, plus create and delete profiles, list all users, and change other users' roles.

Enforcement happens in two layers. Every `/api/*` route requires authentication via `AuthMiddleware`, which validates the access token, loads the user, checks `is_active`, and attaches the user to the request. Specific routes that need admin access then add `RoleMiddleware` configured with the required role.

This means there are no scattered role checks inside controllers. The middleware decides at the gate. Adding a new admin only route is a single line:

```php
$group->post('/profiles', [ProfileController::class, 'create'])
      ->add('role.admin');
```

The `is_active` flag on users is checked on every authenticated request. If it is set to 0, the user receives a 403 even if their token is valid. This lets an admin disable an account immediately without waiting for tokens to expire.

### Bootstrap admin

The first user to sign up while the `users` table is empty automatically receives the `admin` role. Every user after that defaults to `analyst`. Admins can promote or demote other users through `PATCH /api/users/:id/role` or through the web portal's user management page. An admin cannot demote themselves, which prevents an admin from accidentally locking the system out of all admin access.

## Natural language parsing

The Stage 2 search endpoint accepts a free text query like `"young males from nigeria"` and translates it into the same structured filters used by the regular list endpoint. The parser lives in `src/Parsers/QueryParser.php` and is rule based, not AI.

It works in three passes:

1. **Gender detection.** Looks for the words "male" or "female" with regex word boundaries to avoid `male` matching inside `female`. If both appear, the search prefers female (since "female" is the more specific term).
2. **Age cues.** Checks for explicit age groups (`child`, `teenager`, `adult`, `senior`) and maps them to the `age_group` filter. Then checks for the keyword `young` and maps it to `min_age=16, max_age=24`. Finally, regex matches phrases like `above 30`, `over 40`, `below 50`, `under 25`, `older than X`, `younger than X` and maps them to `min_age` or `max_age`.
3. **Country detection.** Iterates through a hardcoded map of country name (lowercased) to ISO code. The first match wins. The map covers about 50 countries including all major African nations represented in the seed data.

If the parser cannot extract any filters from the query, it returns a special marker that the controller turns into a 400 error: "Unable to interpret query".

## API endpoints

### Auth

- `GET /auth/github` redirects to GitHub for authorization
- `GET /auth/github/callback` handles the GitHub redirect and issues tokens or auth codes
- `POST /auth/cli/exchange` exchanges a CLI auth code plus PKCE verifier for tokens
- `POST /auth/refresh` rotates the access and refresh tokens
- `POST /auth/logout` revokes the refresh token and clears cookies
- `GET /auth/me` returns the current user (requires authentication)

### Profiles

All endpoints require `X-API-Version: 1` header.

- `GET /api/profiles` lists profiles with filters, sorting, and pagination
- `GET /api/profiles/search?q=...` natural language search
- `GET /api/profiles/export?format=csv` exports filtered results as CSV
- `GET /api/profiles/{id}` retrieves a single profile
- `POST /api/profiles` creates a profile (admin only)
- `DELETE /api/profiles/{id}` deletes a profile (admin only)

### Users

- `GET /api/users` lists all users (admin only)
- `PATCH /api/users/{id}/role` changes a user's role (admin only)

## Pagination response shape

Both `/api/profiles` and `/api/profiles/search` return:

```json
{
  "status": "success",
  "page": 1,
  "limit": 10,
  "total": 2026,
  "total_pages": 203,
  "links": {
    "self": "/api/profiles?page=1&limit=10",
    "next": "/api/profiles?page=2&limit=10",
    "prev": null
  },
  "data": [ ... ]
}
```

`prev` is null on page 1. `next` is null on the last page. Filters applied to the request are preserved in all three links.

## Standard error shape

Every error response, regardless of status code, follows the same shape:

```json
{
  "status": "error",
  "message": "Human readable explanation"
}
```

This is enforced centrally by `ErrorHandlerMiddleware`, which catches every exception thrown anywhere in the request lifecycle and translates it into this format.

## Rate limiting

- Auth endpoints (`/auth/*`): 10 requests per minute, identified by client IP
- API endpoints (`/api/*`): 60 requests per minute, identified by user id (falls back to IP if no user is attached)

The implementation uses a fixed window counter stored in the `rate_limits` SQLite table. Each request UPSERTs into the table, incrementing the count for the current minute bucket. If the post increment count exceeds the limit, the response is 429 with `Retry-After: 60`. Every response also carries `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers so well behaved clients can pace themselves.

The middleware reads `X-Forwarded-For` for the real client IP, since Railway runs the app behind a reverse proxy.

## Request logging

`LoggerMiddleware` records the start time on every incoming request, calls the handler, then writes a structured log entry with method, path, status code, and duration in milliseconds. Logs go to `logs/app.log` via Monolog. Errors caught by `ErrorHandlerMiddleware` are logged with full stack traces at error level, while normal requests log at info level.

Request bodies, query strings, headers, and cookies are intentionally not logged. They might contain credentials, and logging them would create a secondary leak risk.

## Security posture

- HTTPS enforced in production via Railway's edge
- HTTP only and Secure flags on auth cookies prevent XSS theft
- Double submit CSRF protection for cookie authenticated state changing requests
- Bearer authenticated requests bypass CSRF (no cookie attack surface)
- PKCE on the CLI flow protects the auth code from interception
- Refresh tokens stored as SHA 256 hashes
- Rotation on every refresh detects token theft
- Timing safe string comparisons (`hash_equals`) for all secret comparisons
- Prepared statements everywhere, no string concatenated SQL
- Role checks enforced as middleware, not scattered through controllers
- Generic 500 messages on uncaught exceptions, full traces in logs only

## Local development

You will need PHP 8.2+, Composer, and SQLite installed.

Clone the repo:

```
git clone https://github.com/Yiranubari/profile-intelligence.git
cd profile-intelligence
```

Install dependencies:

```
composer install
```

Copy the example environment file and fill in your own values:

```
cp .env.example .env
```

You will need to register a GitHub OAuth App at https://github.com/settings/developers and put the client id, client secret, and redirect URI into your `.env`. For local development, you should set the redirect URI to `http://localhost:8000/auth/github/callback` and register that same URL as the OAuth app's authorization callback URL on GitHub.

Generate a JWT signing secret:

```
openssl rand -hex 32
```

Paste it into `.env` as `JWT_SECRET`.

Seed the database:

```
php database/seed.php
```

This creates the schema and loads 2026 sample profiles. The script is idempotent: running it twice will not duplicate data.

Start the development server:

```
php -S 0.0.0.0:8000 -t public public/index.php
```

The server will be available at `http://localhost:8000`.

## Environment variables

| Name                   | Purpose                                                     |
| ---------------------- | ----------------------------------------------------------- |
| `DB_PATH`              | Path to the SQLite file. Defaults to `database/profiles.db` |
| `GITHUB_CLIENT_ID`     | OAuth App client ID from GitHub                             |
| `GITHUB_CLIENT_SECRET` | OAuth App client secret                                     |
| `GITHUB_REDIRECT_URI`  | Where GitHub should redirect after authorization            |
| `JWT_SECRET`           | 64 character hex string for signing access tokens           |
| `ACCESS_TOKEN_TTL`     | Access token lifetime in seconds. Defaults to 180           |
| `REFRESH_TOKEN_TTL`    | Refresh token lifetime in seconds. Defaults to 300          |
| `WEB_PORTAL_URL`       | Where to redirect the browser after web login               |
| `COOKIE_SECURE`        | Set to `true` in production (HTTPS), `false` for local dev  |

## Deployment

The backend is deployed on Railway from the `main` branch. The `Dockerfile` builds a PHP FPM container with nginx in front. The `docker/start.sh` script runs the seed, fixes file ownership for the FPM user, and starts both services.

Railway injects environment variables directly into the container. Each merged pull request to `main` triggers an automatic redeploy.

## Continuous integration

`.github/workflows/ci.yml` runs on every pull request to `main` and every push to `main`. It installs PHP 8.2, validates `composer.json`, installs dependencies (with caching), and runs `php -l` on every file in `src/`, `public/`, `config/`, and `database/`. Branch protection on `main` blocks merging if the workflow fails.

## Engineering practices

- Every change went through a feature branch and pull request, never a direct commit to `main`
- Commits use conventional commits with a scope, for example `feat(auth): add github oauth pkce flow`
- Branches use the prefix system `feat/`, `fix/`, `chore/`, `refactor/`, `docs/`, `ci/`
- The CI pipeline runs on every PR and must pass before merging
- The whole feature was built incrementally: schema, then helpers, then repositories, then services, then controllers, then routes. Each step was tested in isolation before moving on

## Companion repositories

This is one of three repos that make up the Insighta Labs+ system:

- **Backend** (this repo): https://github.com/Yiranubari/profile-intelligence
- **CLI**: https://github.com/Yiranubari/insighta-cli
- **Web portal**: https://github.com/Yiranubari/insighta-web

The CLI and the portal both consume this backend's API. They authenticate differently (Bearer tokens for the CLI, HTTP only cookies for the portal), but they read and write the exact same data through the exact same endpoints.
