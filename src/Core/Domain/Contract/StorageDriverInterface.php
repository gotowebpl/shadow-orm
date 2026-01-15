<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\Contract;

use ShadowORM\Core\Domain\Entity\ShadowEntity;

interface StorageDriverInterface
{
    public function insert(string $table, ShadowEntity $entity): int;

    public function update(string $table, ShadowEntity $entity): bool;

    public function delete(string $table, int $postId): bool;

    public function findByPostId(string $table, int $postId): ?ShadowEntity;

    /**
     * @param array<string, mixed> $metaQuery
     * @return array<ShadowEntity>
     */
    public function findByMetaQuery(string $table, array $metaQuery): array;

    public function createIndex(string $table, string $jsonPath, string $indexName): void;

    public function dropIndex(string $table, string $indexName): void;

    /**
     * @param array<int> $postIds
     * @return array<int, ShadowEntity>
     */
    public function findMany(string $table, array $postIds): array;

    public function exists(string $table, int $postId): bool;

    public function supportsNativeJson(): bool;

    public function getDriverName(): string;
}
