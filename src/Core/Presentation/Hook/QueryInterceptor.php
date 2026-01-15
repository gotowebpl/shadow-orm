<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Application\Service\QueryService;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use WP_Query;

final class QueryInterceptor
{
    private static ?DriverFactory $factory = null;
    private static ?ShadowTableManager $tableManager = null;
    
    /** @var array<string, bool> Cache for table existence check */
    private static array $tableExistsCache = [];

    public static function intercept(array $clauses, WP_Query $query): array
    {
        if (!self::shouldIntercept($query)) {
            return $clauses;
        }

        $postType = self::getPostType($query);
        
        // Fast check: is table available?
        if (!self::hasTable($postType)) {
            return $clauses;
        }
        
        $metaQuery = $query->get('meta_query') ?: [];

        if (empty($metaQuery)) {
            return $clauses;
        }

        global $wpdb;

        $factory = self::getFactory();

        try {
            $driver = $factory->create();
        } catch (\Exception) {
            return $clauses;
        }

        $schema = new SchemaDefinition($postType);
        $queryService = new QueryService($driver, $schema, $wpdb->prefix);

        return $queryService->transformClauses($clauses, $metaQuery);
    }

    private static function shouldIntercept(WP_Query $query): bool
    {
        $postType = self::getPostType($query);
        
        // Check if post type is supported first (fast O(1) check)
        if (!SupportedTypes::isSupported($postType)) {
            return false;
        }

        // Admin support - enabled by default now
        // Only skip for single post edit screens where we need fresh data
        if ($query->is_admin()) {
            // Allow admin queries except for edit screens
            if (self::isEditScreen()) {
                return false;
            }
            // Allow for list screens, search, filters
            return true;
        }

        return true;
    }
    
    private static function isEditScreen(): bool
    {
        global $pagenow;
        
        // Skip on post edit screen
        if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
            return true;
        }
        
        // Skip on quick edit
        if (defined('DOING_AJAX') && isset($_POST['action']) && $_POST['action'] === 'inline-save') {
            return true;
        }
        
        return false;
    }
    
    private static function hasTable(string $postType): bool
    {
        if (isset(self::$tableExistsCache[$postType])) {
            return self::$tableExistsCache[$postType];
        }
        
        $tableManager = self::getTableManager();
        $exists = $tableManager->tableExists($postType);
        self::$tableExistsCache[$postType] = $exists;
        
        return $exists;
    }

    private static function getPostType(WP_Query $query): string
    {
        $postType = $query->get('post_type');

        if (is_array($postType)) {
            return $postType[0] ?? 'post';
        }

        return $postType ?: 'post';
    }

    private static function getFactory(): DriverFactory
    {
        if (self::$factory === null) {
            global $wpdb;
            self::$factory = new DriverFactory($wpdb);
        }

        return self::$factory;
    }
    
    private static function getTableManager(): ShadowTableManager
    {
        if (self::$tableManager === null) {
            global $wpdb;
            self::$tableManager = new ShadowTableManager($wpdb, self::getFactory());
        }

        return self::$tableManager;
    }
}
