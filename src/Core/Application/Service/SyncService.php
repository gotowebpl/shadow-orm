<?php

declare(strict_types=1);

namespace ShadowORM\Core\Application\Service;

use ShadowORM\Core\Application\Cache\RuntimeCache;
use ShadowORM\Core\Domain\Contract\PostMetaReaderInterface;
use ShadowORM\Core\Domain\Contract\ShadowRepositoryInterface;
use ShadowORM\Core\Domain\Entity\ShadowEntity;

final class SyncService
{
    public function __construct(
        private readonly ShadowRepositoryInterface $repository,
        private readonly RuntimeCache $cache,
        private readonly PostMetaReaderInterface $metaReader,
    ) {
    }

    public function syncPost(int $postId): void
    {
        $post = get_post($postId);

        if ($post === null || !isset($post->post_type)) {
            return;
        }

        $meta = $this->metaReader->getPostMeta($postId);

        $entity = new ShadowEntity(
            postId: $postId,
            postType: $post->post_type,
            content: $post->post_content,
            metaData: $this->normalizeMetaData($meta),
        );

        $this->repository->save($entity);
        $this->cache->set($postId, $entity);
    }

    public function deletePost(int $postId): void
    {
        $this->repository->remove($postId);
        $this->cache->delete($postId);
    }

    public function migrateAll(string $postType, int $batchSize = 500, ?callable $progress = null): int
    {
        $total = $this->metaReader->countPosts($postType);
        $migrated = 0;
        $offset = 0;

        while ($offset < $total) {
            $postIds = $this->metaReader->getPostIds($postType, $batchSize, $offset);

            foreach ($postIds as $postId) {
                $this->syncPost($postId);
                $migrated++;
            }

            if ($progress !== null) {
                $progress($migrated, $total);
            }

            $offset += $batchSize;
        }

        do_action('shadow_orm_sync_completed', $postType, $migrated);

        return $migrated;
    }

    public function rollback(string $postType): void
    {
        $total = $this->metaReader->countPosts($postType);
        $offset = 0;
        $batchSize = 500;

        while ($offset < $total) {
            $postIds = $this->metaReader->getPostIds($postType, $batchSize, $offset);

            foreach ($postIds as $postId) {
                $this->repository->remove($postId);
            }

            $offset += $batchSize;
        }

        $this->cache->clear();
    }

    /**
     * @param array<string, array<string>> $meta
     * @return array<string, mixed>
     */
    private function normalizeMetaData(array $meta): array
    {
        $normalized = [];

        foreach ($meta as $key => $values) {
            if ($key === '_edit_lock' || $key === '_edit_last') {
                continue;
            }

            $value = $values[0] ?? null;

            if ($value === null) {
                continue;
            }

            $normalized[$key] = maybe_unserialize($value);
        }

        return $normalized;
    }
}
