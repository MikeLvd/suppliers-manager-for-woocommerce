<?php
/**
 * Plugin Activator Class (Clean Version - Post Migration)
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      3.0.1
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

/**
 * Activator class
 *
 * Handles plugin activation and database setup.
 * Migration code removed - only for post-migration installations.
 *
 * @since 3.0.1
 */
class Activator
{
	/**
	 * Execute activation tasks
	 *
	 * @since  3.0.1
	 * @return void
	 */
	public static function activate(): void
	{
		// Load required classes
		if (!class_exists('Suppliers_Manager_For_WooCommerce\Email_Logger')) {
			require_once SMFW_PLUGIN_DIR . 'includes/class-email-logger.php';
		}
		if (!class_exists('Suppliers_Manager_For_WooCommerce\Supplier_Relationships')) {
			require_once SMFW_PLUGIN_DIR . 'includes/class-supplier-relationships.php';
		}

		// Create email history table
		$logger = new Email_Logger();
		$logger->create_table();

		// Create supplier relationships table
		$relationships = new Supplier_Relationships();
		$relationships->create_table();

		// Flush rewrite rules to ensure custom post types work
		flush_rewrite_rules();

		// Set plugin version
		update_option('smfw_version', SMFW_VERSION);

		// Set default settings if they don't exist
		if (false === get_option('smfw_notification_status')) {
			update_option('smfw_notification_status', 'processing');
		}

		if (false === get_option('smfw_enable_email_history')) {
			update_option('smfw_enable_email_history', '1');
		}

		if (false === get_option('smfw_bcc_admin')) {
			update_option('smfw_bcc_admin', '1');
		}

		if (false === get_option('smfw_admin_email')) {
			update_option('smfw_admin_email', get_option('admin_email'));
		}

		if (false === get_option('smfw_history_retention_days')) {
			update_option('smfw_history_retention_days', 90);
		}

		// Schedule cleanup cron job
		if (!wp_next_scheduled('smfw_cleanup_email_history')) {
			wp_schedule_event(time(), 'daily', 'smfw_cleanup_email_history');
		}

		// Log activation
		if (function_exists('wc_get_logger')) {
			$wc_logger = wc_get_logger();
			$message = 'Suppliers Manager for WooCommerce v' . SMFW_VERSION . ' activated';
			$wc_logger->info($message, ['source' => 'suppliers-manager']);
		}
	}
}