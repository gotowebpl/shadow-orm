<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

use Faker\Factory;
use Faker\Generator;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

trait VariableProductGeneratorTrait
{
    use EntityGeneratorTrait;

    /**
     * Generuje produkt z wariantami (parent + variations)
     * @return array<int, ShadowEntity>
     */
    protected function generateVariableProduct(int $parentId, int $variationCount = 5): array
    {
        $entities = [];
        $faker = $this->getFaker();

        $colors = ['red', 'blue', 'green', 'black', 'white', 'yellow'];
        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

        $parentMeta = [
            '_sku' => strtoupper($faker->lexify('VAR-??????')),
            '_price' => $faker->randomFloat(2, 50, 500),
            '_regular_price' => $faker->randomFloat(2, 50, 500),
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_virtual' => 'no',
            '_downloadable' => 'no',
            '_product_attributes' => [
                'pa_color' => [
                    'name' => 'pa_color',
                    'value' => '',
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1,
                ],
                'pa_size' => [
                    'name' => 'pa_size',
                    'value' => '',
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1,
                ],
            ],
            '_children' => [],
            'total_sales' => $faker->numberBetween(0, 500),
        ];

        $children = [];
        $basePrice = (float) $parentMeta['_regular_price'];

        for ($i = 0; $i < $variationCount; $i++) {
            $variationId = $parentId + $i + 1;
            $children[] = $variationId;

            $variationPrice = $basePrice + $faker->randomFloat(2, -20, 50);

            $variationMeta = [
                '_sku' => $parentMeta['_sku'] . '-' . ($i + 1),
                '_price' => $variationPrice,
                '_regular_price' => $variationPrice,
                '_sale_price' => $faker->optional(0.3)->randomFloat(2, $variationPrice * 0.7, $variationPrice * 0.95),
                '_stock' => $faker->numberBetween(0, 100),
                '_stock_status' => $faker->randomElement(['instock', 'outofstock']),
                '_manage_stock' => 'yes',
                '_virtual' => 'no',
                '_downloadable' => 'no',
                '_variation_description' => $faker->sentence(),
                'attribute_pa_color' => $colors[array_rand($colors)],
                'attribute_pa_size' => $sizes[array_rand($sizes)],
                '_thumbnail_id' => $faker->numberBetween(100, 999),
            ];

            $entities[$variationId] = new ShadowEntity(
                postId: $variationId,
                postType: 'product_variation',
                content: '',
                metaData: $variationMeta,
            );
        }

        $parentMeta['_children'] = $children;
        $entities[$parentId] = new ShadowEntity(
            postId: $parentId,
            postType: 'product',
            content: $faker->paragraphs(2, true),
            metaData: $parentMeta,
        );

        return $entities;
    }

    /**
     * Generuje wiele produktów z wariantami
     * @return array<int, ShadowEntity>
     */
    protected function generateVariableProducts(int $count, int $variationsPerProduct = 5, int $startId = 1): array
    {
        $entities = [];
        $currentId = $startId;

        for ($i = 0; $i < $count; $i++) {
            $productEntities = $this->generateVariableProduct($currentId, $variationsPerProduct);
            $entities += $productEntities;
            $currentId += $variationsPerProduct + 1;
        }

        return $entities;
    }
}
