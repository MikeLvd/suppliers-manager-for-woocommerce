<?php
declare(strict_types=1);
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
function smfw_uninstall_cleanup(): void
{
    global $wpdb;
    $delete_data = get_option('smfw_delete_data_on_uninstall', 'no');
    if ($delete_data !== 'yes') {
        return;
    }
    delete_option('smfw_version');
    delete_option('smfw_notification_status');
    delete_option('smfw_delete_data_on_uninstall');
    delete_option('smfw_enable_email_history');
    delete_option('smfw_bcc_admin');
    delete_option('smfw_admin_email');
    delete_option('smfw_history_retention_days');
    delete_option('smfw_migration_stats');
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smfw_product_suppliers");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smfw_email_history");
    wp_clear_scheduled_hook('smfw_check_stock_availability');
    wp_clear_scheduled_hook('smfw_cleanup_email_history');
}
smfw_uninstall_cleanup();
