<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Persistence;

use ShadowORM\Core\Domain\Contract\PostMetaReaderInterface;
use wpdb;

final class WpPostMetaReader implements PostMetaReaderInterface
{
    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function getPostMeta(int $postId): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->wpdb->postmeta} WHERE post_id = %d",
                $postId
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['meta_key']][] = $row['meta_value'];
        }

        return $meta;
    }

    public function getPostIds(string $postType, int $limit, int $offset): array
    {
        $ids = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT ID FROM {$this->wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft' LIMIT %d OFFSET %d",
                $postType,
                $limit,
                $offset
            )
        );

        return array_map('intval', $ids);
    }

    public function countPosts(string $postType): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                $postType
            )
        );
    }
}
