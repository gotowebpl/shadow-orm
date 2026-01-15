<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Application\Service\SyncService;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Contract\PostMetaReaderInterface;
use ShadowORM\Core\Domain\Contract\ShadowRepositoryInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class SyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testSyncPostSuccess(): void
    {
        $repository = Mockery::mock(ShadowRepositoryInterface::class);
        $cache = new RuntimeCache();
        $metaReader = Mockery::mock(PostMetaReaderInterface::class);

        $post = new \stdClass();
        $post->ID = 123;
        $post->post_type = 'post';
        $post->post_content = 'Test content';

        Functions\when('get_post')->justReturn($post);
        Functions\when('maybe_unserialize')->returnArg();

        $metaReader->shouldReceive('getPostMeta')
            ->once()
            ->with(123)
            ->andReturn([
                'title' => ['Test Title'],
                'description' => ['Test Description'],
            ]);

        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn(ShadowEntity $entity) =>
                $entity->postId === 123 &&
                $entity->postType === 'post' &&
                $entity->getMeta('title') === 'Test Title' &&
                $entity->getMeta('description') === 'Test Description'
            ));

        $service = new SyncService($repository, $cache, $metaReader);
        $service->syncPost(123);

        $cachedEntity = $cache->get(123);
        $this->assertInstanceOf(ShadowEntity::class, $cachedEntity);
        $this->assertSame(123, $cachedEntity->postId);
    }

    public function testSyncPostWithNullPost(): void
    {
        $repository = Mockery::mock(ShadowRepositoryInterface::class);
        $cache = new RuntimeCache();
        $metaReader = Mockery::mock(PostMetaReaderInterface::class);

        Functions\when('get_post')->justReturn(null);

        $repository->shouldNotReceive('save');
        $metaReader->shouldNotReceive('getPostMeta');

        $service = new SyncService($repository, $cache, $metaReader);
        $service->syncPost(999);

        $this->assertFalse($cache->has(999));
    }

    public function testDeletePost(): void
    {
        $repository = Mockery::mock(ShadowRepositoryInterface::class);
        $cache = new RuntimeCache();
        $metaReader = Mockery::mock(PostMetaReaderInterface::class);
        $cache->set(123, new ShadowEntity(postId: 123, postType: 'post'));

        $repository->shouldReceive('remove')->once()->with(123);

        $service = new SyncService($repository, $cache, $metaReader);
        $service->deletePost(123);

        $this->assertFalse($cache->has(123));
    }

    public function testNormalizeMetaDataIgnoresPrivateKeys(): void
    {
        $repository = Mockery::mock(ShadowRepositoryInterface::class);
        $cache = new RuntimeCache();
        $metaReader = Mockery::mock(PostMetaReaderInterface::class);

        $post = new \stdClass();
        $post->ID = 1;
        $post->post_type = 'post';
        $post->post_content = '';

        Functions\when('get_post')->justReturn($post);
        Functions\when('maybe_unserialize')->returnArg();

        $metaReader->shouldReceive('getPostMeta')
            ->once()
            ->with(1)
            ->andReturn([
                'public_key' => ['public_value'],
                '_edit_lock' => ['12345:1'],
                '_edit_last' => ['1'],
            ]);

        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function(ShadowEntity $entity) {
                return $entity->hasMeta('public_key') &&
                       !$entity->hasMeta('_edit_lock') &&
                       !$entity->hasMeta('_edit_last');
            }));

        $service = new SyncService($repository, $cache, $metaReader);
        $service->syncPost(1);

        $this->assertTrue(true);
    }
}
