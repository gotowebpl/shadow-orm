<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Cache;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class RuntimeCache
{
    /** @var array<int, ShadowEntity> */
    private static array $cache = [];

    /** @var array<int, bool> */
    private static array $notFound = [];

    public function get(int $postId): ?ShadowEntity
    {
        return self::$cache[$postId] ?? null;
    }

    public function set(int $postId, ShadowEntity $entity): void
    {
        self::$cache[$postId] = $entity;
        unset(self::$notFound[$postId]);
    }

    public function has(int $postId): bool
    {
        return isset(self::$cache[$postId]);
    }

    public function delete(int $postId): void
    {
        unset(self::$cache[$postId]);
        unset(self::$notFound[$postId]);
    }

    public function markNotFound(int $postId): void
    {
        self::$notFound[$postId] = true;
    }

    public function isMarkedNotFound(int $postId): bool
    {
        return isset(self::$notFound[$postId]);
    }

    public function clear(): void
    {
        self::$cache = [];
        self::$notFound = [];
    }

    /**
     * @param array<ShadowEntity> $entities
     */
    public function warmup(array $entities): void
    {
        foreach ($entities as $entity) {
            self::$cache[$entity->postId] = $entity;
        }
    }

    public function getStats(): array
    {
        return [
            'cached' => count(self::$cache),
            'not_found' => count(self::$notFound),
            'memory' => memory_get_usage(true),
        ];
    }
}
