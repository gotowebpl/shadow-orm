<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class ShadowEntityTest extends TestCase
{
    public function testCreateEntity(): void
    {
        $entity = new ShadowEntity(
            postId: 123,
            postType: 'product',
            content: '<p>Test content</p>',
            metaData: ['price' => 99.99, 'sku' => 'TEST-001'],
        );

        $this->assertSame(123, $entity->postId);
        $this->assertSame('product', $entity->postType);
        $this->assertSame('<p>Test content</p>', $entity->content);
    }

    public function testGetMeta(): void
    {
        $entity = new ShadowEntity(
            postId: 1,
            postType: 'post',
            metaData: ['key1' => 'value1', 'key2' => 123],
        );

        $this->assertSame('value1', $entity->getMeta('key1'));
        $this->assertSame(123, $entity->getMeta('key2'));
        $this->assertNull($entity->getMeta('nonexistent'));
        $this->assertSame('default', $entity->getMeta('nonexistent', 'default'));
    }

    public function testSetMetaReturnsNewInstance(): void
    {
        $entity = new ShadowEntity(postId: 1, postType: 'post', metaData: ['key1' => 'value1']);
        
        $newEntity = $entity->setMeta('key2', 'value2');

        $this->assertNotSame($entity, $newEntity);
        $this->assertNull($entity->getMeta('key2'));
        $this->assertSame('value2', $newEntity->getMeta('key2'));
    }

    public function testHasMeta(): void
    {
        $entity = new ShadowEntity(postId: 1, postType: 'post', metaData: ['exists' => null]);

        $this->assertTrue($entity->hasMeta('exists'));
        $this->assertFalse($entity->hasMeta('not_exists'));
    }

    public function testRemoveMeta(): void
    {
        $entity = new ShadowEntity(postId: 1, postType: 'post', metaData: ['key1' => 'value1', 'key2' => 'value2']);
        
        $newEntity = $entity->removeMeta('key1');

        $this->assertTrue($entity->hasMeta('key1'));
        $this->assertFalse($newEntity->hasMeta('key1'));
        $this->assertTrue($newEntity->hasMeta('key2'));
    }

    public function testGetAllMeta(): void
    {
        $meta = ['key1' => 'value1', 'key2' => ['nested' => 'value']];
        $entity = new ShadowEntity(postId: 1, postType: 'post', metaData: $meta);

        $this->assertSame($meta, $entity->getAllMeta());
    }

    public function testToArray(): void
    {
        $entity = new ShadowEntity(
            postId: 42,
            postType: 'page',
            content: 'Content',
            metaData: ['test' => 'value'],
        );

        $array = $entity->toArray();

        $this->assertSame(42, $array['post_id']);
        $this->assertSame('page', $array['post_type']);
        $this->assertSame('Content', $array['content']);
        $this->assertSame(['test' => 'value'], $array['meta_data']);
    }

    public function testFromArray(): void
    {
        $data = [
            'post_id' => 99,
            'post_type' => 'product',
            'content' => 'Product description',
            'meta_data' => ['price' => 49.99],
        ];

        $entity = ShadowEntity::fromArray($data);

        $this->assertSame(99, $entity->postId);
        $this->assertSame('product', $entity->postType);
        $this->assertSame('Product description', $entity->content);
        $this->assertSame(49.99, $entity->getMeta('price'));
    }

    public function testFromArrayWithMissingFields(): void
    {
        $entity = ShadowEntity::fromArray([]);

        $this->assertSame(0, $entity->postId);
        $this->assertSame('', $entity->postType);
        $this->assertSame('', $entity->content);
        $this->assertSame([], $entity->getAllMeta());
    }
}
