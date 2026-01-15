<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class RuntimeCacheTest extends TestCase
{
    private RuntimeCache $cache;

    protected function setUp(): void
    {
        $this->cache = new RuntimeCache();
        $this->cache->clear();
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $this->assertNull($this->cache->get(999));
    }

    public function testSetAndGet(): void
    {
        $entity = new ShadowEntity(postId: 1, postType: 'post');
        
        $this->cache->set(1, $entity);

        $this->assertSame($entity, $this->cache->get(1));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has(1));

        $this->cache->set(1, new ShadowEntity(postId: 1, postType: 'post'));

        $this->assertTrue($this->cache->has(1));
    }

    public function testDelete(): void
    {
        $this->cache->set(1, new ShadowEntity(postId: 1, postType: 'post'));
        
        $this->cache->delete(1);

        $this->assertFalse($this->cache->has(1));
    }

    public function testMarkNotFound(): void
    {
        $this->assertFalse($this->cache->isMarkedNotFound(1));

        $this->cache->markNotFound(1);

        $this->assertTrue($this->cache->isMarkedNotFound(1));
    }

    public function testSetClearsNotFoundMark(): void
    {
        $this->cache->markNotFound(1);
        $this->cache->set(1, new ShadowEntity(postId: 1, postType: 'post'));

        $this->assertFalse($this->cache->isMarkedNotFound(1));
    }

    public function testClear(): void
    {
        $this->cache->set(1, new ShadowEntity(postId: 1, postType: 'post'));
        $this->cache->markNotFound(2);

        $this->cache->clear();

        $this->assertFalse($this->cache->has(1));
        $this->assertFalse($this->cache->isMarkedNotFound(2));
    }

    public function testWarmup(): void
    {
        $entities = [
            new ShadowEntity(postId: 1, postType: 'post'),
            new ShadowEntity(postId: 2, postType: 'page'),
            new ShadowEntity(postId: 3, postType: 'product'),
        ];

        $this->cache->warmup($entities);

        $this->assertTrue($this->cache->has(1));
        $this->assertTrue($this->cache->has(2));
        $this->assertTrue($this->cache->has(3));
    }

    public function testGetStats(): void
    {
        $this->cache->set(1, new ShadowEntity(postId: 1, postType: 'post'));
        $this->cache->markNotFound(2);

        $stats = $this->cache->getStats();

        $this->assertSame(1, $stats['cached']);
        $this->assertSame(1, $stats['not_found']);
        $this->assertArrayHasKey('memory', $stats);
    }
}
