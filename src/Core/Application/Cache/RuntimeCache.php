<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Cache;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class RuntimeCache
{
    private const CACHE_GROUP = 'shadow_orm';
    private const TTL = 3600; // 1 hour

    /** @var array<int, ShadowEntity> In-memory cache for current request */
    private static array $cache = [];

    /** @var array<int, bool> Posts marked as not found in shadow tables */
    private static array $notFound = [];

    /** @var bool Whether persistent cache (Redis/Memcached) is available */
    private static ?bool $persistentCacheAvailable = null;

    public function get(int $postId): ?ShadowEntity
    {
        // Try in-memory first (fastest)
        if (isset(self::$cache[$postId])) {
            return self::$cache[$postId];
        }

        // Try persistent cache if available
        if (self::hasPersistentCache()) {
            $cached = wp_cache_get($postId, self::CACHE_GROUP);
            if ($cached instanceof ShadowEntity) {
                self::$cache[$postId] = $cached;
                return $cached;
            }
        }

        return null;
    }

    public function set(int $postId, ShadowEntity $entity): void
    {
        self::$cache[$postId] = $entity;
        unset(self::$notFound[$postId]);

        // Persist to object cache if available
        if (self::hasPersistentCache()) {
            wp_cache_set($postId, $entity, self::CACHE_GROUP, self::TTL);
        }
    }

    public function has(int $postId): bool
    {
        if (isset(self::$cache[$postId])) {
            return true;
        }

        if (self::hasPersistentCache()) {
            $cached = wp_cache_get($postId, self::CACHE_GROUP);
            if ($cached instanceof ShadowEntity) {
                self::$cache[$postId] = $cached;
                return true;
            }
        }

        return false;
    }

    public function delete(int $postId): void
    {
        unset(self::$cache[$postId]);
        unset(self::$notFound[$postId]);

        if (self::hasPersistentCache()) {
            wp_cache_delete($postId, self::CACHE_GROUP);
        }
    }

    public function markNotFound(int $postId): void
    {
        self::$notFound[$postId] = true;

        // Cache the "not found" state too
        if (self::hasPersistentCache()) {
            wp_cache_set('nf_' . $postId, true, self::CACHE_GROUP, self::TTL);
        }
    }

    public function isMarkedNotFound(int $postId): bool
    {
        if (isset(self::$notFound[$postId])) {
            return true;
        }

        if (self::hasPersistentCache()) {
            $notFound = wp_cache_get('nf_' . $postId, self::CACHE_GROUP);
            if ($notFound === true) {
                self::$notFound[$postId] = true;
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        self::$cache = [];
        self::$notFound = [];

        // Note: We don't flush the persistent cache group here
        // as it may affect other requests
    }

    public function flush(): void
    {
        self::clear();

        if (self::hasPersistentCache() && function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }

    /**
     * @param array<ShadowEntity> $entities
     */
    public function warmup(array $entities): void
    {
        foreach ($entities as $entity) {
            self::$cache[$entity->postId] = $entity;

            if (self::hasPersistentCache()) {
                wp_cache_set($entity->postId, $entity, self::CACHE_GROUP, self::TTL);
            }
        }
    }

    public function getStats(): array
    {
        return [
            'cached' => count(self::$cache),
            'not_found' => count(self::$notFound),
            'memory' => memory_get_usage(true),
            'persistent_cache' => self::hasPersistentCache(),
        ];
    }

    private static function hasPersistentCache(): bool
    {
        if (self::$persistentCacheAvailable === null) {
            self::$persistentCacheAvailable = wp_using_ext_object_cache();
        }

        return self::$persistentCacheAvailable;
    }
}
