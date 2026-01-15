<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

final class IntegrityCheckService
{
    private const SAMPLE_SIZE = 100;
    private const OPTION_LAST_CHECK = 'shadow_orm_integrity_last_check';
    private const OPTION_ISSUES = 'shadow_orm_integrity_issues';

    public static function check(string $postType, int $sampleSize = self::SAMPLE_SIZE): array
    {
        global $wpdb;

        $schema = new SchemaDefinition($postType);
        $tableName = $schema->getTableName($wpdb->prefix);

        // Check if shadow table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
        if (!$exists) {
            return ['status' => 'no_table', 'message' => 'Shadow table does not exist'];
        }

        // Get random sample of post IDs from shadow table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $shadowIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$tableName} ORDER BY RAND() LIMIT %d",
                $sampleSize
            )
        );

        if (empty($shadowIds)) {
            return ['status' => 'empty', 'message' => 'Shadow table is empty'];
        }

        $factory = new DriverFactory($wpdb);
        $driver = $factory->create();
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);

        $issues = [];
        $checked = 0;

        foreach ($shadowIds as $postId) {
            $postId = (int) $postId;
            $shadowEntity = $repository->find($postId);

            if ($shadowEntity === null) {
                continue;
            }

            // Get current meta from wp_postmeta
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $currentMeta = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                    $postId
                ),
                ARRAY_A
            );

            $wpMeta = [];
            foreach ($currentMeta as $row) {
                $wpMeta[$row['meta_key']] = maybe_unserialize($row['meta_value']);
            }

            // Compare with shadow data
            $shadowMeta = $shadowEntity->getAllMeta();
            $mismatches = [];

            foreach ($shadowMeta as $key => $shadowValue) {
                if (!isset($wpMeta[$key])) {
                    $mismatches[$key] = ['shadow' => $shadowValue, 'wp' => null];
                } elseif ($wpMeta[$key] !== $shadowValue) {
                    // Handle serialized data comparison
                    $wpSerialized = maybe_serialize($wpMeta[$key]);
                    $shadowSerialized = maybe_serialize($shadowValue);
                    if ($wpSerialized !== $shadowSerialized) {
                        $mismatches[$key] = ['shadow' => $shadowValue, 'wp' => $wpMeta[$key]];
                    }
                }
            }

            if (!empty($mismatches)) {
                $issues[$postId] = $mismatches;
            }

            $checked++;
        }

        $result = [
            'status' => empty($issues) ? 'ok' : 'issues_found',
            'checked' => $checked,
            'issues_count' => count($issues),
            'issues' => array_slice($issues, 0, 10, true), // Limit to first 10
            'timestamp' => time(),
        ];

        update_option(self::OPTION_LAST_CHECK, $result);

        if (!empty($issues)) {
            update_option(self::OPTION_ISSUES, $issues);
        } else {
            delete_option(self::OPTION_ISSUES);
        }

        return $result;
    }

    public static function checkAll(): array
    {
        $results = [];

        foreach (SupportedTypes::get() as $postType) {
            $results[$postType] = self::check($postType);
        }

        return $results;
    }

    public static function getLastCheck(): ?array
    {
        return get_option(self::OPTION_LAST_CHECK, null);
    }

    public static function hasIssues(): bool
    {
        $issues = get_option(self::OPTION_ISSUES, []);
        return !empty($issues);
    }

    public static function getIssuesCount(): int
    {
        $issues = get_option(self::OPTION_ISSUES, []);
        return count($issues);
    }

    public static function repairPost(int $postId, string $postType): bool
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new \ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager($wpdb, $factory);

        if (!$tableManager->tableExists($postType)) {
            return false;
        }

        $driver = $factory->create();
        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new \ShadowORM\Core\Application\Cache\RuntimeCache();
        $metaReader = new \ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader($wpdb);

        $syncService = new SyncService($repository, $cache, $metaReader);
        $syncService->syncPost($postId);

        return true;
    }
}
