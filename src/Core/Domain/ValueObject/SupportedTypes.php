<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\ValueObject;

if (!defined('ABSPATH')) {
    exit;
}

final class SupportedTypes
{
    /** @var array<string, true> Hash map for O(1) lookup */
    private static array $types = [
        'post' => true,
        'page' => true,
        'product' => true,
        'product_variation' => true,
    ];

    /**
     * @return array<string>
     */
    public static function get(): array
    {
        return array_keys(self::$types);
    }

    public static function add(string $type): void
    {
        self::$types[$type] = true;
    }

    public static function remove(string $type): void
    {
        unset(self::$types[$type]);
    }

    public static function isSupported(string $type): bool
    {
        return isset(self::$types[$type]);
    }
}
