<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;

final class QueryService
{
    public function __construct(
        private readonly StorageDriverInterface $driver,
        private readonly SchemaDefinition $schema,
        private readonly string $tablePrefix = 'wp_',
    ) {
    }

    /**
     * @param array<string, mixed> $metaQuery
     * @return array<string, mixed>
     */
    public function transformClauses(array $clauses, array $metaQuery): array
    {
        if (empty($metaQuery) || !$this->shouldIntercept($metaQuery)) {
            return $clauses;
        }

        $table = $this->schema->getTableName($this->tablePrefix);
        $conditions = $this->buildConditions($metaQuery);

        if ($conditions === '') {
            return $clauses;
        }

        if ($this->driver->supportsNativeJson()) {
            return $this->transformClausesJson($clauses, $table, $conditions);
        }

        return $this->transformClausesLookup($clauses, $table, $conditions, $metaQuery);
    }

    private function shouldIntercept(array $metaQuery): bool
    {
        foreach ($metaQuery as $query) {
            if (is_array($query) && isset($query['key'])) {
                return true;
            }
        }

        return false;
    }

    private function buildConditions(array $metaQuery): string
    {
        global $wpdb;

        $conditions = [];

        foreach ($metaQuery as $key => $query) {
            if (!is_array($query) || !isset($query['key'])) {
                continue;
            }

            $metaKey = $query['key'];
            $value = $query['value'] ?? null;
            $compare = strtoupper($query['compare'] ?? '=');

            if ($this->driver->supportsNativeJson()) {
                $condition = $this->buildJsonCondition($metaKey, $value, $compare);
            } else {
                $condition = $this->buildLookupCondition($metaKey, $value, $compare);
            }

            if ($condition !== '') {
                $conditions[] = $condition;
            }
        }

        $relation = strtoupper($metaQuery['relation'] ?? 'AND');

        return implode(" {$relation} ", $conditions);
    }

    private function buildJsonCondition(string $key, mixed $value, string $compare): string
    {
        global $wpdb;

        $jsonPath = '$.' . $key;

        return match ($compare) {
            '=' => $wpdb->prepare(
                "JSON_UNQUOTE(JSON_EXTRACT(shadow.meta_data, %s)) = %s",
                $jsonPath,
                $value
            ),
            '!=' => $wpdb->prepare(
                "JSON_UNQUOTE(JSON_EXTRACT(shadow.meta_data, %s)) != %s",
                $jsonPath,
                $value
            ),
            'LIKE' => $wpdb->prepare(
                "JSON_UNQUOTE(JSON_EXTRACT(shadow.meta_data, %s)) LIKE %s",
                $jsonPath,
                '%' . $wpdb->esc_like($value) . '%'
            ),
            '>' => $wpdb->prepare(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(shadow.meta_data, %s)) AS DECIMAL) > %f",
                $jsonPath,
                $value
            ),
            '<' => $wpdb->prepare(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(shadow.meta_data, %s)) AS DECIMAL) < %f",
                $jsonPath,
                $value
            ),
            'EXISTS' => "JSON_CONTAINS_PATH(shadow.meta_data, 'one', '{$jsonPath}')",
            'NOT EXISTS' => "NOT JSON_CONTAINS_PATH(shadow.meta_data, 'one', '{$jsonPath}')",
            default => '',
        };
    }

    private function buildLookupCondition(string $key, mixed $value, string $compare): string
    {
        global $wpdb;

        return match ($compare) {
            '=' => $wpdb->prepare("lookup.meta_key = %s AND lookup.meta_value = %s", $key, $value),
            '!=' => $wpdb->prepare("lookup.meta_key = %s AND lookup.meta_value != %s", $key, $value),
            'LIKE' => $wpdb->prepare("lookup.meta_key = %s AND lookup.meta_value LIKE %s", $key, '%' . $wpdb->esc_like($value) . '%'),
            '>' => $wpdb->prepare("lookup.meta_key = %s AND CAST(lookup.meta_value AS DECIMAL) > %f", $key, $value),
            '<' => $wpdb->prepare("lookup.meta_key = %s AND CAST(lookup.meta_value AS DECIMAL) < %f", $key, $value),
            default => '',
        };
    }

    private function transformClausesJson(array $clauses, string $table, string $conditions): array
    {
        global $wpdb;

        $clauses['join'] .= " INNER JOIN {$table} AS shadow ON {$wpdb->posts}.ID = shadow.post_id";
        $clauses['where'] .= " AND ({$conditions})";

        return $clauses;
    }

    private function transformClausesLookup(array $clauses, string $table, string $conditions, array $metaQuery): array
    {
        global $wpdb;

        $lookupTable = $table . '_lookup';

        $clauses['join'] .= " INNER JOIN {$table} AS shadow ON {$wpdb->posts}.ID = shadow.post_id";
        $clauses['join'] .= " INNER JOIN {$lookupTable} AS lookup ON shadow.post_id = lookup.post_id";
        $clauses['where'] .= " AND ({$conditions})";
        $clauses['groupby'] = "{$wpdb->posts}.ID";

        return $clauses;
    }
}
