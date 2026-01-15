<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $settings */
/** @var array $status */
?>
<div class="shadow-orm-post-types">
    <div class="shadow-orm-card">
        <h2>
            <span class="dashicons dashicons-database"></span>
            <?php esc_html_e('Zarządzanie typami postów', 'shadow-orm'); ?>
        </h2>
        <p class="description">
            <?php esc_html_e('Włącz synchronizację dla wybranych typów postów. Po włączeniu metadane będą przechowywane w zoptymalizowanych Shadow Tables.', 'shadow-orm'); ?>
        </p>

        <table class="widefat striped shadow-orm-types-table">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php esc_html_e('Typ', 'shadow-orm'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Włączony', 'shadow-orm'); ?></th>
                    <th><?php esc_html_e('Status migracji', 'shadow-orm'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('Akcje', 'shadow-orm'); ?></th>
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
                                <?php if ($typeStatus['migrated'] < $typeStatus['total']): ?>
                                    <span class="status-incomplete" style="color: #dba617;">
                                        <span class="dashicons dashicons-warning"></span>
                                    </span>
                                <?php else: ?>
                                    <span class="status-complete" style="color: #46b450;">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </span>
                                <?php endif; ?>
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

    <?php do_action('shadow_orm_post_types_after', $settings, $status); ?>
</div>
