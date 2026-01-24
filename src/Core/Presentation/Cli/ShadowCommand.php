<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Cli;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Application\Service\AutoDiscoveryService;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Infrastructure\Persistence\WpPostMetaReader;
use ShadowORM\Core\Infrastructure\Index\IndexManager;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use WP_CLI;

final class ShadowCommand
{
    /**
     * @subcommand migrate
     */
    public function migrate(array $args, array $assoc): void
    {
        global $wpdb;

        $type = $assoc['type'] ?? null;
        $all = isset($assoc['all']);
        $dryRun = isset($assoc['dry-run']);
        $batch = (int) ($assoc['batch'] ?? 500);

        if (!$type && !$all) {
            WP_CLI::error('Specify --type=<post_type> or --all');
        }

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $driver = $factory->create();

        $types = $all ? $this->getAllConfiguredTypes() : [$type];

        foreach ($types as $postType) {
            $this->migrateType($postType, $driver, $tableManager, $wpdb, $batch, $dryRun);
        }
    }

    /**
     * @subcommand status
     */
    public function status(array $args, array $assoc): void
    {
        global $wpdb;

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $indexManager = new IndexManager($wpdb, $wpdb->prefix);

        $headers = ['Post Type', 'Total', 'Migrated', 'Size', 'Indexes', 'Driver'];
        $rows = [];

        foreach ($this->getAllConfiguredTypes() as $postType) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                    $postType
                )
            );

            $stats = $tableManager->getTableStats($postType);
            $hasIndexes = $stats['exists'] && $indexManager->hasIndexes($postType);

            $rows[] = [
                'Post Type' => $postType,
                'Total' => $total,
                'Migrated' => $stats['exists'] ? $stats['count'] : 0,
                'Size' => $stats['exists'] ? $this->formatBytes($stats['size']) : '-',
                'Indexes' => $hasIndexes ? 'Yes' : 'No',
                'Driver' => $stats['exists'] ? $factory->create()->getDriverName() : '-',
            ];
        }

        WP_CLI\Utils\format_items('table', $rows, $headers);
    }

    /**
     * @subcommand index
     */
    public function index(array $args, array $assoc): void
    {
        global $wpdb;

        $action = $args[0] ?? 'status';
        $type = $assoc['type'] ?? null;
        $all = isset($assoc['all']);

        $indexManager = new IndexManager($wpdb, $wpdb->prefix);
        $types = $all ? $this->getAllConfiguredTypes() : ($type ? [$type] : $this->getAllConfiguredTypes());

        match ($action) {
            'create' => $this->indexCreate($indexManager, $types),
            'drop' => $this->indexDrop($indexManager, $types),
            'status' => $this->indexStatus($indexManager, $types),
            default => WP_CLI::error("Unknown action: {$action}. Use: create, drop, status"),
        };
    }

    private function indexCreate(IndexManager $manager, array $types): void
    {
        foreach ($types as $postType) {
            $count = $manager->createIndexes($postType);
            WP_CLI::log("Created {$count} indexes for '{$postType}'");
        }

        WP_CLI::success('Indexes created');
    }

    private function indexDrop(IndexManager $manager, array $types): void
    {
        foreach ($types as $postType) {
            $count = $manager->dropIndexes($postType);
            WP_CLI::log("Dropped {$count} indexes for '{$postType}'");
        }

        WP_CLI::success('Indexes dropped');
    }

    private function indexStatus(IndexManager $manager, array $types): void
    {
        $headers = ['Post Type', 'Meta Key', 'Column', 'Exists', 'Indexed'];
        $rows = [];

        foreach ($types as $postType) {
            $status = $manager->getIndexStatus($postType);

            foreach ($status as $metaKey => $info) {
                $rows[] = [
                    'Post Type' => $postType,
                    'Meta Key' => $metaKey,
                    'Column' => $info['column'],
                    'Exists' => $info['exists'] ? 'Yes' : 'No',
                    'Indexed' => $info['indexed'] ? 'Yes' : 'No',
                ];
            }
        }

        if (empty($rows)) {
            WP_CLI::log('No index configuration found for these post types.');
            return;
        }

        WP_CLI\Utils\format_items('table', $rows, $headers);
    }

    /**
     * @subcommand rollback
     */
    public function rollback(array $args, array $assoc): void
    {
        global $wpdb;

        $type = $assoc['type'] ?? null;

        if (!$type) {
            WP_CLI::error('Specify --type=<post_type>');
        }

        WP_CLI::confirm("This will delete shadow table for '{$type}'. Continue?");

        $factory = new DriverFactory($wpdb);
        $tableManager = new ShadowTableManager($wpdb, $factory);
        $tableManager->dropTable($type);

        WP_CLI::success("Shadow table for '{$type}' dropped");
    }

    /**
     * @subcommand benchmark
     */
    public function benchmark(array $args, array $assoc): void
    {
        global $wpdb;

        $type = $assoc['type'] ?? 'post';
        $iterations = (int) ($assoc['iterations'] ?? 10);

        $factory = new DriverFactory($wpdb);

        if (!$factory->create()->supportsNativeJson()) {
            WP_CLI::warning('Running on Legacy driver (no native JSON)');
        }

        $schema = new SchemaDefinition($type);
        $table = $schema->getTableName($wpdb->prefix);

        $postId = (int) $wpdb->get_var(
            "SELECT post_id FROM `{$table}` LIMIT 1"
        );

        if (!$postId) {
            WP_CLI::error("No posts found in shadow table for '{$type}'");
        }

        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE post_id = %d", $postId));
            $times[] = (microtime(true) - $start) * 1000;
        }

        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);

        WP_CLI::log(sprintf('Benchmark results for %d iterations:', $iterations));
        WP_CLI::log(sprintf('  Average: %.3f ms', $avg));
        WP_CLI::log(sprintf('  Min: %.3f ms', $min));
        WP_CLI::log(sprintf('  Max: %.3f ms', $max));
    }

    private function migrateType(string $postType, $driver, $tableManager, $wpdb, int $batch, bool $dryRun): void
    {
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                $postType
            )
        );

        if ($dryRun) {
            WP_CLI::log("Would migrate {$total} posts of type '{$postType}'");
            return;
        }

        $schema = new SchemaDefinition($postType);
        $tableManager->createTable($schema);

        $repository = new ShadowRepository($driver, $schema, $wpdb->prefix);
        $cache = new RuntimeCache();
        $metaReader = new WpPostMetaReader($wpdb);
        $syncService = new SyncService($repository, $cache, $metaReader);

        $progress = WP_CLI\Utils\make_progress_bar("Migrating {$postType}", $total);

        $migrated = $syncService->migrateAll($postType, $batch, function ($current, $total) use ($progress) {
            $progress->tick();
        });

        $progress->finish();

        WP_CLI::success("Migrated {$migrated} posts of type '{$postType}'");
    }

    private function getAllConfiguredTypes(): array
    {
        return ['post', 'page', 'product', 'product_variation'];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

