<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Persistence;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use wpdb;

final class ShadowTableManager
{
    public function __construct(
        private readonly wpdb $wpdb,
        private readonly DriverFactory $driverFactory,
    ) {
    }

    public function createTable(SchemaDefinition $schema): void
    {
        $table = $schema->getTableName($this->wpdb->prefix);
        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(20) NOT NULL,
            content LONGTEXT,
            meta_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id),
            KEY post_type_idx (post_type)
        ) {$charset}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if (!$this->driverFactory->isMySQL8()) {
            $this->createLookupTable($schema);
        }

        $this->createVirtualColumns($schema);
    }

    public function dropTable(string $postType): void
    {
        $schema = new SchemaDefinition($postType);
        $table = $schema->getTableName($this->wpdb->prefix);
        $lookupTable = $schema->getLookupTableName($this->wpdb->prefix);

        $this->wpdb->query("DROP TABLE IF EXISTS `{$lookupTable}`");
        $this->wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    public function tableExists(string $postType): bool
    {
        $schema = new SchemaDefinition($postType);
        $table = $schema->getTableName($this->wpdb->prefix);

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $table
            )
        );

        return (int) $result > 0;
    }

    public function addVirtualColumn(string $postType, string $columnName, string $jsonPath): void
    {
        $schema = new SchemaDefinition($postType);
        $table = $schema->getTableName($this->wpdb->prefix);

        if (!$this->driverFactory->isMySQL8()) {
            return;
        }

        $this->wpdb->query(
            "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `{$columnName}` VARCHAR(255) 
             GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(meta_data, '{$jsonPath}'))) STORED"
        );

        $this->wpdb->query(
            "CREATE INDEX IF NOT EXISTS `idx_{$columnName}` ON `{$table}` (`{$columnName}`)"
        );
    }

    public function getTableStats(string $postType): array
    {
        $schema = new SchemaDefinition($postType);
        $table = $schema->getTableName($this->wpdb->prefix);

        if (!$this->tableExists($postType)) {
            return [
                'exists' => false,
                'count' => 0,
                'size' => 0,
            ];
        }

        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

        $size = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT (DATA_LENGTH + INDEX_LENGTH) FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $table
            )
        );

        return [
            'exists' => true,
            'count' => $count,
            'size' => (int) $size,
        ];
    }

    private function createLookupTable(SchemaDefinition $schema): void
    {
        $lookupTable = $schema->getLookupTableName($this->wpdb->prefix);
        $charset = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$lookupTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            PRIMARY KEY (id),
            KEY post_id_idx (post_id),
            KEY meta_key_idx (meta_key(191)),
            KEY meta_key_value_idx (meta_key(191), meta_value(191))
        ) {$charset}";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function createVirtualColumns(SchemaDefinition $schema): void
    {
        if (!$this->driverFactory->isMySQL8()) {
            return;
        }

        foreach ($schema->virtualColumns as $column => $jsonPath) {
            $this->addVirtualColumn($schema->postType, $column, $jsonPath);
        }
    }
}
