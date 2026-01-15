<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

use ShadowORM\Core\Domain\Contract\ShadowRepositoryInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;
use ShadowORM\Core\Application\Cache\RuntimeCache;
use WP_Post;

final class SyncService
{
    public function __construct(
        private readonly ShadowRepositoryInterface $repository,
        private readonly RuntimeCache $cache,
    ) {
    }

    public function syncPost(int $postId): void
    {
        $post = get_post($postId);

        if ($post === null || !isset($post->post_type)) {
            return;
        }

        // Bypass ReadInterceptor and cache - fetch raw meta from Source of Truth (wp_postmeta)
        // This avoids circular dependency where syncing reads incomplete data from Shadow Table
        global $wpdb;
        $rawMeta = $wpdb->get_results(
            $wpdb->prepare("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $postId),
            ARRAY_A
        );

        $meta = [];
        foreach ($rawMeta as $row) {
            $meta[$row['meta_key']][] = $row['meta_value'];
        }
        $normalizedMeta = $this->normalizeMetaData($meta);

        $entity = new ShadowEntity(
            postId: $postId,
            postType: $post->post_type,
            content: $post->post_content,
            metaData: $normalizedMeta,
        );

        $this->repository->save($entity);
        $this->cache->set($postId, $entity);
    }

    public function deletePost(int $postId): void
    {
        $this->repository->remove($postId);
        $this->cache->delete($postId);
    }

    /**
     * @return int Number of migrated posts
     */
    public function migrateAll(string $postType, int $batchSize = 500, ?callable $progress = null): int
    {
        global $wpdb;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft'",
                $postType
            )
        );

        $migrated = 0;
        $offset = 0;

        while ($offset < $total) {
            $posts = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'auto-draft' LIMIT %d OFFSET %d",
                    $postType,
                    $batchSize,
                    $offset
                )
            );

            foreach ($posts as $postId) {
                $this->syncPost((int) $postId);
                $migrated++;
            }

            if ($progress !== null) {
                $progress($migrated, $total);
            }

            $offset += $batchSize;
        }

        /**
         * Fires after sync/migration is completed for a post type.
         * 
         * @param string $postType The post type that was synced
         * @param int $migrated Number of posts migrated
         */
        do_action('shadow_orm_sync_completed', $postType, $migrated);

        return $migrated;
    }

    public function rollback(string $postType): void
    {
        $this->cache->clear();
    }

    /**
     * @param array<string, array<mixed>> $meta
     * @return array<string, mixed>
     */
    private function normalizeMetaData(array $meta): array
    {
        $normalized = [];

        foreach ($meta as $key => $values) {
            // Allow all keys, including private ones (WooCommerce uses _price, Elementor _elementor_data)
            // But skip internal WP meta if needed (e.g. _edit_lock) - for now allow almost all
            if ($key === '_edit_lock' || $key === '_edit_last') {
                 continue;
            }

            $value = $values[0] ?? null;

            if ($value === null) {
                continue;
            }

            $unserialized = maybe_unserialize($value);
            $normalized[$key] = $unserialized;
        }

        return $normalized;
    }
}
