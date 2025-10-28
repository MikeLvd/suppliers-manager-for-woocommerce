<?php
/**
 * Core Plugin Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

/**
 * Core plugin class
 *
 * @since 3.0.0
 */
class Plugin
{
	protected Loader $loader;
	protected string $plugin_name;
	protected string $version;

	public function __construct()
	{
		$this->version = defined('SMFW_VERSION') ? SMFW_VERSION : '3.0.1';
		$this->plugin_name = 'suppliers-manager-for-woocommerce';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies(): void
	{
		require_once SMFW_PLUGIN_DIR . 'includes/class-loader.php';
		require_once SMFW_PLUGIN_DIR . 'includes/class-i18n.php';
		require_once SMFW_PLUGIN_DIR . 'includes/class-email-logger.php';
		require_once SMFW_PLUGIN_DIR . 'includes/class-supplier-relationships.php';

		require_once SMFW_PLUGIN_DIR . 'admin/class-supplier-post-type.php';
		require_once SMFW_PLUGIN_DIR . 'admin/class-supplier-meta-boxes.php';
		require_once SMFW_PLUGIN_DIR . 'admin/class-product-meta-boxes.php';
		require_once SMFW_PLUGIN_DIR . 'admin/class-admin.php';
		require_once SMFW_PLUGIN_DIR . 'admin/class-settings.php';
		require_once SMFW_PLUGIN_DIR . 'admin/class-email-history.php';

		require_once SMFW_PLUGIN_DIR . 'public/class-public.php';

		$this->loader = new Loader();
	}

	private function set_locale(): void
	{
		$plugin_i18n = new I18n();
		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	private function define_admin_hooks(): void
	{
		$supplier_cpt = new Admin\Supplier_Post_Type();
		$this->loader->add_action('init', $supplier_cpt, 'register_post_type');
		$this->loader->add_filter('manage_supplier_posts_columns', $supplier_cpt, 'add_custom_columns');
		$this->loader->add_action('manage_supplier_posts_custom_column', $supplier_cpt, 'render_custom_columns', 10, 2);
		$this->loader->add_filter('manage_edit-supplier_sortable_columns', $supplier_cpt, 'make_columns_sortable');
		$this->loader->add_action('pre_get_posts', $supplier_cpt, 'handle_column_sorting');
		$this->loader->add_action('restrict_manage_posts', $supplier_cpt, 'add_filter_dropdowns');
		$this->loader->add_filter('enter_title_here', $supplier_cpt, 'customize_title_placeholder', 10, 2);
		$this->loader->add_filter('dashboard_glance_items', $supplier_cpt, 'add_glance_item');
		$this->loader->add_action('before_delete_post', $supplier_cpt, 'delete_supplier_relationships');

		$supplier_meta_boxes = new Admin\Supplier_Meta_Boxes();
		$this->loader->add_action('add_meta_boxes', $supplier_meta_boxes, 'add_meta_boxes');
		$this->loader->add_action('save_post_supplier', $supplier_meta_boxes, 'save_contact_info', 10, 2);

		$product_meta_boxes = new Admin\Product_Meta_Boxes();
		$this->loader->add_action('add_meta_boxes', $product_meta_boxes, 'add_meta_boxes');
		$this->loader->add_action('save_post_product', $product_meta_boxes, 'save_suppliers', 10, 2);

		$admin = new Admin\Admin($this->get_plugin_name(), $this->get_version());
		$settings = new Admin\Settings($this->get_plugin_name());
		$email_history = new Admin\Email_History($this->get_plugin_name());

		$this->loader->add_filter('manage_edit-product_columns', $admin, 'add_product_columns');
		$this->loader->add_action('manage_product_posts_custom_column', $admin, 'render_product_column_content', 10, 2);

		$this->loader->add_action('woocommerce_product_options_general_product_data', $admin, 'add_product_fields');
		$this->loader->add_action('woocommerce_process_product_meta', $admin, 'save_product_fields');

		$this->loader->add_filter('woocommerce_email_classes', $admin, 'register_email_class', 90, 1);

		$this->loader->add_filter('woocommerce_order_actions', $admin, 'add_order_actions', 10, 1);
		$this->loader->add_action('woocommerce_order_action_smfw_notify_supplier', $admin, 'process_notify_supplier_action');

		$this->loader->add_action('woocommerce_order_status_changed', $admin, 'handle_order_status_change', 10, 3);

		$this->loader->add_action('admin_menu', $settings, 'add_settings_page');
		$this->loader->add_action('admin_init', $settings, 'register_settings');

		$this->loader->add_action('admin_menu', $email_history, 'add_history_page');

		$this->loader->add_action('smfw_cleanup_email_history', $this, 'cleanup_email_history');

		$this->loader->add_action('pre_get_posts', $admin, 'filter_products_by_supplier');
	}

	private function define_public_hooks(): void
	{
		$public = new PublicFrontend\PublicFrontend($this->get_plugin_name(), $this->get_version());
	}

	public function run(): void
	{
		$this->loader->run();
	}

	public function get_plugin_name(): string
	{
		return $this->plugin_name;
	}

	public function get_loader(): Loader
	{
		return $this->loader;
	}

	public function get_version(): string
	{
		return $this->version;
	}

	public function cleanup_email_history(): void
	{
		$retention_days = (int) get_option('smfw_history_retention_days', 90);

		$logger = new Email_Logger();
		$logger->cleanup_old_history($retention_days);
	}
}