<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use WP_Post_Type;

final class AutoDiscoveryService
{
    private static array $registeredTypes = [];

    public function __construct(
        private readonly ShadowTableManager $tableManager,
        private readonly array $config = [],
    ) {
    }

    public static function onPostTypeRegistered(string $postType, WP_Post_Type $postTypeObject): void
    {
        if (!self::shouldHandle($postType)) {
            return;
        }

        self::$registeredTypes[$postType] = $postTypeObject;
    }

    public function initializeTables(): void
    {
        foreach (self::$registeredTypes as $postType => $postTypeObject) {
            if (!$this->isConfigured($postType)) {
                continue;
            }

            $schema = $this->getSchemaForType($postType);

            if (!$this->tableManager->tableExists($postType)) {
                $this->tableManager->createTable($schema);
            }
        }
    }

    public function getConfiguredTypes(): array
    {
        return array_keys(array_filter(
            self::$registeredTypes,
            fn(string $type) => $this->isConfigured($type),
            ARRAY_FILTER_USE_KEY
        ));
    }

    public function isConfigured(string $postType): bool
    {
        $configuredTypes = $this->config['post_types'] ?? ['post', 'page', 'product'];

        return in_array($postType, $configuredTypes, true);
    }

    public function getSchemaForType(string $postType): SchemaDefinition
    {
        $indexedFields = $this->config['indexed_fields'][$postType] ?? [];
        $virtualColumns = $this->config['virtual_columns'][$postType] ?? [];

        return new SchemaDefinition(
            postType: $postType,
            indexedFields: $indexedFields,
            virtualColumns: $virtualColumns,
        );
    }

    private static function shouldHandle(string $postType): bool
    {
        $excluded = ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];

        return !in_array($postType, $excluded, true);
    }

    public static function getRegisteredTypes(): array
    {
        return self::$registeredTypes;
    }
}
