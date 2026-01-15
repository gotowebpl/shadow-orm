<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $tabs */
/** @var string $currentTab */
/** @var array $settings */
/** @var array $status */

$pageUrl = admin_url('options-general.php?page=shadow-orm');
?>
<div class="wrap shadow-orm-admin">
    <h1>
        <?php esc_html_e('ShadowORM', 'shadow-orm'); ?>
        <?php if (\ShadowORM\Core\Presentation\Admin\AdminPage::isProActive()): ?>
            <span class="shadow-orm-pro-badge">Pro</span>
        <?php endif; ?>
    </h1>

    <nav class="nav-tab-wrapper shadow-orm-tabs">
        <?php foreach ($tabs as $tabId => $tab): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tabId, $pageUrl)); ?>"
               class="nav-tab <?php echo $currentTab === $tabId ? 'nav-tab-active' : ''; ?>">
                <?php if (!empty($tab['icon'])): ?>
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                <?php endif; ?>
                <?php echo esc_html($tab['title']); ?>
                <?php if (!empty($tab['pro'])): ?>
                    <span class="shadow-orm-pro-label">Pro</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="shadow-orm-tab-content">
        <?php
        switch ($currentTab) {
            case 'dashboard':
                include __DIR__ . '/tabs/dashboard.php';
                break;
            case 'post-types':
                include __DIR__ . '/tabs/post-types.php';
                break;
            case 'settings':
                include __DIR__ . '/tabs/settings.php';
                break;
            default:
                do_action('shadow_orm_admin_tab_' . $currentTab, $settings, $status);
                break;
        }
        ?>
    </div>

    <div id="shadow-orm-progress" style="display: none;">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <p class="progress-text"></p>
    </div>
</div>

