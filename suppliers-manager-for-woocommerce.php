<?php
/**
 * Suppliers Manager for WooCommerce
 *
 * @package           Suppliers_Manager_For_WooCommerce
 * @author            Mike Lvd
 * @copyright         2019-2025 Mike Lvd
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Suppliers Manager for WooCommerce
 * Plugin URI:        https://goldenbath.gr/
 * Description:       Advanced supplier management with Custom Post Type architecture, rich profiles, document management, and automated notifications.
 * Version:           3.0.1
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            Mike Lvd
 * Author URI:        https://goldenbath.gr/
 * Text Domain:       suppliers-manager-for-woocommerce
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 5.0.0
 * WC tested up to:   8.5.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version
 */
define('SMFW_VERSION', '3.0.0');

/**
 * Plugin directory path
 */
define('SMFW_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL
 */
define('SMFW_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename
 */
define('SMFW_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Minimum PHP version required
 */
define('SMFW_MIN_PHP_VERSION', '8.0');

/**
 * Minimum WordPress version required
 */
define('SMFW_MIN_WP_VERSION', '5.8');

/**
 * Minimum WooCommerce version required
 */
define('SMFW_MIN_WC_VERSION', '5.0.0');

/**
 * Check system requirements before loading plugin
 *
 * @return bool True if requirements are met, false otherwise
 */
function smfw_check_requirements(): bool
{
    global $wp_version;

    // Check PHP version
    if (version_compare(PHP_VERSION, SMFW_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Current PHP version, 2: Required PHP version */
                    esc_html__('Suppliers Manager for WooCommerce requires PHP %2$s or higher. You are running PHP %1$s.', 'suppliers-manager-for-woocommerce'),
                    esc_html(PHP_VERSION),
                    esc_html(SMFW_MIN_PHP_VERSION)
                )
            );
        });
        return false;
    }

    // Check WordPress version
    if (version_compare($wp_version, SMFW_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', function () use ($wp_version) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Current WordPress version, 2: Required WordPress version */
                    esc_html__('Suppliers Manager for WooCommerce requires WordPress %2$s or higher. You are running WordPress %1$s.', 'suppliers-manager-for-woocommerce'),
                    esc_html($wp_version),
                    esc_html(SMFW_MIN_WP_VERSION)
                )
            );
        });
        return false;
    }

    // Check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Suppliers Manager for WooCommerce requires WooCommerce to be installed and activated.', 'suppliers-manager-for-woocommerce')
            );
        });
        return false;
    }

    // Check WooCommerce version
    if (defined('WC_VERSION') && version_compare(WC_VERSION, SMFW_MIN_WC_VERSION, '<')) {
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Current WooCommerce version, 2: Required WooCommerce version */
                    esc_html__('Suppliers Manager for WooCommerce requires WooCommerce %2$s or higher. You are running WooCommerce %1$s.', 'suppliers-manager-for-woocommerce'),
                    esc_html(WC_VERSION),
                    esc_html(SMFW_MIN_WC_VERSION)
                )
            );
        });
        return false;
    }

    return true;
}

/**
 * Activation hook callback
 *
 * @return void
 */
function smfw_activate(): void
{
    require_once SMFW_PLUGIN_DIR . 'includes/class-activator.php';
    Activator::activate();
}

/**
 * Deactivation hook callback
 *
 * @return void
 */
function smfw_deactivate(): void
{
    require_once SMFW_PLUGIN_DIR . 'includes/class-deactivator.php';
    Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, __NAMESPACE__ . '\\smfw_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\smfw_deactivate');

/**
 * Begin plugin execution
 *
 * @return void
 */
function smfw_run(): void
{
    // Check system requirements
    if (!smfw_check_requirements()) {
        return;
    }

    // Load plugin core class
    require_once SMFW_PLUGIN_DIR . 'includes/class-plugin.php';

    // Initialize plugin
    $plugin = new Plugin();
    $plugin->run();
}

// Initialize plugin
add_action('plugins_loaded', __NAMESPACE__ . '\\smfw_run', 10);

/**
 * Declare HPOS compatibility
 *
 * @return void
 */
add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
