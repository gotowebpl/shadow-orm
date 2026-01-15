<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

final class ReadInterceptor
{
    private static ?RuntimeCache $cache = null;
    private static ?DriverFactory $factory = null;
    private static array $repositories = [];
    
    /** @var array<int, string|false> Post type cache to avoid repeated get_post_type() calls */
    private static array $postTypeCache = [];
    
    /** @var array<int, bool> Cache of posts that should be skipped (unsupported or no table) */
    private static array $skipCache = [];

    public static function intercept(
        mixed $value,
        int $objectId,
        string $metaKey,
        bool $single,
        string $metaType
    ): mixed {
        // Fast path: not post meta or already has value
        if ($metaType !== 'post' || $value !== null) {
            return $value;
        }

        // Fast path: already know to skip this post
        if (isset(self::$skipCache[$objectId])) {
            return $value;
        }

        // Get post type with caching
        $postType = self::getPostType($objectId);

        if ($postType === false || !SupportedTypes::isSupported($postType)) {
            self::$skipCache[$objectId] = true;
            return $value;
        }

        $cache = self::getCache();

        if ($cache->isMarkedNotFound($objectId)) {
            return $value;
        }

        $entity = $cache->get($objectId);

        if ($entity === null) {
            $entity = self::loadEntity($objectId, $postType);

            if ($entity === null) {
                $cache->markNotFound($objectId);
                return $value;
            }

            $cache->set($objectId, $entity);
        }

        if ($metaKey === '') {
            return array_map(static fn($v) => is_array($v) ? $v : [$v], $entity->getAllMeta());
        }

        if (!$entity->hasMeta($metaKey)) {
            return $value;
        }

        return [$entity->getMeta($metaKey)];
    }

    public static function preloadEntities(array $postIds, string $postType): void
    {
        if (empty($postIds)) {
            return;
        }

        $cache = self::getCache();
        $toLoad = [];

        foreach ($postIds as $postId) {
            $id = (int) $postId;
            if (!$cache->has($id) && !$cache->isMarkedNotFound($id) && !isset(self::$skipCache[$id])) {
                $toLoad[] = $id;
                // Pre-cache post type
                self::$postTypeCache[$id] = $postType;
            }
        }

        if (empty($toLoad)) {
            return;
        }

        $repository = self::getRepository($postType);
        if ($repository === null) {
            // Mark all as skip - no table exists
            foreach ($toLoad as $postId) {
                self::$skipCache[$postId] = true;
            }
            return;
        }

        $entities = $repository->findMany($toLoad);

        foreach ($entities as $postId => $entity) {
            $cache->set($postId, $entity);
        }

        foreach ($toLoad as $postId) {
            if (!isset($entities[$postId])) {
                $cache->markNotFound($postId);
            }
        }
    }

    public static function clearCaches(): void
    {
        self::$postTypeCache = [];
        self::$skipCache = [];
        self::$cache?->clear();
    }

    private static function getPostType(int $postId): string|false
    {
        if (isset(self::$postTypeCache[$postId])) {
            return self::$postTypeCache[$postId];
        }

        $postType = get_post_type($postId);
        self::$postTypeCache[$postId] = $postType;

        return $postType;
    }

    private static function loadEntity(int $postId, string $postType): ?ShadowEntity
    {
        $repository = self::getRepository($postType);

        if ($repository === null) {
            self::$skipCache[$postId] = true;
            return null;
        }

        return $repository->find($postId);
    }

    private static function getRepository(string $postType): ?ShadowRepository
    {
        if (isset(self::$repositories[$postType])) {
            return self::$repositories[$postType];
        }

        global $wpdb;

        $factory = self::getFactory();
        $schema = new SchemaDefinition($postType);
        $tableName = $schema->getTableName($wpdb->prefix);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));

        if (!$exists) {
            self::$repositories[$postType] = null;
            return null;
        }

        $driver = $factory->create();

        return self::$repositories[$postType] = new ShadowRepository($driver, $schema, $wpdb->prefix);
    }

    private static function getFactory(): DriverFactory
    {
        if (self::$factory === null) {
            global $wpdb;
            self::$factory = new DriverFactory($wpdb);
        }

        return self::$factory;
    }

    private static function getCache(): RuntimeCache
    {
        return self::$cache ??= new RuntimeCache();
    }
}
