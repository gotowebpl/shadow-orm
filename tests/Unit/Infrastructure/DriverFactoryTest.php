<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Infrastructure\Driver\DriverFactory;
use ShadowORM\Core\Infrastructure\Driver\MySQL8Driver;
use ShadowORM\Core\Infrastructure\Driver\LegacyDriver;
use ShadowORM\Core\Infrastructure\Exception\UnsupportedDatabaseException;
use Mockery;
use wpdb;

final class DriverFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testCreateMySQL8Driver(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('8.0.28');

        $factory = new DriverFactory($wpdb);
        $driver = $factory->create();

        $this->assertInstanceOf(MySQL8Driver::class, $driver);
    }

    public function testCreateMySQL817Driver(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('8.0.17');

        $factory = new DriverFactory($wpdb);
        $driver = $factory->create();

        $this->assertInstanceOf(MySQL8Driver::class, $driver);
    }

    public function testCreateLegacyDriverFor57(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('5.7.42');

        $factory = new DriverFactory($wpdb);
        $driver = $factory->create();

        $this->assertInstanceOf(LegacyDriver::class, $driver);
    }

    public function testCreateLegacyDriverForMariaDB(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('5.7.0');

        $factory = new DriverFactory($wpdb);
        $driver = $factory->create();

        $this->assertInstanceOf(LegacyDriver::class, $driver);
    }

    public function testThrowsExceptionForOldMySQL(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('5.6.50');

        $factory = new DriverFactory($wpdb);

        $this->expectException(UnsupportedDatabaseException::class);
        $this->expectExceptionMessage('5.6.50');

        $factory->create();
    }

    public function testGetMySQLVersion(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->once()->andReturn('8.0.28-ubuntu0.20.04.1');

        $factory = new DriverFactory($wpdb);

        $this->assertSame('8.0.28', $factory->getMySQLVersion());
    }

    public function testIsMySQL8True(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->andReturn('8.0.30');

        $factory = new DriverFactory($wpdb);

        $this->assertTrue($factory->isMySQL8());
    }

    public function testIsMySQL8False(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_version')->andReturn('5.7.40');

        $factory = new DriverFactory($wpdb);

        $this->assertFalse($factory->isMySQL8());
    }

    public function testIsMariaDB(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_server_info')->once()->andReturn('5.5.5-10.6.12-MariaDB');

        $factory = new DriverFactory($wpdb);

        $this->assertTrue($factory->isMariaDB());
    }

    public function testIsNotMariaDB(): void
    {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->shouldReceive('db_server_info')->once()->andReturn('8.0.28-ubuntu0.20.04.1');

        $factory = new DriverFactory($wpdb);

        $this->assertFalse($factory->isMariaDB());
    }
}
