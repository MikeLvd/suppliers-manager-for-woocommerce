<?php
declare(strict_types=1);
namespace Suppliers_Manager_For_WooCommerce;
class Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
        wp_clear_scheduled_hook('smfw_check_stock_availability');
        wp_clear_scheduled_hook('smfw_cleanup_email_history');
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info(
                'Suppliers Manager for WooCommerce v3.0.0 deactivated',
                ['source' => 'suppliers-manager']
            );
        }
    }
}
