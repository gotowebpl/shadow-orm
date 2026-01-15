<?php

declare(strict_types=1);

namespace ShadowORM\Core\Domain\ValueObject;

final class SupportedTypes
{
    /** @var array<string> */
    private static array $types = ['post', 'page', 'product', 'product_variation'];

    /**
     * @return array<string>
     */
    public static function get(): array
    {
        return self::$types;
    }

    public static function add(string $type): void
    {
        if (!in_array($type, self::$types, true)) {
            self::$types[] = $type;
        }
    }

    public static function remove(string $type): void
    {
        self::$types = array_values(array_filter(
            self::$types,
            static fn(string $t): bool => $t !== $type
        ));
    }

    public static function isSupported(string $type): bool
    {
        return in_array($type, self::$types, true);
    }
}
