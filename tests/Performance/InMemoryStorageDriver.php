<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class InMemoryStorageDriver implements StorageDriverInterface
{
    /** @var array<string, array<int, ShadowEntity>> */
    private array $storage = [];

    /** @var array<string, array<string, array<int>>> */
    private array $metaIndex = [];

    public function insert(string $table, ShadowEntity $entity): int
    {
        $this->storage[$table][$entity->postId] = $entity;
        $this->indexMeta($table, $entity);

        return $entity->postId;
    }

    public function update(string $table, ShadowEntity $entity): bool
    {
        if (!isset($this->storage[$table][$entity->postId])) {
            return false;
        }

        $this->storage[$table][$entity->postId] = $entity;
        $this->indexMeta($table, $entity);

        return true;
    }

    public function delete(string $table, int $postId): bool
    {
        if (!isset($this->storage[$table][$postId])) {
            return false;
        }

        unset($this->storage[$table][$postId]);

        return true;
    }

    public function findByPostId(string $table, int $postId): ?ShadowEntity
    {
        return $this->storage[$table][$postId] ?? null;
    }

    /**
     * @param array<array{key: string, value: mixed, compare: string}> $metaQuery
     * @return array<ShadowEntity>
     */
    public function findByMetaQuery(string $table, array $metaQuery): array
    {
        if (!isset($this->storage[$table])) {
            return [];
        }

        $results = [];

        foreach ($this->storage[$table] as $entity) {
            if ($this->matchesMetaQuery($entity, $metaQuery)) {
                $results[] = $entity;
            }
        }

        return $results;
    }

    public function createIndex(string $table, string $jsonPath, string $indexName): void
    {
    }

    public function dropIndex(string $table, string $indexName): void
    {
    }

    /**
     * @param array<int> $postIds
     * @return array<int, ShadowEntity>
     */
    public function findMany(string $table, array $postIds): array
    {
        $results = [];

        foreach ($postIds as $postId) {
            if (isset($this->storage[$table][$postId])) {
                $results[$postId] = $this->storage[$table][$postId];
            }
        }

        return $results;
    }

    public function exists(string $table, int $postId): bool
    {
        return isset($this->storage[$table][$postId]);
    }

    public function supportsNativeJson(): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return 'in_memory';
    }

    public function clear(): void
    {
        $this->storage = [];
        $this->metaIndex = [];
    }

    public function count(string $table): int
    {
        return count($this->storage[$table] ?? []);
    }

    private function indexMeta(string $table, ShadowEntity $entity): void
    {
        foreach ($entity->getAllMeta() as $key => $value) {
            $valueKey = $this->normalizeValue($value);
            $this->metaIndex[$table][$key . ':' . $valueKey][] = $entity->postId;
        }
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return md5(serialize($value));
        }

        return (string) $value;
    }

    /**
     * @param array<array{key: string, value: mixed, compare: string}> $metaQuery
     */
    private function matchesMetaQuery(ShadowEntity $entity, array $metaQuery): bool
    {
        foreach ($metaQuery as $query) {
            $key = $query['key'];
            $value = $query['value'];
            $compare = $query['compare'] ?? '=';
            $metaValue = $entity->getMeta($key);

            if (!$this->compareValues($metaValue, $value, $compare)) {
                return false;
            }
        }

        return true;
    }

    private function compareValues(mixed $metaValue, mixed $queryValue, string $compare): bool
    {
        return match ($compare) {
            '=' => $metaValue == $queryValue,
            '!=' => $metaValue != $queryValue,
            '>' => $metaValue > $queryValue,
            '>=' => $metaValue >= $queryValue,
            '<' => $metaValue < $queryValue,
            '<=' => $metaValue <= $queryValue,
            'LIKE' => is_string($metaValue) && str_contains($metaValue, (string) $queryValue),
            'IN' => is_array($queryValue) && in_array($metaValue, $queryValue, false),
            'NOT IN' => is_array($queryValue) && !in_array($metaValue, $queryValue, false),
            'EXISTS' => $metaValue !== null,
            'NOT EXISTS' => $metaValue === null,
            default => false,
        };
    }
}
