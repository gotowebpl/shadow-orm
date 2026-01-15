<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

#[Group('performance')]
final class ShadowRepositoryBenchmarkTest extends TestCase
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

    public function testInsertPerformance1000(): void
    {
        $this->runInsertBenchmark(1000);
    }

    public function testInsertPerformance5000(): void
    {
        $this->runInsertBenchmark(5000);
    }

    public function testInsertPerformance10000(): void
    {
        $this->runInsertBenchmark(10000);
    }

    public function testFindPerformance(): void
    {
        $count = 1000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }

        $result = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $this->repository->find($i);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('find', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(5.0, $result['time']);
    }

    public function testFindManyPerformance100(): void
    {
        $this->runFindManyBenchmark(100, 1000);
    }

    public function testFindManyPerformance500(): void
    {
        $this->runFindManyBenchmark(500, 2000);
    }

    public function testFindManyPerformance1000(): void
    {
        $this->runFindManyBenchmark(1000, 5000);
    }

    public function testFindByMetaPerformance(): void
    {
        $count = 5000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }

        $iterations = 100;
        $result = BenchmarkResultFormatter::measure(function () use ($iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->repository->findByMeta('_stock_status', 'instock');
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('findByMeta', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(10.0, $result['time']);
    }

    public function testUpdatePerformance(): void
    {
        $count = 1000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }

        $result = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $entity) {
                $updated = $entity->setMeta('_price', 99.99);
                $this->repository->save($updated);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('update', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(5.0, $result['time']);
    }

    public function testDeletePerformance(): void
    {
        $count = 1000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }

        $result = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $this->repository->remove($i);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('delete', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(2.0, $result['time']);
    }

    public function testMixedOperationsPerformance(): void
    {
        $count = 1000;

        $result = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $entity = $this->generateEntity($i, 'product');
                $this->repository->save($entity);
                $this->repository->find($i);
                $updated = $entity->setMeta('updated_at', time());
                $this->repository->save($updated);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('mixed_ops', $result['time'], $result['memory'], $count * 3);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(15.0, $result['time']);
    }

    private function runInsertBenchmark(int $count): void
    {
        $entities = $this->generateEntities($count, 'product');

        $result = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $entity) {
                $this->repository->save($entity);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle("insert_{$count}", $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertSame($count, $this->driver->count('wp_shadow_product'));
        $this->assertLessThan($count * 0.01, $result['time']);
    }

    private function runFindManyBenchmark(int $batchSize, int $totalEntities): void
    {
        $entities = $this->generateEntities($totalEntities, 'product');

        foreach ($entities as $entity) {
            $this->repository->save($entity);
        }

        $postIds = $this->generatePostIds($batchSize);
        $iterations = 100;

        $result = BenchmarkResultFormatter::measure(function () use ($postIds, $iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->repository->findMany($postIds);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle("findMany_{$batchSize}", $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(5.0, $result['time']);
    }
}
