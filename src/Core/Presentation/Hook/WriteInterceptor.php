<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use WP_Post;

final class WriteInterceptor
{
    private static array $supportedTypes = ['post', 'page', 'product'];

    public static function onSavePost(int $postId, WP_Post $post, bool $update): void
    {
        if (!in_array($post->post_type, self::$supportedTypes, true)) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if ($post->post_status === 'auto-draft') {
            return;
        }

        self::syncPost($postId, $post->post_type);
    }

    public static function onDeletePost(int $postId, WP_Post $post): void
    {
        if (!in_array($post->post_type, self::$supportedTypes, true)) {
            return;
        }

        self::deletePost($postId, $post->post_type);
    }

    /**
     * Hook for updated_post_meta and added_post_meta
     */
    public static function onMetaChange(int $metaId, int $objectId, string $metaKey, mixed $metaValue): void
    {
        self::handleMetaSync($objectId);
    }

    /**
     * Hook for deleted_post_meta - first argument can be array or int
     */
    public static function onMetaDeleted(array|int $metaIds, int $objectId, string $metaKey, mixed $metaValue): void
    {
        self::handleMetaSync($objectId);
    }

    private static function handleMetaSync(int $objectId): void
    {
        $post = get_post($objectId);
        if (!$post || !in_array($post->post_type, self::$supportedTypes, true)) {
            return;
        }

        self::syncPost($objectId, $post->post_type);
    }

    private static function syncPost(int $postId, string $postType): void
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        $driver = $factory->create();
        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new RuntimeCache();

        $syncService = new SyncService($repository, $cache);
        $syncService->syncPost($postId);
    }

    private static function deletePost(int $postId, string $postType): void
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        $driver = $factory->create();
        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new RuntimeCache();

        $syncService = new SyncService($repository, $cache);
        $syncService->deletePost($postId);
    }
}
