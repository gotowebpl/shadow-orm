<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $settings */
/** @var array $status */
?>
<div class="shadow-orm-dashboard">
    <div class="shadow-orm-grid">
        <div class="shadow-orm-card">
            <h2>
                <span class="dashicons dashicons-performance"></span>
                <?php esc_html_e('Status systemu', 'shadow-orm'); ?>
            </h2>
            <table class="widefat">
                <tr>
                    <th><?php esc_html_e('Driver', 'shadow-orm'); ?></th>
                    <td>
                        <code><?php echo esc_html($status['driver']); ?></code>
                        <?php if ($status['is_mysql8']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('MySQL', 'shadow-orm'); ?></th>
                    <td><?php echo esc_html($status['mysql_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Obsługiwane typy', 'shadow-orm'); ?></th>
                    <td><?php echo esc_html(implode(', ', $settings['post_types'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Status', 'shadow-orm'); ?></th>
                    <td>
                        <?php if ($settings['enabled']): ?>
                            <span style="color: #46b450;">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Aktywny', 'shadow-orm'); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #dc3232;">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Wyłączony', 'shadow-orm'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="shadow-orm-card">
            <h2>
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Statystyki tabel', 'shadow-orm'); ?>
            </h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Typ', 'shadow-orm'); ?></th>
                        <th><?php esc_html_e('Zmigrowane', 'shadow-orm'); ?></th>
                        <th><?php esc_html_e('Rozmiar', 'shadow-orm'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalMigrated = 0;
                    $totalSize = 0;
                    foreach ($status['post_types'] as $postType => $typeStatus):
                        if ($typeStatus['exists']):
                            $totalMigrated += $typeStatus['migrated'];
                            $totalSize += $typeStatus['size'];
                    ?>
                        <tr>
                            <td><?php echo esc_html($postType); ?></td>
                            <td><?php echo esc_html($typeStatus['migrated']); ?> / <?php echo esc_html($typeStatus['total']); ?></td>
                            <td><?php echo esc_html(size_format($typeStatus['size'])); ?></td>
                        </tr>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th><?php esc_html_e('Razem', 'shadow-orm'); ?></th>
                        <th><?php echo esc_html($totalMigrated); ?></th>
                        <th><?php echo esc_html(size_format($totalSize)); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php do_action('shadow_orm_dashboard_after', $settings, $status); ?>
</div>
