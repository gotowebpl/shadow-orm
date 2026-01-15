<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Infrastructure\Driver\MySQL8Driver;
use Mockery;
use Mockery\MockInterface;
use wpdb;

final class MySQL8DriverTest extends TestCase
{
    private wpdb|MockInterface $wpdb;
    private MySQL8Driver $driver;

    protected function setUp(): void
    {
        $this->wpdb = Mockery::mock('wpdb');
        $this->driver = new MySQL8Driver($this->wpdb);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testInsertSuccess(): void
    {
        $entity = new ShadowEntity(
            postId: 123,
            postType: 'post',
            content: 'Test content',
            metaData: ['key1' => 'value1'],
        );

        $this->wpdb->insert_id = 123;
        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with(
                'wp_shadow_post',
                Mockery::on(fn($data) => 
                    $data['post_id'] === 123 &&
                    $data['post_type'] === 'post' &&
                    $data['content'] === 'Test content' &&
                    json_decode($data['meta_data'], true) === ['key1' => 'value1']
                ),
                ['%d', '%s', '%s', '%s']
            )
            ->andReturn(1);

        $result = $this->driver->insert('wp_shadow_post', $entity);

        $this->assertSame(123, $result);
    }

    public function testUpdateSuccess(): void
    {
        $entity = new ShadowEntity(
            postId: 456,
            postType: 'page',
            content: 'Updated content',
            metaData: ['updated' => true],
        );

        $this->wpdb->shouldReceive('update')
            ->once()
            ->with(
                'wp_shadow_page',
                Mockery::type('array'),
                ['post_id' => 456],
                ['%s', '%s'],
                ['%d']
            )
            ->andReturn(1);

        $result = $this->driver->update('wp_shadow_page', $entity);

        $this->assertTrue($result);
    }

    public function testUpdateFailure(): void
    {
        $entity = new ShadowEntity(postId: 999, postType: 'post');

        $this->wpdb->shouldReceive('update')
            ->once()
            ->andReturn(false);

        $result = $this->driver->update('wp_shadow_post', $entity);

        $this->assertFalse($result);
    }

    public function testDeleteSuccess(): void
    {
        $this->wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_shadow_post', ['post_id' => 123], ['%d'])
            ->andReturn(1);

        $result = $this->driver->delete('wp_shadow_post', 123);

        $this->assertTrue($result);
    }

    public function testFindByPostIdSuccess(): void
    {
        $row = [
            'post_id' => 42,
            'post_type' => 'product',
            'content' => 'Product description',
            'meta_data' => '{"price": 99.99, "sku": "TEST-001"}',
        ];

        $this->wpdb->shouldReceive('prepare')
            ->once()
            ->andReturn('SELECT * FROM wp_shadow_product WHERE post_id = 42');

        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn($row);

        $entity = $this->driver->findByPostId('wp_shadow_product', 42);

        $this->assertInstanceOf(ShadowEntity::class, $entity);
        $this->assertSame(42, $entity->postId);
        $this->assertSame('product', $entity->postType);
        $this->assertSame(99.99, $entity->getMeta('price'));
        $this->assertSame('TEST-001', $entity->getMeta('sku'));
    }

    public function testFindByPostIdNotFound(): void
    {
        $this->wpdb->shouldReceive('prepare')->once()->andReturn('SELECT...');
        $this->wpdb->shouldReceive('get_row')->once()->andReturn(null);

        $entity = $this->driver->findByPostId('wp_shadow_post', 999);

        $this->assertNull($entity);
    }

    public function testSupportsNativeJson(): void
    {
        $this->assertTrue($this->driver->supportsNativeJson());
    }

    public function testGetDriverName(): void
    {
        $this->assertSame('MySQL8', $this->driver->getDriverName());
    }

    #[DataProvider('metaQueryProvider')]
    public function testFindByMetaQuery(array $metaQuery, string $expectedCondition): void
    {
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($sql, ...$args) => vsprintf(str_replace(['%s', '%d', '%f'], ["'%s'", '%d', '%f'], $sql), $args));

        $this->wpdb->shouldReceive('esc_like')
            ->andReturnUsing(fn($val) => $val);

        $this->wpdb->shouldReceive('_real_escape')
            ->andReturnUsing(fn($val) => addslashes((string) $val));

        $sqlMatched = false;
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->with(Mockery::on(function($sql) use ($expectedCondition, &$sqlMatched) {
                $sqlMatched = str_contains($sql, $expectedCondition);
                return true;
            }), ARRAY_A)
            ->andReturn([]);

        $this->driver->findByMetaQuery('wp_shadow_post', $metaQuery);

        $this->assertTrue($sqlMatched, "SQL should contain: {$expectedCondition}");
    }

    public static function metaQueryProvider(): array
    {
        return [
            'equals' => [
                [['key' => 'status', 'value' => 'active', 'compare' => '=']],
                "meta_data->>",
            ],
            'not_equals' => [
                [['key' => 'status', 'value' => 'inactive', 'compare' => '!=']],
                "!=",
            ],
            'like' => [
                [['key' => 'title', 'value' => 'test', 'compare' => 'LIKE']],
                "LIKE",
            ],
            'exists' => [
                [['key' => 'featured', 'compare' => 'EXISTS']],
                "JSON_CONTAINS_PATH",
            ],
        ];
    }
}
