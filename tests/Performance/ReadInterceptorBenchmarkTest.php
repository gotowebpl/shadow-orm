<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

#[Group('performance')]
final class ReadInterceptorBenchmarkTest extends TestCase
{
    use EntityGeneratorTrait;

    private RuntimeCache $cache;
    private InMemoryStorageDriver $driver;

    protected function setUp(): void
    {
        $this->cache = new RuntimeCache();
        $this->driver = new InMemoryStorageDriver();
    }

    public function testCacheWritePerformance(): void
    {
        $count = 10000;
        $entities = $this->generateEntities($count, 'product');

        $result = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $postId => $entity) {
                $this->cache->set($postId, $entity);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('cache_write', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(1.0, $result['time']);
    }

    public function testCacheReadPerformance(): void
    {
        $count = 10000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $postId => $entity) {
            $this->cache->set($postId, $entity);
        }

        $result = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $this->cache->get($i);
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('cache_read', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(0.5, $result['time']);
    }

    public function testCacheHitRatioPerformance(): void
    {
        $totalEntities = 5000;
        $entities = $this->generateEntities($totalEntities, 'product');

        foreach (array_slice($entities, 0, (int) ($totalEntities * 0.8), true) as $postId => $entity) {
            $this->cache->set($postId, $entity);
        }

        $iterations = 10000;
        $hits = 0;
        $misses = 0;

        $result = BenchmarkResultFormatter::measure(function () use ($iterations, $totalEntities, &$hits, &$misses) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $totalEntities);
                if ($this->cache->get($postId) !== null) {
                    $hits++;
                } else {
                    $misses++;
                }
            }
        });

        $hitRatio = ($hits / $iterations) * 100;
        $message = BenchmarkResultFormatter::formatSingle('cache_hit_ratio', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . " [hit ratio: " . number_format($hitRatio, 1) . "%]\n");

        $this->assertGreaterThan(70, $hitRatio);
        $this->assertLessThan(1.0, $result['time']);
    }

    public function testPreloadSimulation100(): void
    {
        $this->runPreloadBenchmark(100, 1000);
    }

    public function testPreloadSimulation500(): void
    {
        $this->runPreloadBenchmark(500, 2000);
    }

    public function testPreloadSimulation1000(): void
    {
        $this->runPreloadBenchmark(1000, 5000);
    }

    public function testGetMetaPerformance(): void
    {
        $count = 5000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $postId => $entity) {
            $this->cache->set($postId, $entity);
        }

        $iterations = 10000;
        $metaKeys = ['_price', '_sku', '_stock', '_stock_status', '_regular_price'];

        $result = BenchmarkResultFormatter::measure(function () use ($iterations, $count, $metaKeys) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $count);
                $entity = $this->cache->get($postId);

                if ($entity instanceof ShadowEntity) {
                    $key = $metaKeys[array_rand($metaKeys)];
                    $entity->getMeta($key);
                }
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('get_meta', $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(1.0, $result['time']);
    }

    public function testGetAllMetaPerformance(): void
    {
        $count = 1000;
        $entities = $this->generateEntities($count, 'product');

        foreach ($entities as $postId => $entity) {
            $this->cache->set($postId, $entity);
        }

        $result = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $entity = $this->cache->get($i);
                if ($entity instanceof ShadowEntity) {
                    $entity->getAllMeta();
                }
            }
        });

        $message = BenchmarkResultFormatter::formatSingle('get_all_meta', $result['time'], $result['memory'], $count);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(0.5, $result['time']);
    }

    private function runPreloadBenchmark(int $batchSize, int $totalEntities): void
    {
        $entities = $this->generateEntities($totalEntities, 'product');

        foreach ($entities as $entity) {
            $this->driver->insert('wp_shadow_product', $entity);
        }

        $postIds = $this->generatePostIds($batchSize);
        $iterations = 50;

        $result = BenchmarkResultFormatter::measure(function () use ($postIds, $iterations) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->cache = new RuntimeCache();

                $loaded = $this->driver->findMany('wp_shadow_product', $postIds);
                foreach ($loaded as $postId => $entity) {
                    $this->cache->set($postId, $entity);
                }
            }
        });

        $message = BenchmarkResultFormatter::formatSingle("preload_{$batchSize}", $result['time'], $result['memory'], $iterations);
        fwrite(STDOUT, "\n" . $message . "\n");

        $this->assertLessThan(5.0, $result['time']);
    }
}
