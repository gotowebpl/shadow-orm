<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;

final class SchemaDefinitionTest extends TestCase
{
    public function testGetTableName(): void
    {
        $schema = new SchemaDefinition('product');

        $this->assertSame('wp_shadow_product', $schema->getTableName());
        $this->assertSame('custom_shadow_product', $schema->getTableName('custom_'));
    }

    public function testGetLookupTableName(): void
    {
        $schema = new SchemaDefinition('post');

        $this->assertSame('wp_shadow_post_lookup', $schema->getLookupTableName());
    }

    public function testHasIndexedField(): void
    {
        $schema = new SchemaDefinition('product', indexedFields: ['price', 'sku']);

        $this->assertTrue($schema->hasIndexedField('price'));
        $this->assertTrue($schema->hasIndexedField('sku'));
        $this->assertFalse($schema->hasIndexedField('name'));
    }

    public function testHasVirtualColumn(): void
    {
        $schema = new SchemaDefinition('product', virtualColumns: ['price_idx' => '$.price']);

        $this->assertTrue($schema->hasVirtualColumn('price_idx'));
        $this->assertFalse($schema->hasVirtualColumn('sku_idx'));
    }

    public function testWithIndexedField(): void
    {
        $schema = new SchemaDefinition('product', indexedFields: ['price']);
        
        $newSchema = $schema->withIndexedField('sku');

        $this->assertNotSame($schema, $newSchema);
        $this->assertFalse($schema->hasIndexedField('sku'));
        $this->assertTrue($newSchema->hasIndexedField('sku'));
        $this->assertTrue($newSchema->hasIndexedField('price'));
    }

    public function testWithIndexedFieldDoesNotDuplicate(): void
    {
        $schema = new SchemaDefinition('product', indexedFields: ['price']);
        
        $sameSchema = $schema->withIndexedField('price');

        $this->assertSame($schema, $sameSchema);
    }

    public function testWithVirtualColumn(): void
    {
        $schema = new SchemaDefinition('product');
        
        $newSchema = $schema->withVirtualColumn('price_idx', '$.price');

        $this->assertFalse($schema->hasVirtualColumn('price_idx'));
        $this->assertTrue($newSchema->hasVirtualColumn('price_idx'));
        $this->assertSame('$.price', $newSchema->virtualColumns['price_idx']);
    }

    public function testToArray(): void
    {
        $schema = new SchemaDefinition(
            postType: 'product',
            indexedFields: ['price'],
            virtualColumns: ['price_idx' => '$.price'],
        );

        $array = $schema->toArray();

        $this->assertSame('product', $array['post_type']);
        $this->assertSame(['price'], $array['indexed_fields']);
        $this->assertSame(['price_idx' => '$.price'], $array['virtual_columns']);
    }

    public function testFromArray(): void
    {
        $data = [
            'post_type' => 'page',
            'indexed_fields' => ['field1', 'field2'],
            'virtual_columns' => ['col1' => '$.path'],
        ];

        $schema = SchemaDefinition::fromArray($data);

        $this->assertSame('page', $schema->postType);
        $this->assertTrue($schema->hasIndexedField('field1'));
        $this->assertTrue($schema->hasVirtualColumn('col1'));
    }
}
