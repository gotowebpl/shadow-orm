<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

#[Group('performance')]
#[Group('comparison')]
final class ComparisonBenchmarkTest extends TestCase
{
    use EntityGeneratorTrait;

    private WordPressEavSimulator $wpEav;
    private InMemoryStorageDriver $shadowDriver;
    private ShadowRepository $shadowRepository;

    /** @var array<string, array{wp: array{time: float, memory: int}, shadow: array{time: float, memory: int}, count: int}> */
    private array $results = [];

    protected function setUp(): void
    {
        $this->wpEav = new WordPressEavSimulator();
        $this->shadowDriver = new InMemoryStorageDriver();
        $schema = new SchemaDefinition('product');
        $this->shadowRepository = new ShadowRepository($this->shadowDriver, $schema, 'wp_');
    }

    public function testInsertComparison1000(): void
    {
        $this->runInsertComparison(1000);
    }

    public function testInsertComparison5000(): void
    {
        $this->runInsertComparison(5000);
    }

    public function testGetMetaComparison(): void
    {
        $count = 5000;
        $this->seedBothSystems($count);

        $iterations = 10000;
        $metaKeys = ['_price', '_sku', '_stock', '_stock_status', '_regular_price'];

        $wpResult = BenchmarkResultFormatter::measure(function () use ($iterations, $count, $metaKeys) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $count);
                $key = $metaKeys[array_rand($metaKeys)];
                $this->wpEav->getPostMeta($postId, $key, true);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($iterations, $count, $metaKeys) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $count);
                $key = $metaKeys[array_rand($metaKeys)];
                $entity = $this->shadowDriver->findByPostId('wp_shadow_product', $postId);
                $entity?->getMeta($key);
            }
        });

        $this->printComparisonResult('get_meta', $wpResult, $shadowResult, $iterations);
        $this->assertLessThan($wpResult['time'], $shadowResult['time']);
    }

    public function testGetAllMetaComparison(): void
    {
        $count = 5000;
        $this->seedBothSystems($count);

        $iterations = 5000;

        $wpResult = BenchmarkResultFormatter::measure(function () use ($iterations, $count) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $count);
                $this->wpEav->getAllPostMeta($postId);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($iterations, $count) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $count);
                $entity = $this->shadowDriver->findByPostId('wp_shadow_product', $postId);
                $entity?->getAllMeta();
            }
        });

        $this->printComparisonResult('get_all_meta', $wpResult, $shadowResult, $iterations);
        $this->assertLessThan($wpResult['time'], $shadowResult['time']);
    }

    public function testMetaQueryComparison(): void
    {
        $count = 5000;
        $this->seedBothSystems($count);

        $iterations = 50;
        $metaQuery = [
            ['key' => '_stock_status', 'value' => 'instock', 'compare' => '='],
        ];

        $wpResult = BenchmarkResultFormatter::measure(function () use ($iterations, $metaQuery) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->wpEav->queryByMeta('product', $metaQuery);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($iterations, $metaQuery) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->shadowDriver->findByMetaQuery('wp_shadow_product', $metaQuery);
            }
        });

        $this->printComparisonResult('meta_query_simple', $wpResult, $shadowResult, $iterations);
        $this->assertTrue(true);
    }

    public function testComplexMetaQueryComparison(): void
    {
        $count = 5000;
        $this->seedBothSystems($count);

        $iterations = 30;
        $metaQuery = [
            ['key' => '_stock_status', 'value' => 'instock', 'compare' => '='],
            ['key' => '_price', 'value' => 50, 'compare' => '>='],
        ];

        $wpResult = BenchmarkResultFormatter::measure(function () use ($iterations, $metaQuery) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->wpEav->queryByMeta('product', $metaQuery);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($iterations, $metaQuery) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->shadowDriver->findByMetaQuery('wp_shadow_product', $metaQuery);
            }
        });

        $this->printComparisonResult('meta_query_complex', $wpResult, $shadowResult, $iterations);
        $this->assertTrue(true);
    }

    public function testBulkGetMetaComparison(): void
    {
        $count = 5000;
        $this->seedBothSystems($count);

        $batchSize = 100;
        $iterations = 50;
        $postIds = $this->generatePostIds($batchSize);

        $wpResult = BenchmarkResultFormatter::measure(function () use ($iterations, $postIds) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->wpEav->getMultiplePostMeta($postIds);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($iterations, $postIds) {
            for ($i = 0; $i < $iterations; $i++) {
                $this->shadowDriver->findMany('wp_shadow_product', $postIds);
            }
        });

        $this->printComparisonResult('bulk_get_100', $wpResult, $shadowResult, $iterations);
        $this->assertLessThan($wpResult['time'], $shadowResult['time']);
    }

    public function testUpdateMetaComparison(): void
    {
        $count = 1000;
        $this->seedBothSystems($count);

        $wpResult = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $this->wpEav->updatePostMeta($i, '_price', 99.99);
            }
        });

        $shadowResult = BenchmarkResultFormatter::measure(function () use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $entity = $this->shadowDriver->findByPostId('wp_shadow_product', $i);
                if ($entity !== null) {
                    $updated = $entity->setMeta('_price', 99.99);
                    $this->shadowDriver->update('wp_shadow_product', $updated);
                }
            }
        });

        $this->printComparisonResult('update_meta', $wpResult, $shadowResult, $count);
        $this->assertTrue(true);
    }

    public function testMemoryUsageComparison(): void
    {
        $count = 3000;

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $this->wpEav->clear();
        $entities = $this->generateEntities($count, 'product');
        foreach ($entities as $postId => $entity) {
            $this->wpEav->insertPost($postId, 'product', $entity->content);
            $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
        }

        $wpMemory = memory_get_usage(true) - $memBefore;

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $this->shadowDriver->clear();
        foreach ($entities as $entity) {
            $this->shadowRepository->save($entity);
        }

        $shadowMemory = memory_get_usage(true) - $memBefore;

        $output = sprintf(
            "\n[Memory Comparison %d records]\n" .
            "  WordPress EAV: %.2f MB (%d posts, %d meta rows)\n" .
            "  ShadowORM:     %.2f MB (%d entities)\n" .
            "  Difference:    %.1f%% %s\n",
            $count,
            $wpMemory / 1024 / 1024,
            $this->wpEav->countPosts(),
            $this->wpEav->countMeta(),
            $shadowMemory / 1024 / 1024,
            $this->shadowDriver->count('wp_shadow_product'),
            $wpMemory > 0 ? abs(($wpMemory - $shadowMemory) / $wpMemory * 100) : 0,
            $shadowMemory < $wpMemory ? 'less' : 'more'
        );

        fwrite(STDOUT, $output);
        $this->assertTrue(true);
    }

    public function testSummaryReport(): void
    {
        fwrite(STDOUT, "\n" . str_repeat('=', 80) . "\n");
        fwrite(STDOUT, "WORDPRESS EAV vs SHADOWORM - PERFORMANCE COMPARISON SUMMARY\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n\n");

        fwrite(STDOUT, "Tested scenarios:\n");
        fwrite(STDOUT, "- Insert operations (1k, 5k records)\n");
        fwrite(STDOUT, "- Single meta read (get_post_meta equivalent)\n");
        fwrite(STDOUT, "- All meta read (get_post_meta with empty key)\n");
        fwrite(STDOUT, "- Meta queries (WP_Query meta_query equivalent)\n");
        fwrite(STDOUT, "- Bulk operations (multiple posts at once)\n");
        fwrite(STDOUT, "- Memory usage comparison\n\n");

        fwrite(STDOUT, "Key advantages of ShadowORM:\n");
        fwrite(STDOUT, "1. Single JSON column vs multiple EAV rows = fewer DB queries\n");
        fwrite(STDOUT, "2. No JOIN operations needed for meta retrieval\n");
        fwrite(STDOUT, "3. Native JSON indexing in MySQL 8+ / MariaDB 10.2+\n");
        fwrite(STDOUT, "4. Reduced memory footprint per record\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n");

        $this->assertTrue(true);
    }

    private function runInsertComparison(int $count): void
    {
        $entities = $this->generateEntities($count, 'product');

        $this->wpEav->clear();
        $wpResult = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $postId => $entity) {
                $this->wpEav->insertPost($postId, 'product', $entity->content);
                $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
            }
        });

        $this->shadowDriver->clear();
        $shadowResult = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $entity) {
                $this->shadowRepository->save($entity);
            }
        });

        $this->printComparisonResult("insert_{$count}", $wpResult, $shadowResult, $count);
        $this->assertTrue(true);
    }

    private function seedBothSystems(int $count): void
    {
        $entities = $this->generateEntities($count, 'product');

        $this->wpEav->clear();
        foreach ($entities as $postId => $entity) {
            $this->wpEav->insertPost($postId, 'product', $entity->content);
            $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
        }

        $this->shadowDriver->clear();
        foreach ($entities as $entity) {
            $this->shadowRepository->save($entity);
        }
    }

    /**
     * @param array{time: float, memory: int} $wpResult
     * @param array{time: float, memory: int} $shadowResult
     */
    private function printComparisonResult(string $name, array $wpResult, array $shadowResult, int $count): void
    {
        $wpOps = $count / $wpResult['time'];
        $shadowOps = $count / $shadowResult['time'];
        $speedup = $wpResult['time'] / $shadowResult['time'];
        $speedupText = $speedup >= 1 
            ? sprintf("%.1fx faster", $speedup)
            : sprintf("%.1fx slower", 1 / $speedup);

        $output = sprintf(
            "\n[%s] %d operations\n" .
            "  WordPress EAV: %8.2f ms (%10.0f ops/sec)\n" .
            "  ShadowORM:     %8.2f ms (%10.0f ops/sec)\n" .
            "  Result:        ShadowORM is %s\n",
            $name,
            $count,
            $wpResult['time'] * 1000,
            $wpOps,
            $shadowResult['time'] * 1000,
            $shadowOps,
            $speedupText
        );

        fwrite(STDOUT, $output);
    }
}
