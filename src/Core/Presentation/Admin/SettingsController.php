<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Admin;

use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

final class SettingsController
{
    private const OPTION_ENABLED = 'shadow_orm_enabled';
    private const OPTION_POST_TYPES = 'shadow_orm_post_types';
    private const OPTION_DRIVER = 'shadow_orm_driver';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('shadow-orm/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getSettingsEndpoint'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'saveSettingsEndpoint'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
        ]);

        register_rest_route('shadow-orm/v1', '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'getStatusEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);

        register_rest_route('shadow-orm/v1', '/sync', [
            'methods' => 'POST',
            'callback' => [self::class, 'syncEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);

        register_rest_route('shadow-orm/v1', '/sync/start', [
            'methods' => 'POST',
            'callback' => [self::class, 'syncStartEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);

        register_rest_route('shadow-orm/v1', '/sync/batch', [
            'methods' => 'POST',
            'callback' => [self::class, 'syncBatchEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);

        register_rest_route('shadow-orm/v1', '/sync/progress', [
            'methods' => 'GET',
            'callback' => [self::class, 'syncProgressEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);

        register_rest_route('shadow-orm/v1', '/rollback', [
            'methods' => 'POST',
            'callback' => [self::class, 'rollbackEndpoint'],
            'permission_callback' => [self::class, 'checkPermission'],
        ]);
    }

    public static function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public static function getSettingsEndpoint(): WP_REST_Response
    {
        return new WP_REST_Response(self::getSettings());
    }

    public static function saveSettingsEndpoint(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();

        if (isset($params['enabled'])) {
            update_option(self::OPTION_ENABLED, (bool) $params['enabled']);
        }

        if (isset($params['post_types']) && is_array($params['post_types'])) {
            update_option(self::OPTION_POST_TYPES, array_map('sanitize_key', $params['post_types']));
        }

        if (isset($params['driver'])) {
            update_option(self::OPTION_DRIVER, sanitize_key($params['driver']));
        }

        return new WP_REST_Response(['success' => true, 'settings' => self::getSettings()]);
    }

    public static function getStatusEndpoint(): WP_REST_Response
    {
        return new WP_REST_Response(self::getStatus());
    }

    public static function syncEndpoint(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postType = $request->get_param('post_type');

        if (!$postType) {
            return new WP_Error('missing_post_type', 'Post type is required', ['status' => 400]);
        }

        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $driver = $factory->create();

        $schema = new SchemaDefinition($postType);
        $tableManager->createTable($schema);

        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new RuntimeCache();
        $syncService = new SyncService($repository, $cache);

        $migrated = $syncService->migrateAll($postType, 100);

        // Clean up orphaned records (exist in shadow but not in posts)
        $tableName = $schema->getTableName($wpdb->prefix);
        $wpdb->query("
            DELETE s FROM {$tableName} s
            LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID
            WHERE p.ID IS NULL
        ");

        return new WP_REST_Response([
            'success' => true,
            'migrated' => $migrated,
            'post_type' => $postType,
        ]);
    }

    public static function rollbackEndpoint(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postType = $request->get_param('post_type');

        if (!$postType) {
            return new WP_Error('missing_post_type', 'Post type is required', ['status' => 400]);
        }

        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $tableManager->dropTable($postType);

        return new WP_REST_Response([
            'success' => true,
            'post_type' => $postType,
        ]);
    }

    public static function syncStartEndpoint(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postType = $request->get_param('post_type');

        if (!$postType) {
            return new WP_Error('missing_post_type', 'Post type is required', ['status' => 400]);
        }

        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $schema = new SchemaDefinition($postType);
        $tableManager->createTable($schema);

        $state = \ShadowORM\Core\Application\Service\BatchMigrationService::startMigration($postType);

        return new WP_REST_Response([
            'success' => true,
            'state' => $state,
        ]);
    }

    public static function syncBatchEndpoint(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postType = $request->get_param('post_type');

        if (!$postType) {
            return new WP_Error('missing_post_type', 'Post type is required', ['status' => 400]);
        }

        $state = \ShadowORM\Core\Application\Service\BatchMigrationService::processBatch($postType);

        if (isset($state['error'])) {
            return new WP_Error('batch_error', $state['error'], ['status' => 400]);
        }

        return new WP_REST_Response([
            'success' => true,
            'state' => $state,
        ]);
    }

    public static function syncProgressEndpoint(WP_REST_Request $request): WP_REST_Response
    {
        $postType = $request->get_param('post_type');

        $progress = $postType
            ? \ShadowORM\Core\Application\Service\BatchMigrationService::getProgress($postType)
            : null;

        return new WP_REST_Response([
            'progress' => $progress,
        ]);
    }

    public static function getSettings(): array
    {
        return [
            'enabled' => (bool) get_option(self::OPTION_ENABLED, true),
            'post_types' => (array) get_option(self::OPTION_POST_TYPES, ['post', 'page', 'product']),
            'driver' => (string) get_option(self::OPTION_DRIVER, 'auto'),
        ];
    }

    public static function getStatus(): array
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);

        $postTypes = self::getAvailablePostTypes();
        $status = [];

        foreach ($postTypes as $postType) {
            $stats = $tableManager->getTableStats($postType);

            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                    $postType
                )
            );

            $status[$postType] = [
                'total' => $total,
                'migrated' => $stats['exists'] ? $stats['count'] : 0,
                'size' => $stats['exists'] ? $stats['size'] : 0,
                'exists' => $stats['exists'],
            ];
        }

        return [
            'post_types' => $status,
            'driver' => $factory->create()->getDriverName(),
            'mysql_version' => $factory->getMySQLVersion(),
            'is_mysql8' => $factory->isMySQL8(),
        ];
    }

    private static function getAvailablePostTypes(): array
    {
        $types = get_post_types(['public' => true], 'names');
        $excluded = ['attachment'];

        return array_values(array_diff($types, $excluded));
    }
}
