<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Hook;

if (!defined('ABSPATH')) {
    exit;
}

use ShadowORM\Core\Domain\ValueObject\SupportedTypes;
use WP_Post;
use WP_Query;

final class PostQueryPreloader
{
    private static bool $enabled = true;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * @param WP_Post[] $posts
     * @return WP_Post[]
     */
    public static function preload(array $posts, WP_Query $query): array
    {
        if (!self::$enabled || empty($posts)) {
            return $posts;
        }

        if ($query->is_admin() && !defined('DOING_AJAX')) {
            return $posts;
        }

        $grouped = self::groupByPostType($posts);

        foreach ($grouped as $postType => $postIds) {
            if (!SupportedTypes::isSupported($postType)) {
                continue;
            }

            ReadInterceptor::preloadEntities($postIds, $postType);
        }

        return $posts;
    }

    /**
     * @param WP_Post[] $posts
     * @return array<string, int[]>
     */
    private static function groupByPostType(array $posts): array
    {
        $grouped = [];

        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $grouped[$post->post_type][] = $post->ID;
        }

        return $grouped;
    }
}
