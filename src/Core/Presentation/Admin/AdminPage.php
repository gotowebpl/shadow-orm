<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPage
{
    private const MENU_SLUG = 'shadow-orm';
    private const CAPABILITY = 'manage_options';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function addMenuPage(): void
    {
        add_options_page(
            __('ShadowORM Settings', 'shadow-orm'),
            self::getMenuTitle(),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    private static function getMenuTitle(): string
    {
        $title = 'ShadowORM';

        if (self::isProActive()) {
            $title .= ' <span class="awaiting-mod">Pro</span>';
        }

        return $title;
    }

    public static function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::MENU_SLUG) {
            return;
        }

        $pluginUrl = plugin_dir_url(dirname(__DIR__, 4) . '/shadow-orm.php');

        wp_enqueue_style(
            'shadow-orm-admin',
            $pluginUrl . 'assets/admin.css',
            [],
            SHADOW_ORM_VERSION
        );

        wp_enqueue_script(
            'shadow-orm-admin',
            $pluginUrl . 'assets/admin.js',
            ['jquery'],
            SHADOW_ORM_VERSION,
            true
        );

        wp_localize_script('shadow-orm-admin', 'shadowOrmAdmin', [
            'restUrl' => rest_url('shadow-orm/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isPro' => self::isProActive(),
            'i18n' => [
                'syncing' => __('Synchronizacja...', 'shadow-orm'),
                'syncComplete' => __('Synchronizacja zakończona', 'shadow-orm'),
                'syncError' => __('Błąd synchronizacji', 'shadow-orm'),
                'confirm_rollback' => __('Czy na pewno chcesz usunąć tabelę shadow?', 'shadow-orm'),
            ],
        ]);

        do_action('shadow_orm_admin_enqueue_scripts', $hook);
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $tabs = self::getTabs();
        $currentTab = self::getCurrentTab($tabs);
        $settings = SettingsController::getSettings();
        $status = SettingsController::getStatus();

        include dirname(__DIR__, 4) . '/templates/admin-page.php';
    }

    public static function getTabs(): array
    {
        $tabs = [
            'dashboard' => [
                'title' => __('Dashboard', 'shadow-orm'),
                'icon' => 'dashicons-dashboard',
            ],
            'post-types' => [
                'title' => __('Post Types', 'shadow-orm'),
                'icon' => 'dashicons-database',
            ],
            'settings' => [
                'title' => __('Settings', 'shadow-orm'),
                'icon' => 'dashicons-admin-settings',
            ],
        ];

        return apply_filters('shadow_orm_admin_tabs', $tabs);
    }

    public static function getCurrentTab(array $tabs): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

        return array_key_exists($tab, $tabs) ? $tab : 'dashboard';
    }

    public static function isProActive(): bool
    {
        return class_exists('ShadowORMPro\\ShadowORMPro');
    }

    public static function getMenuSlug(): string
    {
        return self::MENU_SLUG;
    }
}

