<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\Contract;

interface PostMetaReaderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getPostMeta(int $postId): array;

    /**
     * @return array<int>
     */
    public function getPostIds(string $postType, int $limit, int $offset): array;

    public function countPosts(string $postType): int;
}
