<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use ShadowORM\Core\Infrastructure\Installer\DropInInstaller;

final class DropInInstallerTest extends TestCase
{
    private string $testContentDir;
    private string $testMuDir;
    private string $testPluginDir;

    protected function setUp(): void
    {
        $this->testContentDir = sys_get_temp_dir() . '/shadow-orm-test-' . uniqid();
        $this->testMuDir = $this->testContentDir . '/mu-plugins';
        $this->testPluginDir = $this->testContentDir . '/plugins/shadow-orm';

        mkdir($this->testPluginDir . '/stubs', 0755, true);

        file_put_contents(
            $this->testPluginDir . '/stubs/db-template.php',
            "<?php\n// ShadowORM Database Drop-In\n"
        );

        file_put_contents(
            $this->testPluginDir . '/stubs/mu-loader-template.php',
            "<?php\n// ShadowORM MU Loader\n"
        );
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->testContentDir);
    }

    public function testInstallCreatesDropIns(): void
    {
        $this->markTestSkipped('Requires mocking WP constants');
    }

    public function testUninstallRemovesDropIns(): void
    {
        $this->markTestSkipped('Requires mocking WP constants');
    }

    public function testIsInstalledReturnsFalseWhenNotInstalled(): void
    {
        $this->assertFalse(DropInInstaller::isInstalled());
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }

        rmdir($dir);
    }
}
