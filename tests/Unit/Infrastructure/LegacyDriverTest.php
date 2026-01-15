<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Infrastructure\Driver\LegacyDriver;
use Mockery;
use Mockery\MockInterface;
use wpdb;

final class LegacyDriverTest extends TestCase
{
    private wpdb|MockInterface $wpdb;
    private LegacyDriver $driver;

    protected function setUp(): void
    {
        $this->wpdb = Mockery::mock('wpdb');
        $this->driver = new LegacyDriver($this->wpdb);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testInsertWithLookupSync(): void
    {
        $entity = new ShadowEntity(
            postId: 100,
            postType: 'product',
            content: 'Product content',
            metaData: ['price' => 99, 'tags' => ['sale', 'new']],
        );

        $this->wpdb->insert_id = 100;

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->with('wp_shadow_product', Mockery::type('array'), ['%d', '%s', '%s', '%s'])
            ->andReturn(1);

        $this->wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_shadow_product_lookup', ['post_id' => 100], ['%d'])
            ->andReturn(1);

        $this->wpdb->shouldReceive('insert')
            ->times(3)
            ->with('wp_shadow_product_lookup', Mockery::type('array'), ['%d', '%s', '%s'])
            ->andReturn(1);

        $result = $this->driver->insert('wp_shadow_product', $entity);

        $this->assertSame(100, $result);
    }

    public function testDeleteRemovesLookupFirst(): void
    {
        $this->wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_shadow_post_lookup', ['post_id' => 50], ['%d'])
            ->andReturn(1);

        $this->wpdb->shouldReceive('delete')
            ->once()
            ->with('wp_shadow_post', ['post_id' => 50], ['%d'])
            ->andReturn(1);

        $result = $this->driver->delete('wp_shadow_post', 50);

        $this->assertTrue($result);
    }

    public function testFindByMetaQueryWithJoins(): void
    {
        $metaQuery = [
            ['key' => 'color', 'value' => 'red', 'compare' => '='],
            ['key' => 'size', 'value' => 'large', 'compare' => '='],
            'relation' => 'AND',
        ];

        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($sql, ...$args) => vsprintf(str_replace('%s', "'%s'", $sql), $args));

        $this->wpdb->shouldReceive('esc_like')
            ->andReturnUsing(fn($val) => $val);

        $sqlMatched = false;
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->with(Mockery::on(function($sql) use (&$sqlMatched) {
                $sqlMatched = str_contains($sql, 'INNER JOIN') &&
                              str_contains($sql, 'l0') &&
                              str_contains($sql, 'l1');
                return true;
            }), ARRAY_A)
            ->andReturn([]);

        $this->driver->findByMetaQuery('wp_shadow_product', $metaQuery);

        $this->assertTrue($sqlMatched, 'SQL should contain JOINs with aliases l0 and l1');
    }

    public function testSupportsNativeJson(): void
    {
        $this->assertFalse($this->driver->supportsNativeJson());
    }

    public function testGetDriverName(): void
    {
        $this->assertSame('Legacy', $this->driver->getDriverName());
    }

    public function testCreateIndexOnLookupTable(): void
    {
        $this->wpdb->shouldReceive('query')
            ->twice()
            ->andReturn(true);

        $this->driver->createIndex('wp_shadow_product', '$.price', 'price_idx');

        $this->assertTrue(true);
    }
}
