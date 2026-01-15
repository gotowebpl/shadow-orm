<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\Contract;

use ShadowORM\Core\Domain\Entity\ShadowEntity;

interface ShadowRepositoryInterface
{
    public function save(ShadowEntity $entity): void;

    public function find(int $postId): ?ShadowEntity;

    public function remove(int $postId): void;

    /**
     * @return array<ShadowEntity>
     */
    public function findByMeta(string $key, mixed $value): array;

    /**
     * @param array<int> $postIds
     * @return array<int, ShadowEntity>
     */
    public function findMany(array $postIds): array;

    public function exists(int $postId): bool;
}
