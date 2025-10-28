<?php
declare(strict_types=1);
namespace Suppliers_Manager_For_WooCommerce;
class I18n
{
    public function load_plugin_textdomain(): void
    {
        load_plugin_textdomain(
            'suppliers-manager-for-woocommerce',
            false,
            dirname(plugin_basename(SMFW_PLUGIN_DIR)) . '/languages/'
        );
    }
}
