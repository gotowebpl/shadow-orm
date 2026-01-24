<?php

declare(strict_types=1);

/**
 * Plugin Name: ShadowORM MySQL Accelerator
 * Plugin URI: https://github.com/gotowebpl/shadow-orm
 * Description: High-performance ORM layer for WordPress/WooCommerce with Shadow Tables
 * Version: 1.2.6
 * Requires PHP: 8.1
 * Author: gotoweb.pl
 * Author URI: https://gotoweb.pl
 * License: GPLv2 or later
 * Text Domain: shadoworm-mysql-accelerator
 * WP tested up to: 6.9
 */

namespace ShadowORM;

use ShadowORM\Core\Application\Service\AutoDiscoveryService;
use ShadowORM\Core\Presentation\Hook\ReadInterceptor;
use ShadowORM\Core\Presentation\Hook\WriteInterceptor;
use ShadowORM\Core\Presentation\Hook\QueryInterceptor;
use ShadowORM\Core\Presentation\Hook\PostQueryPreloader;
use ShadowORM\Core\Presentation\Admin\AdminPage;
use ShadowORM\Core\Presentation\Admin\SettingsController;

if (!defined('ABSPATH')) {
    exit;
}

const VERSION = '1.2.6';
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR = __DIR__;
const MIN_MYSQL_VERSION = '5.7.0';

define('SHADOW_ORM_VERSION', VERSION);

require_once __DIR__ . '/vendor/autoload.php';

final class ShadowORM
{
    private static ?self $instance = null;

    private function __construct()
    {
        $this->checkRequirements();
        $this->registerHooks();
        $this->registerRestApi();
        $this->registerAdmin();
        $this->registerCli();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function checkRequirements(): void
    {
        global $wpdb;

        $version = $wpdb->db_version();

        if (version_compare($version, MIN_MYSQL_VERSION, '<')) {
            add_action('admin_notices', static fn() => printf(
                '<div class="notice notice-error"><p>ShadowORM requires MySQL %s+. Current: %s</p></div>',
                esc_html(MIN_MYSQL_VERSION),
                esc_html($version)
            ));
        }
    }

    private function registerHooks(): void
    {
        add_action('registered_post_type', [AutoDiscoveryService::class, 'onPostTypeRegistered'], 10, 2);
        add_filter('get_post_metadata', [ReadInterceptor::class, 'intercept'], 1, 5);
        add_action('save_post', [WriteInterceptor::class, 'onSavePost'], 20, 3);
        add_action('updated_post_meta', [WriteInterceptor::class, 'onMetaChange'], 10, 4);
        add_action('added_post_meta', [WriteInterceptor::class, 'onMetaChange'], 10, 4);
        add_action('deleted_post_meta', [WriteInterceptor::class, 'onMetaDeleted'], 10, 4);
        add_action('deleted_post', [WriteInterceptor::class, 'onDeletePost'], 10, 2);
        add_filter('posts_clauses', [QueryInterceptor::class, 'intercept'], 10, 2);
        add_filter('the_posts', [PostQueryPreloader::class, 'preload'], 10, 2);

        Core\Application\Service\AsyncWriteService::register();
    }

    private function registerCli(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('shadow', Core\Presentation\Cli\ShadowCommand::class);
    }

    private function registerRestApi(): void
    {
        SettingsController::register();
    }

    private function registerAdmin(): void
    {
        if (!is_admin()) {
            return;
        }

        AdminPage::register();
    }
}

add_action('plugins_loaded', [ShadowORM::class, 'getInstance']);

