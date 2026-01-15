<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

/**
 * Symulator WordPress EAV (Entity-Attribute-Value) model
 * Odpowiada strukturze wp_posts + wp_postmeta
 */
final class WordPressEavSimulator
{
    /** @var array<int, array{post_id: int, post_type: string, post_content: string}> */
    private array $posts = [];

    /** @var array<int, array<array{meta_id: int, post_id: int, meta_key: string, meta_value: mixed}>> */
    private array $postmeta = [];

    private int $metaIdCounter = 1;

    public function insertPost(int $postId, string $postType, string $content): void
    {
        $this->posts[$postId] = [
            'post_id' => $postId,
            'post_type' => $postType,
            'post_content' => $content,
        ];
    }

    /**
     * @param array<string, mixed> $metaData
     */
    public function insertPostMeta(int $postId, array $metaData): void
    {
        if (!isset($this->postmeta[$postId])) {
            $this->postmeta[$postId] = [];
        }

        foreach ($metaData as $key => $value) {
            $this->postmeta[$postId][] = [
                'meta_id' => $this->metaIdCounter++,
                'post_id' => $postId,
                'meta_key' => $key,
                'meta_value' => is_array($value) ? serialize($value) : $value,
            ];
        }
    }

    public function getPostMeta(int $postId, string $metaKey, bool $single = true): mixed
    {
        if (!isset($this->postmeta[$postId])) {
            return $single ? '' : [];
        }

        $values = [];
        foreach ($this->postmeta[$postId] as $meta) {
            if ($meta['meta_key'] === $metaKey) {
                $value = $meta['meta_value'];
                if (is_string($value) && $this->isSerialized($value)) {
                    $value = unserialize($value);
                }
                $values[] = $value;
            }
        }

        if ($single) {
            return $values[0] ?? '';
        }

        return $values;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function getAllPostMeta(int $postId): array
    {
        if (!isset($this->postmeta[$postId])) {
            return [];
        }

        $result = [];
        foreach ($this->postmeta[$postId] as $meta) {
            $key = $meta['meta_key'];
            $value = $meta['meta_value'];

            if (is_string($value) && $this->isSerialized($value)) {
                $value = unserialize($value);
            }

            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key][] = $value;
        }

        return $result;
    }

    public function updatePostMeta(int $postId, string $metaKey, mixed $value): bool
    {
        if (!isset($this->postmeta[$postId])) {
            return false;
        }

        foreach ($this->postmeta[$postId] as &$meta) {
            if ($meta['meta_key'] === $metaKey) {
                $meta['meta_value'] = is_array($value) ? serialize($value) : $value;
                return true;
            }
        }

        return false;
    }

    /**
     * Symulacja WP_Query z meta_query
     * @param array<array{key: string, value: mixed, compare?: string}> $metaQuery
     * @return array<int>
     */
    public function queryByMeta(string $postType, array $metaQuery): array
    {
        $matchingPostIds = [];

        foreach ($this->posts as $postId => $post) {
            if ($post['post_type'] !== $postType) {
                continue;
            }

            $matches = true;
            foreach ($metaQuery as $query) {
                $key = $query['key'];
                $value = $query['value'];
                $compare = $query['compare'] ?? '=';

                $metaValue = $this->getPostMeta($postId, $key, true);

                if (!$this->compareValues($metaValue, $value, $compare)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $matchingPostIds[] = $postId;
            }
        }

        return $matchingPostIds;
    }

    /**
     * @param array<int> $postIds
     * @return array<int, array<string, array<mixed>>>
     */
    public function getMultiplePostMeta(array $postIds): array
    {
        $result = [];
        foreach ($postIds as $postId) {
            $result[$postId] = $this->getAllPostMeta($postId);
        }
        return $result;
    }

    public function clear(): void
    {
        $this->posts = [];
        $this->postmeta = [];
        $this->metaIdCounter = 1;
    }

    public function countPosts(): int
    {
        return count($this->posts);
    }

    public function countMeta(): int
    {
        $count = 0;
        foreach ($this->postmeta as $metas) {
            $count += count($metas);
        }
        return $count;
    }

    private function isSerialized(string $data): bool
    {
        if ($data === 'N;') {
            return true;
        }

        if (strlen($data) < 4) {
            return false;
        }

        if ($data[1] !== ':') {
            return false;
        }

        return in_array($data[0], ['s', 'a', 'O', 'i', 'd', 'b'], true);
    }

    private function compareValues(mixed $metaValue, mixed $queryValue, string $compare): bool
    {
        return match ($compare) {
            '=' => $metaValue == $queryValue,
            '!=' => $metaValue != $queryValue,
            '>' => $metaValue > $queryValue,
            '>=' => $metaValue >= $queryValue,
            '<' => $metaValue < $queryValue,
            '<=' => $metaValue <= $queryValue,
            'LIKE' => is_string($metaValue) && str_contains($metaValue, (string) $queryValue),
            'IN' => is_array($queryValue) && in_array($metaValue, $queryValue, false),
            'EXISTS' => $metaValue !== '',
            default => false,
        };
    }
}
