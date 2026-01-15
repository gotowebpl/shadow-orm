<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\ValueObject;

final readonly class SchemaDefinition
{
    /**
     * @param array<string> $indexedFields
     * @param array<string, string> $virtualColumns
     */
    public function __construct(
        public string $postType,
        public array $indexedFields = [],
        public array $virtualColumns = [],
    ) {
    }

    public function getTableName(string $prefix = 'wp_'): string
    {
        // Sanitize post type name for SQL table (replace hyphens with underscores)
        $sanitizedType = str_replace('-', '_', $this->postType);
        return $prefix . 'shadow_' . $sanitizedType;
    }

    public function getLookupTableName(string $prefix = 'wp_'): string
    {
        return $this->getTableName($prefix) . '_lookup';
    }

    public function hasIndexedField(string $field): bool
    {
        return in_array($field, $this->indexedFields, true);
    }

    public function hasVirtualColumn(string $column): bool
    {
        return array_key_exists($column, $this->virtualColumns);
    }

    public function withIndexedField(string $field): self
    {
        if ($this->hasIndexedField($field)) {
            return $this;
        }

        return new self(
            postType: $this->postType,
            indexedFields: [...$this->indexedFields, $field],
            virtualColumns: $this->virtualColumns,
        );
    }

    public function withVirtualColumn(string $column, string $jsonPath): self
    {
        return new self(
            postType: $this->postType,
            indexedFields: $this->indexedFields,
            virtualColumns: [...$this->virtualColumns, $column => $jsonPath],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'post_type' => $this->postType,
            'indexed_fields' => $this->indexedFields,
            'virtual_columns' => $this->virtualColumns,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            postType: (string) ($data['post_type'] ?? ''),
            indexedFields: (array) ($data['indexed_fields'] ?? []),
            virtualColumns: (array) ($data['virtual_columns'] ?? []),
        );
    }
}
