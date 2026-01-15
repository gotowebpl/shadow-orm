<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

#[Group('performance')]
final class QueryInterceptorBenchmarkTest extends TestCase
{
    use EntityGeneratorTrait;

    private InMemoryStorageDriver $driver;
    private ShadowRepository $repository;

    protected function setUp(): void
    {
        $this->driver = new InMemoryStorageDriver();
        $schema = new SchemaDefinition('product');
        $this->repository = new ShadowRepository($this->driver, $schema, 'wp_');
    }

    public function testSimpleMetaQueryPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 100;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->repository->findByMeta('_stock_status', 'instock');
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('simple_meta_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(10.0, $result['time']);
    }

    public function testPriceRangeQueryPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 50;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->driver->findByMetaQuery('wp_shadow_product', [
                    ['key' => '_price', 'value' => 100, 'compare' => '>='],
                ]);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('price_range_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(15.0, $result['time']);
    }

    public function testSkuSearchPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 100;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $skuPrefix = 'ABC';
                $this->driver->findByMetaQuery('wp_shadow_product', [
                    ['key' => '_sku', 'value' => $skuPrefix, 'compare' => 'LIKE'],
                ]);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('sku_search', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(10.0, $result['time']);
    }

    public function testMultipleMetaQueryPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 50;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->driver->findByMetaQuery('wp_shadow_product', [
                    ['key' => '_stock_status', 'value' => 'instock', 'compare' => '='],
                    ['key' => '_price', 'value' => 50, 'compare' => '>='],
                ]);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('multi_meta_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(15.0, $result['time']);
    }

    public function testInArrayQueryPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 50;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->driver->findByMetaQuery('wp_shadow_product', [
                    ['key' => '_stock_status', 'value' => ['instock', 'onbackorder'], 'compare' => 'IN'],
                ]);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('in_array_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(15.0, $result['time']);
    }

    public function testExistsQueryPerformance(): void
    {
        $this->seedProducts(5000);

        $iterations = 100;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->driver->findByMetaQuery('wp_shadow_product', [
                    ['key' => '_sale_price', 'value' => null, 'compare' => 'EXISTS'],
                ]);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('exists_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(10.0, $result['time']);
    }

    public function testLargeDatasetQueryPerformance(): void
    {
        $this->seedProducts(20000);

        $iterations = 20;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->repository->findByMeta('_stock_status', 'instock');
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('large_dataset_query', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(30.0, $result['time']);
    }

    private function seedProducts(int $count): void
    {
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }
    }
}
