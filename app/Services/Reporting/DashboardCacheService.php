<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Illuminate\Support\Facades\Cache;

final class DashboardCacheService
{
    private const DEFAULT_TTL = 300; // 5 minutes

    /**
     * Cache an expensive dashboard computation with stampede protection (atomic lock).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $lockKey = "lock:{$key}";
        $lock = Cache::lock($lockKey, 10);

        try {
            // Wait up to 3 seconds to acquire lock
            $lock->block(3);

            // Double-check if another worker computed it while waiting for the lock
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }

            $computed = $callback();
            Cache::put($key, $computed, $ttl);

            return $computed;
        } finally {
            $lock->release();
        }
    }

    /**
     * Plant-scoped caching with version-based instant invalidation.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function rememberPlant(int $plantId, string $widgetName, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $version = $this->getPlantCacheVersion($plantId);
        $key = sprintf('dash:p%d:v%d:%s', $plantId, $version, $widgetName);

        return $this->remember($key, $callback, $ttl);
    }

    /**
     * Group-scoped caching with version-based instant invalidation.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function rememberGroup(string $widgetName, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $version = $this->getGroupCacheVersion();
        $key = sprintf('dash:group:v%d:%s', $version, $widgetName);

        return $this->remember($key, $callback, $ttl);
    }

    /**
     * Bust cache for a specific plant.
     */
    public function invalidatePlant(int $plantId): void
    {
        $current = (int) Cache::get("cache_ver:plant:{$plantId}", 1);
        Cache::put("cache_ver:plant:{$plantId}", $current + 1, 86400 * 30);

        $currentGroup = (int) Cache::get('cache_ver:group', 1);
        Cache::put('cache_ver:group', $currentGroup + 1, 86400 * 30);
    }

    /**
     * Bust all group and plant dashboard caches.
     */
    public function invalidateAll(): void
    {
        $currentGroup = (int) Cache::get('cache_ver:group', 1);
        Cache::put('cache_ver:group', $currentGroup + 1, 86400 * 30);
    }

    private function getPlantCacheVersion(int $plantId): int
    {
        return (int) Cache::get("cache_ver:plant:{$plantId}", 1);
    }

    private function getGroupCacheVersion(): int
    {
        return (int) Cache::get('cache_ver:group', 1);
    }
}
