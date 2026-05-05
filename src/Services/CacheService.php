<?php


namespace App\Services;

use Predis\Client;
use Throwable;

class CacheService
{
    public function __construct(
        private Client $redis,
        private int $defaultTtl = 60
    ) {}

    public function get(string $key): ?array
    {
        try {
            $result = $this->redis->get($key);
            if ($result === null) {
                return null;
            }

            $decoded = json_decode($result, true);
            if ($decoded === null) {
                return null;
            }

            return $decoded;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function set(string $key, array $value, ?int $ttl = null): void
    {
        try {
            $value = json_encode($value);
            if ($value === false) {
                return;
            }
            $ttl = $ttl ?? $this->defaultTtl;
            $this->redis->setex($key, $ttl, $value);
        } catch (Throwable $e) {
        }
    }

    public function invalidate(string $prefix): void
    {
        try {
            $keys = $this->redis->keys($prefix . ':*');
            if (empty($keys)) {
                return;
            }
            $this->redis->del($keys);
        } catch (Throwable $e) {
        }
    }
}
