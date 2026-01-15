<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use Faker\Factory;
use Faker\Generator;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

trait EntityGeneratorTrait
{
    private ?Generator $faker = null;

    private function getFaker(): Generator
    {
        return $this->faker ??= Factory::create();
    }

    protected function generateEntity(int $postId, string $postType = 'post'): ShadowEntity
    {
        $metaData = match ($postType) {
            'product' => $this->generateProductMeta(),
            'page' => $this->generatePageMeta(),
            default => $this->generatePostMeta(),
        };

        return new ShadowEntity(
            postId: $postId,
            postType: $postType,
            content: $this->getFaker()->paragraphs(3, true),
            metaData: $metaData,
        );
    }

    /**
     * @return array<int, ShadowEntity>
     */
    protected function generateEntities(int $count, string $postType = 'post', int $startId = 1): array
    {
        $entities = [];

        for ($i = 0; $i < $count; $i++) {
            $postId = $startId + $i;
            $entities[$postId] = $this->generateEntity($postId, $postType);
        }

        return $entities;
    }

    /**
     * @return array<string, mixed>
     */
    protected function generatePostMeta(): array
    {
        $faker = $this->getFaker();

        return [
            '_edit_lock' => time() . ':1',
            '_thumbnail_id' => $faker->numberBetween(100, 999),
            '_wp_page_template' => 'default',
            '_yoast_wpseo_title' => $faker->sentence(6),
            '_yoast_wpseo_metadesc' => $faker->sentence(15),
            'custom_field_1' => $faker->word(),
            'custom_field_2' => $faker->numberBetween(1, 100),
            'custom_field_3' => $faker->boolean(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function generateProductMeta(): array
    {
        $faker = $this->getFaker();
        $regularPrice = $faker->randomFloat(2, 10, 1000);
        $salePrice = $faker->optional(0.3)->randomFloat(2, 5, $regularPrice - 1);

        return [
            '_sku' => strtoupper($faker->lexify('??????-###')),
            '_price' => $salePrice ?? $regularPrice,
            '_regular_price' => $regularPrice,
            '_sale_price' => $salePrice,
            '_stock' => $faker->numberBetween(0, 500),
            '_stock_status' => $faker->randomElement(['instock', 'outofstock', 'onbackorder']),
            '_manage_stock' => $faker->boolean() ? 'yes' : 'no',
            '_weight' => $faker->randomFloat(2, 0.1, 50),
            '_length' => $faker->randomFloat(1, 1, 100),
            '_width' => $faker->randomFloat(1, 1, 100),
            '_height' => $faker->randomFloat(1, 1, 100),
            '_virtual' => 'no',
            '_downloadable' => 'no',
            '_tax_status' => 'taxable',
            '_tax_class' => '',
            '_backorders' => 'no',
            '_sold_individually' => 'no',
            '_product_attributes' => [
                'color' => [
                    'name' => 'Color',
                    'value' => $faker->safeColorName(),
                    'is_visible' => 1,
                ],
                'size' => [
                    'name' => 'Size',
                    'value' => $faker->randomElement(['S', 'M', 'L', 'XL']),
                    'is_visible' => 1,
                ],
            ],
            '_thumbnail_id' => $faker->numberBetween(100, 999),
            'total_sales' => $faker->numberBetween(0, 1000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function generatePageMeta(): array
    {
        $faker = $this->getFaker();

        return [
            '_wp_page_template' => $faker->randomElement(['default', 'full-width', 'sidebar-left']),
            '_thumbnail_id' => $faker->numberBetween(100, 999),
            '_yoast_wpseo_title' => $faker->sentence(6),
            '_yoast_wpseo_metadesc' => $faker->sentence(15),
        ];
    }

    /**
     * @return array<int>
     */
    protected function generatePostIds(int $count, int $startId = 1): array
    {
        return range($startId, $startId + $count - 1);
    }
}
