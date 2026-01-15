<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Driver;

use ShadowORM\Core\Domain\Contract\StorageDriverInterface;
use ShadowORM\Core\Infrastructure\Exception\UnsupportedDatabaseException;
use wpdb;

final class DriverFactory
{
    private const MIN_MYSQL8_VERSION = '8.0.17';
    private const MIN_LEGACY_VERSION = '5.7.0';

    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    public function create(): StorageDriverInterface
    {
        $version = $this->getMySQLVersion();

        if (version_compare($version, self::MIN_MYSQL8_VERSION, '>=')) {
            return new MySQL8Driver($this->wpdb);
        }

        if (version_compare($version, self::MIN_LEGACY_VERSION, '>=')) {
            return new LegacyDriver($this->wpdb);
        }

        throw new UnsupportedDatabaseException(
            sprintf('MySQL %s is not supported. Minimum version: %s', $version, self::MIN_LEGACY_VERSION)
        );
    }

    public function getMySQLVersion(): string
    {
        $version = $this->wpdb->db_version();

        if (preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)) {
            return $matches[1];
        }

        return $version;
    }

    public function isMySQL8(): bool
    {
        return version_compare($this->getMySQLVersion(), self::MIN_MYSQL8_VERSION, '>=');
    }

    public function isMariaDB(): bool
    {
        $version = $this->wpdb->db_server_info();

        return stripos($version, 'mariadb') !== false;
    }
}
