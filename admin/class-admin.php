<?php
/**
 * Admin Class for CPT Architecture
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Admin
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\Admin;

use WC_Order;
use Suppliers_Manager_For_WooCommerce\Supplier_Relationships;

/**
 * Admin class
 *
 * @since 3.0.0
 */
class Admin
{
	private string $plugin_name;
	private string $version;
	private Supplier_Relationships $relationships;

	public function __construct(string $plugin_name, string $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->relationships = new Supplier_Relationships();
	}

	public function add_product_columns(array $columns): array
	{
		$new_columns = [];

		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;

			if ($key === 'product_tag') {
				$new_columns['supplier'] = __('Suppliers', 'suppliers-manager-for-woocommerce');
			}
		}

		return $new_columns;
	}

	public function render_product_column_content(string $column, int $post_id): void
	{
		if ($column !== 'supplier') {
			return;
		}

		$supplier_ids = $this->relationships->get_product_suppliers($post_id);

		if (empty($supplier_ids)) {
			echo '—';
			return;
		}

		$output = [];
		foreach ($supplier_ids as $supplier_id) {
			$supplier = get_post($supplier_id);
			if ($supplier) {
				$output[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(get_edit_post_link($supplier_id)),
					esc_html($supplier->post_title)
				);
			}
		}

		echo wp_kses_post(implode(', ', $output));
	}

	public function add_product_fields(): void
	{
		global $post;

		echo '<div class="options_group">';

		woocommerce_wp_checkbox([
			'id'          => 'smfw_notify_supplier',
			'label'       => __('Notify supplier on low stock', 'suppliers-manager-for-woocommerce'),
			'description' => __('Check this to notify the supplier when this product is out of stock', 'suppliers-manager-for-woocommerce'),
			'desc_tip'    => true,
			'value'       => get_post_meta($post->ID, '_smfw_notify_supplier', true) ?: 'no',
		]);

		echo '</div>';
	}

	public function save_product_fields(int $post_id): void
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (!current_user_can('edit_product', $post_id)) {
			return;
		}

		$notify_supplier = isset($_POST['smfw_notify_supplier']) ? 'yes' : 'no';
		update_post_meta($post_id, '_smfw_notify_supplier', $notify_supplier);
	}

	public function register_email_class(array $emails): array
	{
		require_once SMFW_PLUGIN_DIR . 'includes/emails/class-supplier-email.php';
		$emails['SMFW_Supplier_Email'] = new \Suppliers_Manager_For_WooCommerce\Emails\Supplier_Email();

		return $emails;
	}

	public function add_order_actions(array $actions): array
	{
		$actions['smfw_notify_supplier'] = __('Notify suppliers', 'suppliers-manager-for-woocommerce');
		return $actions;
	}

	public function process_notify_supplier_action(WC_Order $order): void
	{
		$this->send_supplier_notifications($order->get_id());
	}

	public function handle_order_status_change(int $order_id, string $old_status, string $new_status): void
	{
		$notification_status = get_option('smfw_notification_status', 'processing');

		if ($new_status === $notification_status) {
			$this->send_supplier_notifications($order_id);
		}
	}

	private function send_supplier_notifications(int $order_id): void
	{
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		$mailer = WC()->mailer();
		if (!$mailer) {
			return;
		}

		$emails = $mailer->get_emails();
		if (empty($emails)) {
			return;
		}

		foreach ($emails as $email) {
			if ($email->id === 'smfw_supplier_email' && $email->is_enabled()) {
				do_action('smfw_notify_supplier', $order_id, $order);
				break;
			}
		}
	}

	public function filter_products_by_supplier(\WP_Query $query): void
	{
		global $pagenow;

		if (!is_admin() || $pagenow !== 'edit.php') {
			return;
		}

		if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
			return;
		}

		if (!isset($_GET['supplier_id']) || empty($_GET['supplier_id'])) {
			return;
		}

		$supplier_id = (int) $_GET['supplier_id'];
		$product_ids = $this->relationships->get_supplier_products($supplier_id);

		if (empty($product_ids)) {
			$query->set('post__in', [0]);
		} else {
			$query->set('post__in', $product_ids);
		}
	}
}