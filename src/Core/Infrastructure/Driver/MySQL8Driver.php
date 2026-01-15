<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Driver;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use wpdb;

final class MySQL8Driver implements StorageDriverInterface
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function insert(string $table, ShadowEntity $entity): int
    {
        $this->wpdb->insert(
            $table,
            [
                'post_id' => $entity->postId,
                'post_type' => $entity->postType,
                'content' => $entity->content,
                'meta_data' => json_encode($entity->getAllMeta(), self::JSON_FLAGS),
            ],
            ['%d', '%s', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function update(string $table, ShadowEntity $entity): bool
    {
        return $this->wpdb->update(
            $table,
            [
                'content' => $entity->content,
                'meta_data' => json_encode($entity->getAllMeta(), self::JSON_FLAGS),
            ],
            ['post_id' => $entity->postId],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public function delete(string $table, int $postId): bool
    {
        return $this->wpdb->delete($table, ['post_id' => $postId], ['%d']) !== false;
    }

    public function findByPostId(string $table, int $postId): ?ShadowEntity
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT post_id, post_type, content, meta_data FROM {$table} WHERE post_id = %d LIMIT 1", $postId),
            ARRAY_A
        );

        return $row ? $this->hydrateEntity($row) : null;
    }

    public function findByMetaQuery(string $table, array $metaQuery): array
    {
        $where = $this->buildMetaQueryWhere($metaQuery);

        if ($where === '') {
            return [];
        }

        $rows = $this->wpdb->get_results(
            "SELECT post_id, post_type, content, meta_data FROM {$table} WHERE {$where}",
            ARRAY_A
        );

        return array_map($this->hydrateEntity(...), $rows);
    }

    public function createIndex(string $table, string $jsonPath, string $indexName): void
    {
        $virtualColumn = $indexName . '_idx';

        $this->wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$virtualColumn} VARCHAR(255) 
             GENERATED ALWAYS AS (meta_data->>'{$jsonPath}') STORED"
        );

        $this->wpdb->query("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$virtualColumn})");
    }

    public function dropIndex(string $table, string $indexName): void
    {
        $this->wpdb->query("DROP INDEX IF EXISTS {$indexName} ON {$table}");
    }

    public function supportsNativeJson(): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return 'MySQL8';
    }

    public function findMany(string $table, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $postIds = array_map('intval', $postIds);
        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT post_id, post_type, content, meta_data FROM {$table} WHERE post_id IN ({$placeholders})",
                ...$postIds
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $postId = (int) $row['post_id'];
            $result[$postId] = $this->hydrateEntity($row);
        }

        return $result;
    }

    public function exists(string $table, int $postId): bool
    {
        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT 1 FROM {$table} WHERE post_id = %d LIMIT 1", $postId)
        );
    }

    private function buildMetaQueryWhere(array $metaQuery): string
    {
        $conditions = [];

        foreach ($metaQuery as $query) {
            if (!isset($query['key'])) {
                continue;
            }

            $key = $this->wpdb->_real_escape($query['key']);
            $value = $query['value'] ?? null;
            $compare = strtoupper($query['compare'] ?? '=');
            $jsonPath = '$.' . $key;
            $escapedPath = $this->wpdb->_real_escape($jsonPath);

            $condition = match ($compare) {
                '=' => $this->wpdb->prepare("meta_data->>%s = %s", $jsonPath, $value),
                '!=' => $this->wpdb->prepare("meta_data->>%s != %s", $jsonPath, $value),
                '>' => $this->wpdb->prepare("CAST(meta_data->>%s AS DECIMAL(20,6)) > %f", $jsonPath, (float) $value),
                '<' => $this->wpdb->prepare("CAST(meta_data->>%s AS DECIMAL(20,6)) < %f", $jsonPath, (float) $value),
                '>=' => $this->wpdb->prepare("CAST(meta_data->>%s AS DECIMAL(20,6)) >= %f", $jsonPath, (float) $value),
                '<=' => $this->wpdb->prepare("CAST(meta_data->>%s AS DECIMAL(20,6)) <= %f", $jsonPath, (float) $value),
                'LIKE' => $this->wpdb->prepare("meta_data->>%s LIKE %s", $jsonPath, '%' . $this->wpdb->esc_like((string) $value) . '%'),
                'IN' => sprintf(
                    "meta_data->>'%s' IN (%s)",
                    $escapedPath,
                    implode(',', array_map(fn($v) => $this->wpdb->prepare('%s', $v), (array) $value))
                ),
                'NOT IN' => sprintf(
                    "meta_data->>'%s' NOT IN (%s)",
                    $escapedPath,
                    implode(',', array_map(fn($v) => $this->wpdb->prepare('%s', $v), (array) $value))
                ),
                'EXISTS' => sprintf("JSON_CONTAINS_PATH(meta_data, 'one', '%s')", $escapedPath),
                'NOT EXISTS' => sprintf("NOT JSON_CONTAINS_PATH(meta_data, 'one', '%s')", $escapedPath),
                'BETWEEN' => $this->wpdb->prepare(
                    "CAST(meta_data->>%s AS DECIMAL(20,6)) BETWEEN %f AND %f",
                    $jsonPath,
                    (float) ($value[0] ?? 0),
                    (float) ($value[1] ?? 0)
                ),
                default => null,
            };

            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        if (empty($conditions)) {
            return '';
        }

        $relationValue = $metaQuery['relation'] ?? 'AND';
        $relation = in_array(strtoupper((string) $relationValue), ['AND', 'OR'], true)
            ? strtoupper((string) $relationValue)
            : 'AND';

        return implode(" {$relation} ", $conditions);
    }

    private function hydrateEntity(array $row): ShadowEntity
    {
        return new ShadowEntity(
            postId: (int) $row['post_id'],
            postType: (string) ($row['post_type'] ?? ''),
            content: (string) ($row['content'] ?? ''),
            metaData: json_decode($row['meta_data'] ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: [],
        );
    }
}

