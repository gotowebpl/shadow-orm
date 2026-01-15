<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class AsyncWriteService
{
    private const HOOK_NAME = 'shadow_orm_async_sync';
    private const HOOK_DELETE = 'shadow_orm_async_delete';
    private const GROUP = 'shadow-orm';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_action(self::HOOK_NAME, [self::class, 'processSync'], 10, 2);
        add_action(self::HOOK_DELETE, [self::class, 'processDelete'], 10, 2);

        self::$registered = true;
    }

    public static function scheduleSync(int $postId, string $postType): void
    {
        if (self::hasActionScheduler()) {
            as_enqueue_async_action(self::HOOK_NAME, [$postId, $postType], self::GROUP);
        } else {
            // Fallback: use shutdown hook for deferred sync
            add_action('shutdown', static function () use ($postId, $postType): void {
                self::processSync($postId, $postType);
            }, 100);
        }
    }

    public static function scheduleDelete(int $postId, string $postType): void
    {
        if (self::hasActionScheduler()) {
            as_enqueue_async_action(self::HOOK_DELETE, [$postId, $postType], self::GROUP);
        } else {
            add_action('shutdown', static function () use ($postId, $postType): void {
                self::processDelete($postId, $postType);
            }, 100);
        }
    }

    public static function processSync(int $postId, string $postType): void
    {
        global $wpdb;

        $factory = new \ShadowORM\Core\Infrastructure\Driver\DriverFactory($wpdb);
        $tableManager = new \ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager($wpdb, $factory);

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        $driver = $factory->create();
        $schema = new \ShadowORM\Core\Domain\ValueObject\SchemaDefinition($postType);
        $repository = new \ShadowORM\Core\Infrastructure\Persistence\ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new \ShadowORM\Core\Application\Cache\RuntimeCache();
        $metaReader = new \ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader($wpdb);

        $syncService = new SyncService($repository, $cache, $metaReader);
        $syncService->syncPost($postId);
    }

    public static function processDelete(int $postId, string $postType): void
    {
        global $wpdb;

        $factory = new \ShadowORM\Core\Infrastructure\Driver\DriverFactory($wpdb);
        $tableManager = new \ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager($wpdb, $factory);

        if (!$tableManager->tableExists($postType)) {
            return;
        }

        $driver = $factory->create();
        $schema = new \ShadowORM\Core\Domain\ValueObject\SchemaDefinition($postType);
        $repository = new \ShadowORM\Core\Infrastructure\Persistence\ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new \ShadowORM\Core\Application\Cache\RuntimeCache();
        $metaReader = new \ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader($wpdb);

        $syncService = new SyncService($repository, $cache, $metaReader);
        $syncService->deletePost($postId);
    }

    public static function hasActionScheduler(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    public static function isEnabled(): bool
    {
        return (bool) get_option('shadow_orm_async_write', true);
    }
}
