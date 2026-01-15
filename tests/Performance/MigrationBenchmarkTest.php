<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

#[Group('performance')]
final class MigrationBenchmarkTest extends TestCase
{
    use EntityGeneratorTrait;

    private InMemoryStorageDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new InMemoryStorageDriver();
    }

    public function testMigratePosts1000(): void
    {
        $this->runMigrationBenchmark('post', 1000);
    }

    public function testMigratePosts5000(): void
    {
        $this->runMigrationBenchmark('post', 5000);
    }

    public function testMigrateProducts1000(): void
    {
        $this->runMigrationBenchmark('product', 1000);
    }

    public function testMigrateProducts5000(): void
    {
        $this->runMigrationBenchmark('product', 5000);
    }

    public function testMigratePages1000(): void
    {
        $this->runMigrationBenchmark('page', 1000);
    }

    public function testBatchMigrationPerformance(): void
    {
        $batchSizes = [100, 250, 500, 1000];
        $totalEntities = 5000;

        $results = [];

        foreach ($batchSizes as $batchSize) {
            $this->driver->clear();
            $schema = new SchemaDefinition('product');
            $repository = new ShadowRepository($this->driver, $schema, 'wp_');
            $entities = $this->generateEntities($totalEntities, 'product');

            $entityBatches = array_chunk($entities, $batchSize, true);

            $result = BenchmarkResultFormatter::measure(function () use ($entityBatches, $repository) {
                foreach ($entityBatches as $batch) {
                    foreach ($batch as $entity) {
                        $repository->save($entity);
                    }
                }
            });

            $results["batch_{$batchSize}"] = [
                'time' => $result['time'],
                'memory' => $result['memory'],
                'count' => $totalEntities,
            ];
        }

        fwrite(STDOUT, BenchmarkResultFormatter::format($results));

        foreach ($results as $data) {
            $this->assertLessThan(30.0, $data['time']);
        }
    }

    public function testMigrationWithCachePerformance(): void
    {
        $count = 5000;
        $schema = new SchemaDefinition('product');
        $repository = new ShadowRepository($this->driver, $schema, 'wp_');
        $cache = new RuntimeCache();
        $entities = $this->generateEntities($count, 'product');

        $result = BenchmarkResultFormatter::measure(function () use ($entities, $repository, $cache) {
            foreach ($entities as $postId => $entity) {
                $repository->save($entity);
                $cache->set($postId, $entity);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('migration_with_cache', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertSame($count, $this->driver->count('wp_shadow_product'));
        $this->assertLessThan(15.0, $result['time']);
    }

    public function testRollbackPerformance(): void
    {
        $count = 5000;
        $schema = new SchemaDefinition('product');
        $repository = new ShadowRepository($this->driver, $schema, 'wp_');
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $entity) {
            $repository->save($entity);
        }

        $this->assertSame($count, $this->driver->count('wp_shadow_product'));

        $result = BenchmarkResultFormatter::measure(function () use ($repository, $count) {
            for ($i = 1; $i <= $count; $i++) {
                $repository->remove($i);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('rollback', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertSame(0, $this->driver->count('wp_shadow_product'));
        $this->assertLessThan(5.0, $result['time']);
    }

    public function testIncrementalSyncPerformance(): void
    {
        $initialCount = 5000;
        $incrementCount = 1000;
        $schema = new SchemaDefinition('product');
        $repository = new ShadowRepository($this->driver, $schema, 'wp_');

        $initialEntities = $this->generateEntities($initialCount, 'product');
        foreach ($initialEntities as $entity) {
            $repository->save($entity);
        }

        $newEntities = $this->generateEntities($incrementCount, 'product', $initialCount + 1);

        $result = BenchmarkResultFormatter::measure(function () use ($newEntities, $repository) {
            foreach ($newEntities as $entity) {
                $repository->save($entity);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('incremental_sync', $result['time'], $result['memory'], $incrementCount);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertSame($initialCount + $incrementCount, $this->driver->count('wp_shadow_product'));
        $this->assertLessThan(5.0, $result['time']);
    }

    private function runMigrationBenchmark(string $postType, int $count): void
    {
        $this->driver->clear();
        $schema = new SchemaDefinition($postType);
        $repository = new ShadowRepository($this->driver, $schema, 'wp_');
        $entities = $this->generateEntities($count, $postType);

        $result = BenchmarkResultFormatter::measure(function () use ($entities, $repository) {
            foreach ($entities as $entity) {
                $repository->save($entity);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle("migrate_{$postType}_{$count}", $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertSame($count, $this->driver->count("wp_shadow_{$postType}"));
        $this->assertLessThan($count * 0.01, $result['time']);
    }
}
