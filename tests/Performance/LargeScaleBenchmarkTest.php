<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;

#[Group('performance')]
#[Group('large-scale')]
final class LargeScaleBenchmarkTest extends TestCase
{
    use EntityGeneratorTrait;
    use VariableProductGeneratorTrait;

    private WordPressEavSimulator $wpEav;
    private InMemoryStorageDriver $shadowDriver;

    protected function setUp(): void
    {
        $this->wpEav = new WordPressEavSimulator();
        $this->shadowDriver = new InMemoryStorageDriver();
    }

    public function testPosts10k(): void
    {
        fwrite(STDOUT, "\n" . str_repeat('=', 80) . "\n");
        fwrite(STDOUT, "SUPER TEST: 10,000 POSTS\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n");

        $count = 10000;
        $entities = $this->generateEntities($count, 'post');

        $this->runFullBenchmark('post', $entities, $count);
    }

    public function testProducts10kWithVariations(): void
    {
        fwrite(STDOUT, "\n" . str_repeat('=', 80) . "\n");
        fwrite(STDOUT, "SUPER TEST: 2,000 VARIABLE PRODUCTS (5 VARIATIONS EACH = 12,000 ENTITIES)\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n");

        $productCount = 2000;
        $variationsPerProduct = 5;
        $entities = $this->generateVariableProducts($productCount, $variationsPerProduct);
        $totalEntities = count($entities);

        fwrite(STDOUT, sprintf("\nGenerated %d entities (%d products + %d variations)\n\n", 
            $totalEntities, 
            $productCount, 
            $totalEntities - $productCount
        ));

        $this->runFullBenchmark('product', $entities, $totalEntities);
    }

    public function testMixedWorkload(): void
    {
        fwrite(STDOUT, "\n" . str_repeat('=', 80) . "\n");
        fwrite(STDOUT, "SUPER TEST: MIXED WORKLOAD (2k posts + 1k products with variations)\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n");

        $posts = $this->generateEntities(2000, 'post');
        $products = $this->generateVariableProducts(1000, 5, 2001);
        
        $allEntities = $posts + $products;
        $totalCount = count($allEntities);

        fwrite(STDOUT, sprintf("\nTotal entities: %d\n\n", $totalCount));

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $wpInsertResult = BenchmarkResultFormatter::measure(function () use ($allEntities) {
            foreach ($allEntities as $postId => $entity) {
                $this->wpEav->insertPost($postId, $entity->postType, $entity->content);
                $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
            }
        });

        $schemaPost = new SchemaDefinition('post');
        $schemaProduct = new SchemaDefinition('product');
        $schemaVariation = new SchemaDefinition('product_variation');
        
        $repoPost = new ShadowRepository($this->shadowDriver, $schemaPost, 'wp_');
        $repoProduct = new ShadowRepository($this->shadowDriver, $schemaProduct, 'wp_');
        $repoVariation = new ShadowRepository($this->shadowDriver, $schemaVariation, 'wp_');

        $shadowInsertResult = BenchmarkResultFormatter::measure(function () use ($allEntities, $repoPost, $repoProduct, $repoVariation) {
            foreach ($allEntities as $entity) {
                match ($entity->postType) {
                    'post' => $repoPost->save($entity),
                    'product' => $repoProduct->save($entity),
                    'product_variation' => $repoVariation->save($entity),
                    default => null,
                };
            }
        });

        $memAfter = memory_get_usage(true);

        $this->printResult('INSERT', $wpInsertResult, $shadowInsertResult, $totalCount);
        
        fwrite(STDOUT, sprintf("\nTotal memory used: %.2f MB\n", ($memAfter - $memBefore) / 1024 / 1024));

        $this->assertTrue(true);
    }

    public function testScalabilityProgression(): void
    {
        fwrite(STDOUT, "\n" . str_repeat('=', 80) . "\n");
        fwrite(STDOUT, "SCALABILITY TEST: Performance at different scales\n");
        fwrite(STDOUT, str_repeat('=', 80) . "\n\n");

        $scales = [1000, 2000, 3000, 4000, 5000];
        $results = [];

        foreach ($scales as $scale) {
            $this->wpEav->clear();
            $this->shadowDriver->clear();

            $entities = $this->generateEntities($scale, 'product');

            foreach ($entities as $postId => $entity) {
                $this->wpEav->insertPost($postId, 'product', $entity->content);
                $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
            }

            $schema = new SchemaDefinition('product');
            $repo = new ShadowRepository($this->shadowDriver, $schema, 'wp_');
            foreach ($entities as $entity) {
                $repo->save($entity);
            }

            $iterations = 1000;
            $metaKeys = ['_price', '_sku', '_stock'];

            $wpRead = BenchmarkResultFormatter::measure(function () use ($iterations, $scale, $metaKeys) {
                for ($i = 0; $i < $iterations; $i++) {
                    $postId = random_int(1, $scale);
                    $key = $metaKeys[array_rand($metaKeys)];
                    $this->wpEav->getPostMeta($postId, $key, true);
                }
            });

            $shadowRead = BenchmarkResultFormatter::measure(function () use ($iterations, $scale, $metaKeys) {
                for ($i = 0; $i < $iterations; $i++) {
                    $postId = random_int(1, $scale);
                    $key = $metaKeys[array_rand($metaKeys)];
                    $entity = $this->shadowDriver->findByPostId('wp_shadow_product', $postId);
                    $entity?->getMeta($key);
                }
            });

            $speedup = $wpRead['time'] / $shadowRead['time'];
            $results[$scale] = $speedup;

            fwrite(STDOUT, sprintf(
                "Scale: %5dk | WP: %6.2f ms | Shadow: %6.2f ms | Speedup: %.1fx\n",
                $scale / 1000,
                $wpRead['time'] * 1000,
                $shadowRead['time'] * 1000,
                $speedup
            ));
        }

        fwrite(STDOUT, "\n");
        $this->assertTrue(true);
    }

    private function runFullBenchmark(string $postType, array $entities, int $count): void
    {
        $schema = new SchemaDefinition($postType);
        if ($postType === 'product') {
            $schemaVariation = new SchemaDefinition('product_variation');
        }
        
        $wpInsertResult = BenchmarkResultFormatter::measure(function () use ($entities) {
            foreach ($entities as $postId => $entity) {
                $this->wpEav->insertPost($postId, $entity->postType, $entity->content);
                $this->wpEav->insertPostMeta($postId, $entity->getAllMeta());
            }
        });

        $repo = new ShadowRepository($this->shadowDriver, $schema, 'wp_');
        $repoVariation = isset($schemaVariation) 
            ? new ShadowRepository($this->shadowDriver, $schemaVariation, 'wp_') 
            : null;

        $shadowInsertResult = BenchmarkResultFormatter::measure(function () use ($entities, $repo, $repoVariation) {
            foreach ($entities as $entity) {
                if ($entity->postType === 'product_variation' && $repoVariation !== null) {
                    $repoVariation->save($entity);
                } else {
                    $repo->save($entity);
                }
            }
        });

        $this->printResult('INSERT', $wpInsertResult, $shadowInsertResult, $count);

        $iterations = 5000;
        $metaKeys = ['_price', '_sku', '_stock', '_stock_status'];
        $maxId = max(array_keys($entities));

        $wpReadResult = BenchmarkResultFormatter::measure(function () use ($iterations, $maxId, $metaKeys) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $maxId);
                $key = $metaKeys[array_rand($metaKeys)];
                $this->wpEav->getPostMeta($postId, $key, true);
            }
        });

        $table = $postType === 'product_variation' ? 'wp_shadow_product_variation' : "wp_shadow_{$postType}";
        $shadowReadResult = BenchmarkResultFormatter::measure(function () use ($iterations, $maxId, $metaKeys, $entities) {
            for ($i = 0; $i < $iterations; $i++) {
                $postId = random_int(1, $maxId);
                $key = $metaKeys[array_rand($metaKeys)];
                $entity = $entities[$postId] ?? null;
                if ($entity !== null) {
                    $table = $entity->postType === 'product_variation' 
                        ? 'wp_shadow_product_variation' 
                        : 'wp_shadow_' . $entity->postType;
                    $loaded = $this->shadowDriver->findByPostId($table, $postId);
                    $loaded?->getMeta($key);
                }
            }
        });

        $this->printResult('READ META', $wpReadResult, $shadowReadResult, $iterations);

        $batchSize = 500;
        $batchIterations = 100;
        $postIds = $this->generatePostIds($batchSize);

        $wpBulkResult = BenchmarkResultFormatter::measure(function () use ($batchIterations, $postIds) {
            for ($i = 0; $i < $batchIterations; $i++) {
                $this->wpEav->getMultiplePostMeta($postIds);
            }
        });

        $tableName = "wp_shadow_{$postType}";
        $shadowBulkResult = BenchmarkResultFormatter::measure(function () use ($batchIterations, $postIds, $tableName) {
            for ($i = 0; $i < $batchIterations; $i++) {
                $this->shadowDriver->findMany($tableName, $postIds);
            }
        });

        $this->printResult("BULK READ ({$batchSize} IDs)", $wpBulkResult, $shadowBulkResult, $batchIterations);

        $queryIterations = 30;
        $metaQuery = [['key' => '_stock_status', 'value' => 'instock', 'compare' => '=']];

        $wpQueryResult = BenchmarkResultFormatter::measure(function () use ($queryIterations, $metaQuery, $postType) {
            for ($i = 0; $i < $queryIterations; $i++) {
                $this->wpEav->queryByMeta($postType, $metaQuery);
            }
        });

        $shadowQueryResult = BenchmarkResultFormatter::measure(function () use ($queryIterations, $metaQuery, $tableName) {
            for ($i = 0; $i < $queryIterations; $i++) {
                $this->shadowDriver->findByMetaQuery($tableName, $metaQuery);
            }
        });

        $this->printResult('META QUERY', $wpQueryResult, $shadowQueryResult, $queryIterations);

        $wpMeta = $this->wpEav->countMeta();
        $shadowCount = $this->shadowDriver->count("wp_shadow_{$postType}");
        if ($postType === 'product') {
            $shadowCount += $this->shadowDriver->count('wp_shadow_product_variation');
        }

        fwrite(STDOUT, sprintf(
            "\nSTORAGE: WordPress EAV: %d meta rows | ShadowORM: %d entities\n",
            $wpMeta,
            $shadowCount
        ));

        fwrite(STDOUT, str_repeat('=', 80) . "\n");
        $this->assertTrue(true);
    }

    /**
     * @param array{time: float, memory: int} $wpResult
     * @param array{time: float, memory: int} $shadowResult
     */
    private function printResult(string $operation, array $wpResult, array $shadowResult, int $count): void
    {
        $wpOps = $count / $wpResult['time'];
        $shadowOps = $count / $shadowResult['time'];
        $speedup = $wpResult['time'] / $shadowResult['time'];

        $speedupText = $speedup >= 1 
            ? sprintf("%.1fx FASTER", $speedup)
            : sprintf("%.1fx slower", 1 / $speedup);

        $winner = $speedup >= 1 ? 'SHADOW' : 'WP';

        fwrite(STDOUT, sprintf(
            "\n%-20s | WP: %8.2f ms (%8.0f ops/s) | Shadow: %8.2f ms (%8.0f ops/s) | %s (%s)\n",
            $operation,
            $wpResult['time'] * 1000,
            $wpOps,
            $shadowResult['time'] * 1000,
            $shadowOps,
            $speedupText,
            $winner
        ));
    }
}
