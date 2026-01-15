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
use WP_Query;

final class QueryInterceptor
{
    private static ?DriverFactory $factory = null;

    public static function intercept(array $clauses, WP_Query $query): array
    {
        if (!self::shouldIntercept($query)) {
            return $clauses;
        }

        $postType = self::getPostType($query);
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
        if ($query->is_admin() && !defined('DOING_AJAX')) {
            return false;
        }

        return SupportedTypes::isSupported(self::getPostType($query));
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
}
