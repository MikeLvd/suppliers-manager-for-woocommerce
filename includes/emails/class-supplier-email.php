<?php
/**
 * Supplier Email Class for CPT
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes\Emails
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\Emails;

use WC_Email;
use WC_Order;
use Suppliers_Manager_For_WooCommerce\Email_Logger;
use Suppliers_Manager_For_WooCommerce\Supplier_Relationships;

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WC_Email')) {
	return;
}

/**
 * Supplier Email class
 *
 * @since  3.0.0
 * @extends WC_Email
 */
class Supplier_Email extends WC_Email
{
	protected int $current_supplier_id = 0;
	protected string $current_supplier_name = '';
	protected array $current_order_items = [];
	protected Supplier_Relationships $relationships;

	public function __construct()
	{
		$this->id = 'smfw_supplier_email';
		$this->customer_email = false;
		$this->title = __('Supplier Order Notification', 'suppliers-manager-for-woocommerce');
		$this->description = __(
			'Email notifications sent to suppliers when orders containing their products are placed.',
			'suppliers-manager-for-woocommerce'
		);

		$this->template_html = 'emails/supplier-notification.php';
		$this->template_plain = 'emails/plain/supplier-notification.php';
		$this->template_base = SMFW_PLUGIN_DIR . 'templates/';

		$this->placeholders = [
			'{site_title}'    => $this->get_blogname(),
			'{order_date}'    => '',
			'{order_number}'  => '',
			'{supplier_name}' => '',
		];

		$this->relationships = new Supplier_Relationships();

		parent::__construct();

		add_action('smfw_notify_supplier', [$this, 'trigger'], 10, 2);
	}

	public function trigger($order_id, $order = null): void
	{
		if ($order_id && !is_a($order, 'WC_Order')) {
			$order = wc_get_order($order_id);
		}

		if (!is_a($order, 'WC_Order') || !$this->is_enabled()) {
			return;
		}

		$this->setup_locale();
		$this->object = $order;

		$this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
		$this->placeholders['{order_number}'] = $this->object->get_order_number();

		$suppliers_items = $this->group_items_by_supplier($order);

		if (!empty($suppliers_items)) {
			foreach ($suppliers_items as $supplier_id => $item_ids) {
				$this->send_supplier_email($supplier_id, $item_ids, $order);
			}
		}

		$this->restore_locale();
	}

	protected function group_items_by_supplier(WC_Order $order): array
	{
		$suppliers = [];

		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			if (!$product) {
				continue;
			}

			$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
			$supplier_ids = $this->relationships->get_product_suppliers($product_id);

			if (empty($supplier_ids)) {
				continue;
			}

			foreach ($supplier_ids as $supplier_id) {
				if (!isset($suppliers[$supplier_id])) {
					$suppliers[$supplier_id] = [];
				}
				$suppliers[$supplier_id][] = $item_id;
			}
		}

		return $suppliers;
	}

	protected function send_supplier_email(int $supplier_id, array $item_ids, WC_Order $order): void
	{
		$supplier = get_post($supplier_id);
		if (!$supplier || $supplier->post_status !== 'publish') {
			return;
		}

		$supplier_email = get_post_meta($supplier_id, '_supplier_email', true);
		if (empty($supplier_email) || !is_email($supplier_email)) {
			$this->log_email_attempt($order, $supplier_id, $supplier->post_title, $supplier_email, $item_ids, 'failed');
			return;
		}

		$this->current_supplier_id = $supplier_id;
		$this->current_supplier_name = $supplier->post_title;
		$this->current_order_items = $item_ids;
		$this->placeholders['{supplier_name}'] = $supplier->post_title;
		$this->recipient = $supplier_email;

		$bcc_admin = get_option('smfw_bcc_admin', '1');
		$bcc_enabled = ($bcc_admin === '1' || $bcc_admin === 1 || $bcc_admin === true);
		$admin_email = get_option('smfw_admin_email', get_option('admin_email'));

		$headers = $this->get_headers();
		if (is_array($headers)) {
			$headers = implode("\r\n", $headers);
		}

		if ($bcc_enabled && is_email($admin_email) && $admin_email !== $supplier_email) {
			$headers .= "\r\nBCC: " . sanitize_email($admin_email);
		}

		try {
			$result = wp_mail(
				$supplier_email,
				$this->get_subject(),
				$this->get_content(),
				$headers,
				$this->get_attachments()
			);

			$status = $result ? 'sent' : 'failed';
			$this->log_email_attempt($order, $supplier_id, $supplier->post_title, $supplier_email, $item_ids, $status);

		} catch (\Exception $e) {
			$this->log_email_attempt($order, $supplier_id, $supplier->post_title, $supplier_email, $item_ids, 'failed');
		}
	}

	protected function log_email_attempt(
		WC_Order $order,
		int $supplier_id,
		string $supplier_name,
		string $supplier_email,
		array $item_ids,
		string $status
	): void {
		$logging_enabled = get_option('smfw_enable_email_history', '1');
		if ($logging_enabled !== '1' && $logging_enabled !== 1 && $logging_enabled !== true) {
			return;
		}

		try {
			$logger = new Email_Logger();
			$logger->log_email(
				$order->get_id(),
				$supplier_id,
				$supplier_name,
				$supplier_email,
				$this->recipient ?? $supplier_email,
				$this->get_subject(),
				count($item_ids),
				$status
			);
		} catch (\Exception $e) {
			// Silent fail on logging errors
		}
	}

	public function get_default_subject(): string
	{
		return __('New order #{order_number} from {site_title}', 'suppliers-manager-for-woocommerce');
	}

	public function get_default_heading(): string
	{
		return __('New Order Notification', 'suppliers-manager-for-woocommerce');
	}

	public function get_content_html(): string
	{
		return wc_get_template_html(
			$this->template_html,
			[
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'supplier_name'      => $this->current_supplier_name,
				'supplier_id'        => $this->current_supplier_id,
				'order_items'        => $this->current_order_items,
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string
	{
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'supplier_name'      => $this->current_supplier_name,
				'supplier_id'        => $this->current_supplier_id,
				'order_items'        => $this->current_order_items,
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	public function init_form_fields(): void
	{
		$this->form_fields = [
			'enabled'            => [
				'title'   => __('Enable/Disable', 'suppliers-manager-for-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable this email notification', 'suppliers-manager-for-woocommerce'),
				'default' => 'yes',
			],
			'subject'            => [
				'title'       => __('Subject', 'suppliers-manager-for-woocommerce'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf(
					__('Available placeholders: %s', 'suppliers-manager-for-woocommerce'),
					'<code>{site_title}, {order_number}, {order_date}, {supplier_name}</code>'
				),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			],
			'heading'            => [
				'title'       => __('Email heading', 'suppliers-manager-for-woocommerce'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf(
					__('Available placeholders: %s', 'suppliers-manager-for-woocommerce'),
					'<code>{site_title}, {order_number}, {order_date}, {supplier_name}</code>'
				),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			],
			'additional_content' => [
				'title'       => __('Additional content', 'suppliers-manager-for-woocommerce'),
				'description' => __('Text to appear below the order table.', 'suppliers-manager-for-woocommerce'),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __('N/A', 'suppliers-manager-for-woocommerce'),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			],
			'email_type'         => [
				'title'       => __('Email type', 'suppliers-manager-for-woocommerce'),
				'type'        => 'select',
				'description' => __('Choose format for the email to be sent.', 'suppliers-manager-for-woocommerce'),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}

	public function get_recipient(): string
	{
		return $this->recipient ?? '';
	}
}