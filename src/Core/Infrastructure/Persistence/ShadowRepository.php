<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Persistence;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\Contract\ShadowRepositoryInterface;
use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;

final class ShadowRepository implements ShadowRepositoryInterface
{
    private string $table;

    public function __construct(
        private readonly StorageDriverInterface $driver,
        private readonly SchemaDefinition $schema,
        private readonly string $tablePrefix = 'wp_',
    ) {
        $this->table = $schema->getTableName($tablePrefix);
    }

    public function save(ShadowEntity $entity): void
    {
        if ($this->exists($entity->postId)) {
            $this->driver->update($this->table, $entity);
            return;
        }

        $this->driver->insert($this->table, $entity);
    }

    public function find(int $postId): ?ShadowEntity
    {
        return $this->driver->findByPostId($this->table, $postId);
    }

    public function remove(int $postId): void
    {
        $this->driver->delete($this->table, $postId);
    }

    public function findByMeta(string $key, mixed $value): array
    {
        return $this->driver->findByMetaQuery($this->table, [
            ['key' => $key, 'value' => $value, 'compare' => '='],
        ]);
    }

    /**
     * @param array<int> $postIds
     * @return array<int, ShadowEntity>
     */
    public function findMany(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        return $this->driver->findMany($this->table, $postIds);
    }

    public function exists(int $postId): bool
    {
        return $this->driver->exists($this->table, $postId);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getSchema(): SchemaDefinition
    {
        return $this->schema;
    }
}
