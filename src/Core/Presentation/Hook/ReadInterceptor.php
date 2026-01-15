<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;

final class ReadInterceptor
{
    private static ?RuntimeCache $cache = null;
    private static array $supportedTypes = ['post', 'page', 'product'];

    public static function intercept(
        mixed $value,
        int $objectId,
        string $metaKey,
        bool $single,
        string $metaType
    ): mixed {
        if ($metaType !== 'post') {
            return $value;
        }

        if ($value !== null) {
            return $value;
        }

        $post = get_post($objectId);

        if ($post === null || !in_array($post->post_type, self::$supportedTypes, true)) {
            return $value;
        }

        $cache = self::getCache();

        if ($cache->isMarkedNotFound($objectId)) {
            return $value;
        }

        $entity = $cache->get($objectId);

        if ($entity === null) {
            $entity = self::loadEntity($objectId, $post->post_type);

            if ($entity === null) {
                $cache->markNotFound($objectId);
                return $value;
            }

            $cache->set($objectId, $entity);
        }

        if ($metaKey === '') {
            // Return raw map directly, do not wrap in array
            return array_map(fn($v) => is_array($v) ? $v : [$v], $entity->getAllMeta());
        }

        if (!$entity->hasMeta($metaKey)) {
            return $value;
        }

        $metaValue = $entity->getMeta($metaKey);

        // Always return array wrapping the value.
        // If single=true, WP core extracts [0].
        // If single=false, WP core returns the array.
        return [$metaValue];
    }

    private static function loadEntity(int $postId, string $postType): ?\ShadowORM\Core\Domain\Entity\ShadowEntity
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);

        try {
            $driver = $factory->create();
        } catch (\Exception) {
            return null;
        }

        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);

        return $repository->find($postId);
    }

    private static function getCache(): RuntimeCache
    {
        return self::$cache ??= new RuntimeCache();
    }
}
