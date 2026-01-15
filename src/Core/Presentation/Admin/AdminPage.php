<?php

declare(strict_types=1);

namespace ShadowORM\Core\Presentation\Admin;

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
            __('ShadowORM', 'shadow-orm'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render']
        );
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
            'i18n' => [
                'syncing' => __('Synchronizacja...', 'shadow-orm'),
                'syncComplete' => __('Synchronizacja zakończona', 'shadow-orm'),
                'syncError' => __('Błąd synchronizacji', 'shadow-orm'),
                'confirm_rollback' => __('Czy na pewno chcesz usunąć tabelę shadow?', 'shadow-orm'),
            ],
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        $settings = SettingsController::getSettings();
        $status = SettingsController::getStatus();

        include dirname(__DIR__, 4) . '/templates/admin-page.php';
    }
}
