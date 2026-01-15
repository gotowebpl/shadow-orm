<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $settings */
/** @var array $status */
?>
<div class="wrap shadow-orm-admin">
    <h1><?php esc_html_e('ShadowORM Settings', 'shadow-orm'); ?></h1>

    <div class="shadow-orm-grid">
        <div class="shadow-orm-card shadow-orm-settings">
            <h2><?php esc_html_e('Ustawienia główne', 'shadow-orm'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('System aktywny', 'shadow-orm'); ?></th>
                    <td>
                        <label class="shadow-orm-toggle">
                            <input type="checkbox" id="shadow-orm-enabled" <?php checked($settings['enabled']); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e('Włącz/wyłącz przechwytywanie zapytań', 'shadow-orm'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Driver', 'shadow-orm'); ?></th>
                    <td>
                        <code><?php echo esc_html($status['driver']); ?></code>
                        <p class="description">
                            MySQL <?php echo esc_html($status['mysql_version']); ?>
                            <?php if ($status['is_mysql8']): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php esc_html_e('Natywny JSON', 'shadow-orm'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('Lookup Tables', 'shadow-orm'); ?>
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

        <div class="shadow-orm-card shadow-orm-status">
            <h2><?php esc_html_e('Status tabel', 'shadow-orm'); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Typ', 'shadow-orm'); ?></th>
                        <th><?php esc_html_e('Włączony', 'shadow-orm'); ?></th>
                        <th><?php esc_html_e('Status', 'shadow-orm'); ?></th>
                        <th><?php esc_html_e('Akcje', 'shadow-orm'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['post_types'] as $postType => $typeStatus): ?>
                        <tr data-post-type="<?php echo esc_attr($postType); ?>">
                            <td><strong><?php echo esc_html($postType); ?></strong></td>
                            <td>
                                <label class="shadow-orm-toggle small">
                                    <input type="checkbox"
                                           class="shadow-orm-type-toggle"
                                           data-post-type="<?php echo esc_attr($postType); ?>"
                                           <?php checked(in_array($postType, $settings['post_types'], true)); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td class="status-cell">
                                <?php if ($typeStatus['exists']): ?>
                                    <span class="status-migrated">
                                        <?php echo esc_html($typeStatus['migrated']); ?> / <?php echo esc_html($typeStatus['total']); ?>
                                    </span>
                                    <span class="status-size">(<?php echo esc_html(size_format($typeStatus['size'])); ?>)</span>
                                <?php else: ?>
                                    <span class="status-pending"><?php esc_html_e('Nie zmigrowane', 'shadow-orm'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <button type="button"
                                        class="button button-primary shadow-orm-sync"
                                        data-post-type="<?php echo esc_attr($postType); ?>">
                                    <?php esc_html_e('Synchronizuj', 'shadow-orm'); ?>
                                </button>
                                <?php if ($typeStatus['exists']): ?>
                                    <button type="button"
                                            class="button shadow-orm-rollback"
                                            data-post-type="<?php echo esc_attr($postType); ?>">
                                        <?php esc_html_e('Rollback', 'shadow-orm'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="shadow-orm-progress" style="display: none;">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <p class="progress-text"></p>
    </div>
</div>
