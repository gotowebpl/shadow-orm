<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Application\Service\QueryService;
use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use Mockery;

final class QueryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testTransformClausesWithEmptyMetaQuery(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $driver->shouldReceive('supportsNativeJson')->andReturn(true);

        $schema = new SchemaDefinition('product');
        $service = new QueryService($driver, $schema);

        $clauses = ['join' => '', 'where' => ' AND 1=1', 'groupby' => ''];
        $result = $service->transformClauses($clauses, []);

        $this->assertSame($clauses, $result);
    }

    public function testTransformClausesWithJsonDriver(): void
    {
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($sql, ...$args) => vsprintf(str_replace('%s', "'%s'", $sql), $args));
        $wpdb->shouldReceive('esc_like')
            ->andReturnUsing(fn($val) => $val);

        $driver = Mockery::mock(StorageDriverInterface::class);
        $driver->shouldReceive('supportsNativeJson')->andReturn(true);

        $schema = new SchemaDefinition('product');
        $service = new QueryService($driver, $schema, 'wp_');

        $clauses = ['join' => '', 'where' => ' AND 1=1', 'groupby' => ''];
        $metaQuery = [
            ['key' => 'price', 'value' => '100', 'compare' => '>'],
        ];

        $result = $service->transformClauses($clauses, $metaQuery);

        $this->assertStringContainsString('INNER JOIN', $result['join']);
        $this->assertStringContainsString('shadow', $result['join']);
        $this->assertStringContainsString('JSON_EXTRACT', $result['where']);
    }

    public function testTransformClausesWithLookupDriver(): void
    {
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($sql, ...$args) => vsprintf(str_replace('%s', "'%s'", $sql), $args));
        $wpdb->shouldReceive('esc_like')
            ->andReturnUsing(fn($val) => $val);

        $driver = Mockery::mock(StorageDriverInterface::class);
        $driver->shouldReceive('supportsNativeJson')->andReturn(false);

        $schema = new SchemaDefinition('product');
        $service = new QueryService($driver, $schema, 'wp_');

        $clauses = ['join' => '', 'where' => ' AND 1=1', 'groupby' => ''];
        $metaQuery = [
            ['key' => 'color', 'value' => 'red', 'compare' => '='],
        ];

        $result = $service->transformClauses($clauses, $metaQuery);

        $this->assertStringContainsString('lookup', $result['join']);
        $this->assertStringContainsString('meta_key', $result['where']);
        $this->assertNotEmpty($result['groupby']);
    }

    public function testShouldNotInterceptWithoutValidMetaQuery(): void
    {
        $driver = Mockery::mock(StorageDriverInterface::class);
        $driver->shouldReceive('supportsNativeJson')->andReturn(true);

        $schema = new SchemaDefinition('post');
        $service = new QueryService($driver, $schema);

        $clauses = ['join' => '', 'where' => '', 'groupby' => ''];
        $metaQuery = ['relation' => 'AND'];

        $result = $service->transformClauses($clauses, $metaQuery);

        $this->assertSame($clauses, $result);
    }
}
