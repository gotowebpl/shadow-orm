<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Installer;

use RuntimeException;

final class DropInInstaller
{
    private const DB_DROP_IN = 'db.php';
    private const MU_LOADER = 'shadow-orm-loader.php';

    public static function install(): void
    {
        self::installDbDropIn();
        self::installMuLoader();
        self::createMuPluginsDir();
    }

    public static function uninstall(): void
    {
        self::removeDbDropIn();
        self::removeMuLoader();
    }

    public static function isInstalled(): bool
    {
        return self::dbDropInExists() && self::muLoaderExists();
    }

    private static function installDbDropIn(): void
    {
        $target = self::getContentDir() . '/' . self::DB_DROP_IN;
        $stub = self::getPluginDir() . '/stubs/db.php.stub';

        if (self::dbDropInExists() && !self::isOurDropIn($target)) {
            self::backupExistingDropIn($target);
        }

        self::copyFile($stub, $target);
    }

    private static function installMuLoader(): void
    {
        self::createMuPluginsDir();
        
        $target = self::getMuPluginsDir() . '/' . self::MU_LOADER;
        $stub = self::getPluginDir() . '/stubs/mu-loader.php.stub';

        self::copyFile($stub, $target);
    }

    private static function removeDbDropIn(): void
    {
        $target = self::getContentDir() . '/' . self::DB_DROP_IN;

        if (!self::dbDropInExists()) {
            return;
        }

        if (!self::isOurDropIn($target)) {
            return;
        }

        self::deleteFile($target);
        self::restoreBackupIfExists($target);
    }

    private static function removeMuLoader(): void
    {
        $target = self::getMuPluginsDir() . '/' . self::MU_LOADER;

        if (!file_exists($target)) {
            return;
        }

        self::deleteFile($target);
    }

    private static function dbDropInExists(): bool
    {
        return file_exists(self::getContentDir() . '/' . self::DB_DROP_IN);
    }

    private static function muLoaderExists(): bool
    {
        return file_exists(self::getMuPluginsDir() . '/' . self::MU_LOADER);
    }

    private static function isOurDropIn(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        return $content !== false && str_contains($content, 'ShadowORM Database Drop-In');
    }

    private static function backupExistingDropIn(string $path): void
    {
        $backup = $path . '.shadow-orm-backup';
        
        if (!copy($path, $backup)) {
            throw new RuntimeException("Failed to backup existing db.php to {$backup}");
        }
    }

    private static function restoreBackupIfExists(string $originalPath): void
    {
        $backup = $originalPath . '.shadow-orm-backup';

        if (!file_exists($backup)) {
            return;
        }

        rename($backup, $originalPath);
    }

    private static function createMuPluginsDir(): void
    {
        $dir = self::getMuPluginsDir();

        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create mu-plugins directory: {$dir}");
        }
    }

    private static function copyFile(string $source, string $target): void
    {
        if (!file_exists($source)) {
            throw new RuntimeException("Source file not found: {$source}");
        }

        if (!copy($source, $target)) {
            throw new RuntimeException("Failed to copy {$source} to {$target}");
        }
    }

    private static function deleteFile(string $path): void
    {
        if (!unlink($path)) {
            throw new RuntimeException("Failed to delete file: {$path}");
        }
    }

    private static function getContentDir(): string
    {
        return defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    }

    private static function getMuPluginsDir(): string
    {
        return defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : self::getContentDir() . '/mu-plugins';
    }

    private static function getPluginDir(): string
    {
        return dirname(__DIR__, 4);
    }
}
