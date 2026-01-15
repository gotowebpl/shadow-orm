<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Application\Service\AsyncWriteService;
use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader;
use WP_Post;

final class WriteInterceptor
{
    private static ?DriverFactory $factory = null;
    private static ?ShadowTableManager $tableManager = null;
    private static ?WpPostMetaReader $metaReader = null;
    private static array $repositories = [];
    private static array $syncServices = [];
    
    /** @var array<int, bool> Debounce sync - prevent multiple syncs per request */
    private static array $scheduledSync = [];

    public static function onSavePost(int $postId, WP_Post $post, bool $update): void
    {
        if (!SupportedTypes::isSupported($post->post_type)) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if ($post->post_status === 'auto-draft') {
            return;
        }

        self::scheduleSync($postId, $post->post_type);
    }

    public static function onDeletePost(int $postId, WP_Post $post): void
    {
        if (!SupportedTypes::isSupported($post->post_type)) {
            return;
        }

        self::scheduleDelete($postId, $post->post_type);
    }

    public static function onMetaChange(int $metaId, int $objectId, string $metaKey, mixed $metaValue): void
    {
        self::handleMetaSync($objectId);
    }

    public static function onMetaDeleted(array|int $metaIds, int $objectId, string $metaKey, mixed $metaValue): void
    {
        self::handleMetaSync($objectId);
    }

    private static function handleMetaSync(int $objectId): void
    {
        $postType = get_post_type($objectId);

        if ($postType === false || !SupportedTypes::isSupported($postType)) {
            return;
        }

        self::scheduleSync($objectId, $postType);
    }

    private static function scheduleSync(int $postId, string $postType): void
    {
        // Debounce: only schedule once per post per request
        if (isset(self::$scheduledSync[$postId])) {
            return;
        }
        self::$scheduledSync[$postId] = true;

        $tableManager = self::getTableManager();

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        // Use async write if enabled
        if (AsyncWriteService::isEnabled()) {
            AsyncWriteService::scheduleSync($postId, $postType);
        } else {
            // Synchronous fallback
            self::getSyncService($postType)->syncPost($postId);
        }
        
        // Invalidate runtime cache
        $cache = new RuntimeCache();
        $cache->delete($postId);
    }

    private static function scheduleDelete(int $postId, string $postType): void
    {
        $tableManager = self::getTableManager();

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        if (AsyncWriteService::isEnabled()) {
            AsyncWriteService::scheduleDelete($postId, $postType);
        } else {
            self::getSyncService($postType)->deletePost($postId);
        }
        
        $cache = new RuntimeCache();
        $cache->delete($postId);
    }

    private static function getFactory(): DriverFactory
    {
        if (self::$factory === null) {
            global $wpdb;
            self::$factory = new DriverFactory($wpdb);
        }

        return self::$factory;
    }

    private static function getTableManager(): ShadowTableManager
    {
        if (self::$tableManager === null) {
            global $wpdb;
            self::$tableManager = new ShadowTableManager($wpdb, self::getFactory());
        }

        return self::$tableManager;
    }

    private static function getMetaReader(): WpPostMetaReader
    {
        if (self::$metaReader === null) {
            global $wpdb;
            self::$metaReader = new WpPostMetaReader($wpdb);
        }

        return self::$metaReader;
    }

    private static function getSyncService(string $postType): SyncService
    {
        if (isset(self::$syncServices[$postType])) {
            return self::$syncServices[$postType];
        }

        global $wpdb;

        $driver = self::getFactory()->create();
        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new RuntimeCache();
        $metaReader = self::getMetaReader();

        return self::$syncServices[$postType] = new SyncService($repository, $cache, $metaReader);
    }
}
