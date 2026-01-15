<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use Mockery;

final class ShadowRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testSaveInsertNew(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $driver->shouldReceive('findByPostId')
            ->once()
            ->with('wp_shadow_post', 1)
            ->andReturn(null);

        $driver->shouldReceive('insert')
            ->once()
            ->with('wp_shadow_post', Mockery::type(ShadowEntity::class))
            ->andReturn(1);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $entity = new ShadowEntity(postId: 1, postType: 'post');

        $repository->save($entity);

        $this->assertTrue(true);
    }

    public function testSaveUpdateExisting(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $existingEntity = new ShadowEntity(postId: 1, postType: 'post');

        $driver->shouldReceive('findByPostId')
            ->once()
            ->with('wp_shadow_post', 1)
            ->andReturn($existingEntity);

        $driver->shouldReceive('update')
            ->once()
            ->with('wp_shadow_post', Mockery::type(ShadowEntity::class))
            ->andReturn(true);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $entity = new ShadowEntity(postId: 1, postType: 'post', content: 'Updated');

        $repository->save($entity);

        $this->assertTrue(true);
    }

    public function testFind(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('product');

        $entity = new ShadowEntity(postId: 42, postType: 'product', content: 'Product');

        $driver->shouldReceive('findByPostId')
            ->once()
            ->with('wp_shadow_product', 42)
            ->andReturn($entity);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $result = $repository->find(42);

        $this->assertSame($entity, $result);
    }

    public function testFindReturnsNull(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $driver->shouldReceive('findByPostId')
            ->once()
            ->andReturn(null);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $result = $repository->find(999);

        $this->assertNull($result);
    }

    public function testRemove(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $driver->shouldReceive('delete')
            ->once()
            ->with('wp_shadow_post', 123)
            ->andReturn(true);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $repository->remove(123);

        $this->assertTrue(true);
    }

    public function testFindByMeta(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('product');

        $entities = [
            new ShadowEntity(postId: 1, postType: 'product'),
            new ShadowEntity(postId: 2, postType: 'product'),
        ];

        $driver->shouldReceive('findByMetaQuery')
            ->once()
            ->with('wp_shadow_product', [
                ['key' => 'color', 'value' => 'red', 'compare' => '='],
            ])
            ->andReturn($entities);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $result = $repository->findByMeta('color', 'red');

        $this->assertCount(2, $result);
    }

    public function testFindMany(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $entity1 = new ShadowEntity(postId: 1, postType: 'post');
        $entity3 = new ShadowEntity(postId: 3, postType: 'post');

        $driver->shouldReceive('findByPostId')
            ->with('wp_shadow_post', 1)
            ->andReturn($entity1);

        $driver->shouldReceive('findByPostId')
            ->with('wp_shadow_post', 2)
            ->andReturn(null);

        $driver->shouldReceive('findByPostId')
            ->with('wp_shadow_post', 3)
            ->andReturn($entity3);

        $repository = new ShadowRepository($driver, $schema, 'wp_');
        $result = $repository->findMany([1, 2, 3]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    public function testExists(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('post');

        $driver->shouldReceive('findByPostId')
            ->with('wp_shadow_post', 1)
            ->andReturn(new ShadowEntity(postId: 1, postType: 'post'));

        $driver->shouldReceive('findByPostId')
            ->with('wp_shadow_post', 2)
            ->andReturn(null);

        $repository = new ShadowRepository($driver, $schema, 'wp_');

        $this->assertTrue($repository->exists(1));
        $this->assertFalse($repository->exists(2));
    }

    public function testGetTable(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $schema = new SchemaDefinition('product');

        $repository = new ShadowRepository($driver, $schema, 'custom_');

        $this->assertSame('custom_shadow_product', $repository->getTable());
    }
}
