<?php
/**
 * Uninstall script for Suppliers Manager for WooCommerce
 *
 * @package Suppliers_Manager_For_WooCommerce
 * @since   3.0.0
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Clean up plugin data on uninstall
 *
 * @since  3.0.0
 * @return void
 */
function smfw_uninstall_cleanup(): void
{
	global $wpdb;

	// Check if user wants to delete data
	$delete_data = get_option('smfw_delete_data_on_uninstall', 'no');
	if ($delete_data !== 'yes') {
		return;
	}

	// Delete all supplier posts (CPT)
	$suppliers = get_posts([
		'post_type'      => 'supplier',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);

	if (!empty($suppliers)) {
		foreach ($suppliers as $supplier_id) {
			// Force delete (bypass trash)
			wp_delete_post($supplier_id, true);
		}
	}

	// Delete plugin options
	delete_option('smfw_version');
	delete_option('smfw_notification_status');
	delete_option('smfw_delete_data_on_uninstall');
	delete_option('smfw_enable_email_history');
	delete_option('smfw_bcc_admin');
	delete_option('smfw_admin_email');
	delete_option('smfw_history_retention_days');
	delete_option('smfw_migration_stats');

	// Delete custom database tables
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smfw_product_suppliers");
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}smfw_email_history");

	// Delete product meta added by plugin
	$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_smfw_notify_supplier'");

	// Clear scheduled cron jobs
	wp_clear_scheduled_hook('smfw_check_stock_availability');
	wp_clear_scheduled_hook('smfw_cleanup_email_history');

	// Flush rewrite rules (cleanup)
	flush_rewrite_rules();
}

// Execute cleanup
smfw_uninstall_cleanup();