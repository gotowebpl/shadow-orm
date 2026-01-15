<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\Entity;

final class ShadowEntity
{
    /** @var array<string, mixed> */
    private array $metaData;

    /**
     * @param array<string, mixed> $metaData
     */
    public function __construct(
        public readonly int $postId,
        public readonly string $postType,
        public readonly string $content = '',
        array $metaData = [],
    ) {
        $this->metaData = $metaData;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metaData[$key] ?? $default;
    }

    public function setMeta(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->metaData[$key] = $value;

        return $clone;
    }

    public function hasMeta(string $key): bool
    {
        return array_key_exists($key, $this->metaData);
    }

    public function removeMeta(string $key): self
    {
        $clone = clone $this;
        unset($clone->metaData[$key]);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMeta(): array
    {
        return $this->metaData;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'post_id' => $this->postId,
            'post_type' => $this->postType,
            'content' => $this->content,
            'meta_data' => $this->metaData,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            postId: (int) ($data['post_id'] ?? 0),
            postType: (string) ($data['post_type'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            metaData: (array) ($data['meta_data'] ?? []),
        );
    }
}
