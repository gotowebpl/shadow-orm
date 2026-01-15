<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Domain\ValueObject\SchemaDefinition;
use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Persistence\ShadowRepository;
use ShadowORM\Core\Infrastructure\Persistence\ShadowTableManager;
use WP_Query;

final class PostQueryPreloader
{
    private static ?DriverFactory $factory = null;
    private static ?ShadowTableManager $tableManager = null;
    private static array $repositories = [];

    /** @var array<string, bool> Cache for table existence */
    private static array $tableExistsCache = [];

    public static function preload(array $posts, WP_Query $query): array
    {
        if (empty($posts) || !self::shouldPreload($query)) {
            return $posts;
        }

        $postType = self::getPostType($posts);

        if (!SupportedTypes::isSupported($postType)) {
            return $posts;
        }

        if (!self::hasTable($postType)) {
            return $posts;
        }

        $postIds = array_map(static fn($post) => $post->ID, $posts);

        ReadInterceptor::preloadEntities($postIds, $postType);

        return $posts;
    }

    private static function shouldPreload(WP_Query $query): bool
    {
        // Enable preloading for admin screens too (lists, search, filters)
        // Only skip for single post edit screens
        if ($query->is_admin()) {
            if (self::isEditScreen()) {
                return false;
            }
            return true;
        }

        // Enable for AJAX
        if (defined('DOING_AJAX')) {
            return true;
        }

        return true;
    }

    private static function isEditScreen(): bool
    {
        global $pagenow;

        if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
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

    private static function getPostType(array $posts): string
    {
        return $posts[0]->post_type ?? 'post';
    }

    private static function getTableManager(): ShadowTableManager
    {
        if (self::$tableManager === null) {
            global $wpdb;
            self::$tableManager = new ShadowTableManager($wpdb, self::getFactory());
        }

        return self::$tableManager;
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
