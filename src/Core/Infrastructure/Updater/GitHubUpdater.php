<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Updater;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Handles automatic plugin updates from GitHub releases.
 *
 * This class integrates with the Plugin Update Checker library to enable
 * seamless updates directly from the GitHub repository, bypassing the
 * WordPress.org plugin directory.
 *
 * @see https://github.com/YahnisElsts/plugin-update-checker
 */
final class GitHubUpdater
{
    private const GITHUB_REPOSITORY = 'https://github.com/gotowebpl/shadow-orm/';
    private const DEFAULT_BRANCH = 'main';
    private const PLUGIN_SLUG = 'shadow-orm';

    /** @var object|null Plugin Update Checker instance */
    private ?object $updateChecker = null;

    /**
     * @param string $pluginFile Absolute path to the main plugin file.
     */
    public function __construct(
        private readonly string $pluginFile,
    ) {
    }

    /**
     * Initializes the update checker.
     *
     * Should be called during plugin initialization, after plugins_loaded hook.
     */
    public function register(): void
    {
        if (!class_exists(PucFactory::class)) {
            return;
        }

        $this->updateChecker = PucFactory::buildUpdateChecker(
            self::GITHUB_REPOSITORY,
            $this->pluginFile,
            self::PLUGIN_SLUG
        );

        $this->configureBranch();
        $this->configureReleaseAssets();
    }

    /**
     * Sets the branch to check for updates.
     *
     * By default, uses the main branch. For release-based updates,
     * this ensures we track the stable release branch.
     */
    private function configureBranch(): void
    {
        if ($this->updateChecker === null) {
            return;
        }

        if (method_exists($this->updateChecker, 'setBranch')) {
            $this->updateChecker->setBranch(self::DEFAULT_BRANCH);
        }
    }

    /**
     * Configures the update checker to use GitHub releases.
     *
     * When releases are available, the updater will prefer tagged releases
     * over branch commits, ensuring users receive stable versions.
     */
    private function configureReleaseAssets(): void
    {
        if ($this->updateChecker === null) {
            return;
        }

        if (method_exists($this->updateChecker, 'getVcsApi')) {
            $api = $this->updateChecker->getVcsApi();
            if ($api !== null && method_exists($api, 'enableReleaseAssets')) {
                $api->enableReleaseAssets();
            }
        }
    }

    /**
     * Sets GitHub authentication token for private repositories.
     *
     * @param string $token GitHub personal access token or OAuth token.
     */
    public function setAuthentication(string $token): void
    {
        if ($this->updateChecker === null) {
            return;
        }

        if (method_exists($this->updateChecker, 'setAuthentication')) {
            $this->updateChecker->setAuthentication($token);
        }
    }

    /**
     * Forces an immediate update check.
     *
     * Useful for debugging or manual update triggering from admin panel.
     */
    public function checkForUpdates(): ?object
    {
        if ($this->updateChecker === null) {
            return null;
        }

        if (method_exists($this->updateChecker, 'checkForUpdates')) {
            return $this->updateChecker->checkForUpdates();
        }

        return null;
    }

    /**
     * Returns whether an update is available.
     */
    public function isUpdateAvailable(): bool
    {
        $update = $this->checkForUpdates();

        return $update !== null && isset($update->version);
    }

    /**
     * Returns the update checker instance for advanced configuration.
     */
    public function getUpdateChecker(): ?object
    {
        return $this->updateChecker;
    }
}
