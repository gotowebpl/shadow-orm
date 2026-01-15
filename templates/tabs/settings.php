<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $settings */
/** @var array $status */
?>
<div class="shadow-orm-settings-tab">
    <div class="shadow-orm-card">
        <h2>
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('Ustawienia główne', 'shadow-orm'); ?>
        </h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('System aktywny', 'shadow-orm'); ?></th>
                <td>
                    <label class="shadow-orm-toggle">
                        <input type="checkbox" id="shadow-orm-enabled" <?php checked($settings['enabled']); ?>>
                        <span class="slider"></span>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Włącz/wyłącz synchronizację z Shadow Tables', 'shadow-orm'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Driver bazy danych', 'shadow-orm'); ?></th>
                <td>
                    <code><?php echo esc_html($status['driver']); ?></code>
                    <p class="description">
                        MySQL <?php echo esc_html($status['mysql_version']); ?>
                        <?php if ($status['is_mysql8']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e('Natywny JSON (JSON_TABLE, ->> operator)', 'shadow-orm'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Lookup Tables (kompatybilność MySQL 5.7)', 'shadow-orm'); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="shadow-orm-save-settings" class="button button-primary">
                <?php esc_html_e('Zapisz zmiany', 'shadow-orm'); ?>
            </button>
            <span id="shadow-orm-save-status" style="margin-left: 10px;"></span>
        </p>
    </div>

    <?php do_action('shadow_orm_settings_after', $settings, $status); ?>
</div>
