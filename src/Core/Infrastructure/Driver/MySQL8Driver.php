<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Driver;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use wpdb;

final class MySQL8Driver implements StorageDriverInterface
{
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
                'meta_data' => json_encode($entity->getAllMeta(), JSON_UNESCAPED_UNICODE),
            ],
            ['%d', '%s', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function update(string $table, ShadowEntity $entity): bool
    {
        $result = $this->wpdb->update(
            $table,
            [
                'content' => $entity->content,
                'meta_data' => json_encode($entity->getAllMeta(), JSON_UNESCAPED_UNICODE),
            ],
            ['post_id' => $entity->postId],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function delete(string $table, int $postId): bool
    {
        $result = $this->wpdb->delete($table, ['post_id' => $postId], ['%d']);

        return $result !== false;
    }

    public function findByPostId(string $table, int $postId): ?ShadowEntity
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d",
                $postId
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrateEntity($row);
    }

    public function findByMetaQuery(string $table, array $metaQuery): array
    {
        $where = $this->buildMetaQueryWhere($metaQuery);

        if ($where === '') {
            return [];
        }

        $sql = "SELECT * FROM {$table} WHERE {$where}";
        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_map(fn(array $row) => $this->hydrateEntity($row), $rows);
    }

    public function createIndex(string $table, string $jsonPath, string $indexName): void
    {
        $virtualColumn = $indexName . '_idx';
        
        $this->wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$virtualColumn} VARCHAR(255) 
             GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(meta_data, '{$jsonPath}'))) STORED"
        );

        $this->wpdb->query(
            "CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$virtualColumn})"
        );
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

    private function buildMetaQueryWhere(array $metaQuery): string
    {
        $conditions = [];

        foreach ($metaQuery as $query) {
            if (!isset($query['key'])) {
                continue;
            }

            $key = $query['key'];
            $value = $query['value'] ?? null;
            $compare = strtoupper($query['compare'] ?? '=');

            $jsonPath = '$.' . $key;

            $condition = match ($compare) {
                '=' => $this->wpdb->prepare(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, %s)) = %s",
                    $jsonPath,
                    $value
                ),
                '!=' => $this->wpdb->prepare(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, %s)) != %s",
                    $jsonPath,
                    $value
                ),
                'LIKE' => $this->wpdb->prepare(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, %s)) LIKE %s",
                    $jsonPath,
                    '%' . $this->wpdb->esc_like($value) . '%'
                ),
                'IN' => sprintf(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, '%s')) IN (%s)",
                    $jsonPath,
                    implode(',', array_map(fn($v) => $this->wpdb->prepare('%s', $v), (array) $value))
                ),
                'NOT IN' => sprintf(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, '%s')) NOT IN (%s)",
                    $jsonPath,
                    implode(',', array_map(fn($v) => $this->wpdb->prepare('%s', $v), (array) $value))
                ),
                'EXISTS' => "JSON_CONTAINS_PATH(meta_data, 'one', '{$jsonPath}')",
                'NOT EXISTS' => "NOT JSON_CONTAINS_PATH(meta_data, 'one', '{$jsonPath}')",
                'BETWEEN' => $this->wpdb->prepare(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta_data, %s)) BETWEEN %s AND %s",
                    $jsonPath,
                    $value[0] ?? '',
                    $value[1] ?? ''
                ),
                default => null,
            };

            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        $relation = strtoupper($metaQuery['relation'] ?? 'AND');

        return implode(" {$relation} ", $conditions);
    }

    private function hydrateEntity(array $row): ShadowEntity
    {
        $metaData = json_decode($row['meta_data'] ?? '{}', true) ?: [];

        return new ShadowEntity(
            postId: (int) $row['post_id'],
            postType: (string) ($row['post_type'] ?? ''),
            content: (string) ($row['content'] ?? ''),
            metaData: $metaData,
        );
    }
}
