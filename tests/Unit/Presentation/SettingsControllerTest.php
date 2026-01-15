<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Presentation;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Presentation\Admin\SettingsController;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class SettingsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetSettingsReturnsDefaults(): void
    {
        Functions\when('get_option')->alias(function($key, $default = false) {
            return match($key) {
                'shadow_orm_enabled' => false,
                'shadow_orm_post_types' => [],
                'shadow_orm_driver' => 'auto',
                default => $default,
            };
        });

        $settings = SettingsController::getSettings();

        $this->assertArrayHasKey('enabled', $settings);
        $this->assertArrayHasKey('post_types', $settings);
        $this->assertArrayHasKey('driver', $settings);
        $this->assertFalse($settings['enabled']);
        $this->assertIsArray($settings['post_types']);
        $this->assertSame('auto', $settings['driver']);
    }

    public function testGetSettingsReturnsStoredValues(): void
    {
        Functions\when('get_option')->alias(function($key, $default = false) {
            return match($key) {
                'shadow_orm_enabled' => true,
                'shadow_orm_post_types' => ['post', 'product'],
                'shadow_orm_driver' => 'mysql8',
                default => $default,
            };
        });

        $settings = SettingsController::getSettings();

        $this->assertTrue($settings['enabled']);
        $this->assertSame(['post', 'product'], $settings['post_types']);
        $this->assertSame('mysql8', $settings['driver']);
    }

    public function testCheckPermissionRequiresManageOptions(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $this->assertTrue(SettingsController::checkPermission());

        Functions\when('current_user_can')->justReturn(false);
        $this->assertFalse(SettingsController::checkPermission());
    }
}
