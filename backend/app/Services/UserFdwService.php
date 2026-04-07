<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserFdwService
{
    private const CACHE_PREFIX = 'fdw_user:';

    public function findById(string $id): ?object
    {
        $cacheKey = self::CACHE_PREFIX . $id;

        return Cache::remember($cacheKey, $this->getTtl(), function () use ($id) {
            return DB::table('users')->where('id', $id)->first();
        });
    }

    public function checkConnectivity(): array
    {
        $start = microtime(true);

        try {
            DB::selectOne('SELECT 1 FROM users LIMIT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latencyMs];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency_ms' => null];
        }
    }

    public function invalidateCache(string $id): void
    {
        Cache::forget(self::CACHE_PREFIX . $id);
    }

    private function getTtl(): int
    {
        return (int) config('database.fdw.users.cache_ttl', 900);
    }
}
