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

    public static function intercept(
        mixed $value,
        int $objectId,
        string $metaKey,
        bool $single,
        string $metaType
    ): mixed {
        if ($metaType !== 'post' || $value !== null) {
            return $value;
        }

        $postType = get_post_type($objectId);

        if ($postType === false || !SupportedTypes::isSupported($postType)) {
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
            if (!$cache->has($postId) && !$cache->isMarkedNotFound($postId)) {
                $toLoad[] = (int) $postId;
            }
        }

        if (empty($toLoad)) {
            return;
        }

        $repository = self::getRepository($postType);
        if ($repository === null) {
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

    private static function loadEntity(int $postId, string $postType): ?ShadowEntity
    {
        $repository = self::getRepository($postType);

        return $repository?->find($postId);
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

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));

        if (!$exists) {
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
