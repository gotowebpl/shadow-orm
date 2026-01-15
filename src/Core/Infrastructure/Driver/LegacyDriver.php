<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Driver;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use wpdb;

final class LegacyDriver implements StorageDriverInterface
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

        $insertId = (int) $this->wpdb->insert_id;

        $this->syncLookupTable($table, $entity);

        return $insertId;
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

        $this->syncLookupTable($table, $entity);

        return $result !== false;
    }

    public function delete(string $table, int $postId): bool
    {
        $lookupTable = $table . '_lookup';
        $this->wpdb->delete($lookupTable, ['post_id' => $postId], ['%d']);

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
        $lookupTable = $table . '_lookup';
        $joins = [];
        $conditions = [];
        $params = [];

        foreach ($metaQuery as $index => $query) {
            if (!isset($query['key'])) {
                continue;
            }

            $alias = "l{$index}";
            $joins[] = "INNER JOIN {$lookupTable} AS {$alias} ON s.post_id = {$alias}.post_id";

            $key = $query['key'];
            $value = $query['value'] ?? null;
            $compare = strtoupper($query['compare'] ?? '=');

            $conditions[] = "{$alias}.meta_key = %s";
            $params[] = $key;

            $valueCondition = match ($compare) {
                '=' => "{$alias}.meta_value = %s",
                '!=' => "{$alias}.meta_value != %s",
                'LIKE' => "{$alias}.meta_value LIKE %s",
                '>' => "{$alias}.meta_value > %s",
                '<' => "{$alias}.meta_value < %s",
                '>=' => "{$alias}.meta_value >= %s",
                '<=' => "{$alias}.meta_value <= %s",
                default => null,
            };

            if ($valueCondition !== null && $value !== null) {
                $conditions[] = $valueCondition;
                $params[] = $compare === 'LIKE' ? '%' . $this->wpdb->esc_like($value) . '%' : $value;
            }
        }

        if (empty($joins)) {
            return [];
        }

        $relation = strtoupper($metaQuery['relation'] ?? 'AND');
        $joinsSql = implode(' ', $joins);
        $whereSql = implode(" {$relation} ", $conditions);

        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT s.* FROM {$table} AS s {$joinsSql} WHERE {$whereSql}",
            ...$params
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_map(fn(array $row) => $this->hydrateEntity($row), $rows);
    }

    public function createIndex(string $table, string $jsonPath, string $indexName): void
    {
        $lookupTable = $table . '_lookup';

        $this->wpdb->query(
            "CREATE INDEX IF NOT EXISTS {$indexName}_key ON {$lookupTable} (meta_key)"
        );

        $this->wpdb->query(
            "CREATE INDEX IF NOT EXISTS {$indexName}_value ON {$lookupTable} (meta_value(191))"
        );
    }

    public function dropIndex(string $table, string $indexName): void
    {
        $lookupTable = $table . '_lookup';

        $this->wpdb->query("DROP INDEX IF EXISTS {$indexName}_key ON {$lookupTable}");
        $this->wpdb->query("DROP INDEX IF EXISTS {$indexName}_value ON {$lookupTable}");
    }

    public function supportsNativeJson(): bool
    {
        return false;
    }

    public function getDriverName(): string
    {
        return 'Legacy';
    }

    private function syncLookupTable(string $table, ShadowEntity $entity): void
    {
        $lookupTable = $table . '_lookup';

        $this->wpdb->delete($lookupTable, ['post_id' => $entity->postId], ['%d']);

        foreach ($entity->getAllMeta() as $key => $value) {
            $this->insertLookupValues($lookupTable, $entity->postId, $key, $value);
        }
    }

    private function insertLookupValues(string $table, int $postId, string $key, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->insertLookupValues($table, $postId, $key, $item);
            }
            return;
        }

        $this->wpdb->insert(
            $table,
            [
                'post_id' => $postId,
                'meta_key' => $key,
                'meta_value' => is_scalar($value) ? (string) $value : json_encode($value),
            ],
            ['%d', '%s', '%s']
        );
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
