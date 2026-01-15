<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

final class BatchMigrationService
{
    private const OPTION_PREFIX = 'shadow_orm_migration_';
    private const BATCH_SIZE = 100;

    public static function startMigration(string $postType): array
    {
        global $wpdb;

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
            $postType
        ));

        $state = [
            'post_type' => $postType,
            'total' => $total,
            'migrated' => 0,
            'offset' => 0,
            'status' => 'running',
            'started_at' => time(),
        ];

        update_option(self::OPTION_PREFIX . $postType, $state);

        return $state;
    }

    public static function processBatch(string $postType): array
    {
        $state = get_option(self::OPTION_PREFIX . $postType);

        if (!$state || $state['status'] !== 'running') {
            return ['error' => 'No migration in progress'];
        }

        global $wpdb;

        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status != 'auto-draft'
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            $postType,
            self::BATCH_SIZE,
            $state['offset']
        ));

        if (empty($postIds)) {
            $state['status'] = 'completed';
            update_option(self::OPTION_PREFIX . $postType, $state);
            do_action('shadow_orm_sync_completed', $postType, $state['migrated']);

            return $state;
        }

        $syncService = self::getSyncService($postType);

        foreach ($postIds as $postId) {
            $syncService->syncPost((int) $postId);
            $state['migrated']++;
        }

        $state['offset'] += self::BATCH_SIZE;
        update_option(self::OPTION_PREFIX . $postType, $state);

        return $state;
    }

    public static function getProgress(string $postType): ?array
    {
        return get_option(self::OPTION_PREFIX . $postType) ?: null;
    }

    public static function cancelMigration(string $postType): void
    {
        delete_option(self::OPTION_PREFIX . $postType);
    }

    public static function isMigrating(string $postType): bool
    {
        $state = get_option(self::OPTION_PREFIX . $postType);

        return $state && $state['status'] === 'running';
    }

    private static function getSyncService(string $postType): SyncService
    {
        global $wpdb;

        $factory = new \ShadowORM\Core\Infrastructure\Driver\DriverFactory($wpdb);
        $driver = $factory->create();
        $schema = new \ShadowORM\Core\Domain\ValueObject\SchemaDefinition($postType);
        $repository = new \ShadowORM\Core\Infrastructure\Persistence\ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new \ShadowORM\Core\Application\Cache\RuntimeCache();
        $metaReader = new \ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader($wpdb);

        return new SyncService($repository, $cache, $metaReader);
    }
}
