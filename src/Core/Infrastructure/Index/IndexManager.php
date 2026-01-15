<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Index;

use wpdb;

final class IndexManager
{
    private const INDEX_CONFIG = [
        'product' => [
            '_price' => ['type' => 'DECIMAL(20,6)', 'nullable' => true],
            '_sku' => ['type' => 'VARCHAR(100)', 'nullable' => true],
            '_stock' => ['type' => 'INT', 'nullable' => true],
            '_stock_status' => ['type' => 'VARCHAR(20)', 'nullable' => true],
            '_regular_price' => ['type' => 'DECIMAL(20,6)', 'nullable' => true],
        ],
        'product_variation' => [
            '_price' => ['type' => 'DECIMAL(20,6)', 'nullable' => true],
            '_sku' => ['type' => 'VARCHAR(100)', 'nullable' => true],
            '_stock' => ['type' => 'INT', 'nullable' => true],
            '_stock_status' => ['type' => 'VARCHAR(20)', 'nullable' => true],
        ],
        'post' => [
            'views_count' => ['type' => 'INT', 'nullable' => true],
        ],
    ];

    public function __construct(
        private readonly wpdb $wpdb,
        private readonly string $prefix = 'wp_',
    ) {
    }

    public function createIndexes(string $postType): int
    {
        $config = self::INDEX_CONFIG[$postType] ?? [];
        if (empty($config)) {
            return 0;
        }

        $table = $this->prefix . 'shadow_' . $postType;
        $created = 0;

        foreach ($config as $metaKey => $options) {
            if ($this->createIndex($table, $metaKey, $options['type'])) {
                $created++;
            }
        }

        return $created;
    }

    public function createIndex(string $table, string $metaKey, string $columnType): bool
    {
        $columnName = $this->sanitizeColumnName($metaKey);
        $indexName = 'idx_' . $columnName;
        $jsonPath = '$.' . $metaKey;

        if ($this->columnExists($table, $columnName)) {
            return false;
        }

        $castExpression = $this->getCastExpression($columnType, $jsonPath);

        $sql = "ALTER TABLE {$table} ADD COLUMN {$columnName} {$columnType} 
                GENERATED ALWAYS AS ({$castExpression}) STORED";

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            return false;
        }

        $this->wpdb->query("CREATE INDEX {$indexName} ON {$table} ({$columnName})");

        return true;
    }

    public function dropIndexes(string $postType): int
    {
        $config = self::INDEX_CONFIG[$postType] ?? [];
        if (empty($config)) {
            return 0;
        }

        $table = $this->prefix . 'shadow_' . $postType;
        $dropped = 0;

        foreach ($config as $metaKey => $options) {
            if ($this->dropIndex($table, $metaKey)) {
                $dropped++;
            }
        }

        return $dropped;
    }

    public function dropIndex(string $table, string $metaKey): bool
    {
        $columnName = $this->sanitizeColumnName($metaKey);
        $indexName = 'idx_' . $columnName;

        if (!$this->columnExists($table, $columnName)) {
            return false;
        }

        $this->wpdb->query("DROP INDEX IF EXISTS {$indexName} ON {$table}");
        $this->wpdb->query("ALTER TABLE {$table} DROP COLUMN IF EXISTS {$columnName}");

        return true;
    }

    public function getIndexStatus(string $postType): array
    {
        $config = self::INDEX_CONFIG[$postType] ?? [];
        $table = $this->prefix . 'shadow_' . $postType;
        $status = [];

        foreach ($config as $metaKey => $options) {
            $columnName = $this->sanitizeColumnName($metaKey);
            $status[$metaKey] = [
                'column' => $columnName,
                'exists' => $this->columnExists($table, $columnName),
                'indexed' => $this->indexExists($table, 'idx_' . $columnName),
            ];
        }

        return $status;
    }

    public function hasIndexes(string $postType): bool
    {
        $status = $this->getIndexStatus($postType);

        foreach ($status as $info) {
            if (!$info['exists'] || !$info['indexed']) {
                return false;
            }
        }

        return !empty($status);
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                $this->wpdb->dbname,
                $table,
                $column
            )
        );

        return (int) $result > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                $this->wpdb->dbname,
                $table,
                $indexName
            )
        );

        return (int) $result > 0;
    }

    private function sanitizeColumnName(string $metaKey): string
    {
        return preg_replace('/^_/', 'meta_', $metaKey);
    }

    private function getCastExpression(string $columnType, string $jsonPath): string
    {
        $upperType = strtoupper($columnType);

        if (str_starts_with($upperType, 'DECIMAL') || str_starts_with($upperType, 'INT')) {
            return "CAST(JSON_UNQUOTE(meta_data->'{$jsonPath}') AS {$columnType})";
        }

        return "JSON_UNQUOTE(meta_data->'{$jsonPath}')";
    }
}
