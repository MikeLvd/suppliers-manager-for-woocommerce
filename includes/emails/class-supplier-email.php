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

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WC_Email class exists
if (!class_exists('WC_Email')) {
    return;
}

/**
 * Supplier Email class
 *
 * Handles email notifications sent to suppliers when orders are placed.
 * Updated for v3.0.0 to work with Custom Post Type architecture.
 *
 * @since  3.0.0
 * @extends WC_Email
 */
class Supplier_Email extends WC_Email
{
    /**
     * Current supplier post ID
     *
     * @var int
     */
    protected int $current_supplier_id = 0;

    /**
     * Current supplier name
     *
     * @var string
     */
    protected string $current_supplier_name = '';

    /**
     * Current order items for supplier
     *
     * @var array<int, int>
     */
    protected array $current_order_items = [];

    /**
     * Supplier relationships instance
     *
     * @var Supplier_Relationships
     */
    protected Supplier_Relationships $relationships;

    /**
     * Constructor
     *
     * @since 3.0.0
     */
    public function __construct()
    {
        $this->id = 'smfw_supplier_email';
        $this->customer_email = false;
        $this->title = __('Supplier Order Notification', 'suppliers-manager-for-woocommerce');
        $this->description = __(
            'Email notifications sent to suppliers when orders containing their products are placed.',
            'suppliers-manager-for-woocommerce'
        );

        // Email templates
        $this->template_html = 'emails/supplier-notification.php';
        $this->template_plain = 'emails/plain/supplier-notification.php';
        $this->template_base = SMFW_PLUGIN_DIR . 'templates/';

        // Placeholders
        $this->placeholders = [
            '{site_title}'    => $this->get_blogname(),
            '{order_date}'    => '',
            '{order_number}'  => '',
            '{supplier_name}' => '',
        ];

        // Initialize relationships
        $this->relationships = new Supplier_Relationships();

        // Trigger on custom action
        add_action('smfw_notify_supplier', [$this, 'trigger'], 10, 2);

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Trigger email notification
     *
     * @since  3.0.0
     * @param  int           $order_id Order ID
     * @param  WC_Order|null $order    Order object (optional)
     * @return void
     */
    public function trigger($order_id, $order = null): void
    {
        // Setup order object
        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $this->setup_locale();
        $this->object = $order;

        // Update placeholders
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();

        // Group order items by supplier
        $suppliers_items = $this->group_items_by_supplier($order);

        if (empty($suppliers_items)) {
            $this->restore_locale();
            return;
        }

        // Send email to each supplier
        foreach ($suppliers_items as $supplier_id => $item_ids) {
            $this->send_supplier_email($supplier_id, $item_ids, $order);
        }

        $this->restore_locale();
    }

    /**
     * Group order items by supplier (CPT version)
     *
     * @since  3.0.0
     * @param  WC_Order $order Order object
     * @return array<int, array<int, int>> Supplier post ID => Item IDs array
     */
    protected function group_items_by_supplier(WC_Order $order): array
    {
        $suppliers = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            // Get product ID (handle variations)
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

            // Get supplier post IDs from relationships table
            $supplier_ids = $this->relationships->get_product_suppliers($product_id);

            if (empty($supplier_ids)) {
                continue;
            }

            // Group items by supplier
            foreach ($supplier_ids as $supplier_id) {
                if (!isset($suppliers[$supplier_id])) {
                    $suppliers[$supplier_id] = [];
                }
                $suppliers[$supplier_id][] = $item_id;
            }
        }

        return $suppliers;
    }

    /**
     * Send email to a specific supplier
     *
     * @since  3.0.0
     * @param  int      $supplier_id Supplier post ID
     * @param  array    $item_ids    Order item IDs
     * @param  WC_Order $order       Order object
     * @return void
     */
    protected function send_supplier_email(int $supplier_id, array $item_ids, WC_Order $order): void
    {
        // Get supplier post
        $supplier = get_post($supplier_id);

        if (!$supplier || $supplier->post_status !== 'publish') {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->warning(
                    sprintf('Supplier post %d not found or not published', $supplier_id),
                    ['source' => 'suppliers-manager']
                );
            }
            return;
        }

        // Get supplier email
        $supplier_email = get_post_meta($supplier_id, '_supplier_email', true);

        if (empty($supplier_email) || !is_email($supplier_email)) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->warning(
                    sprintf('Invalid supplier email for supplier ID %d (%s)', $supplier_id, $supplier->post_title),
                    ['source' => 'suppliers-manager']
                );
            }

            // Log failed email
            $this->log_email_attempt($order, $supplier_id, $supplier->post_title, $supplier_email, $item_ids, 'failed');
            return;
        }

        // Set current supplier data for template
        $this->current_supplier_id = $supplier_id;
        $this->current_supplier_name = $supplier->post_title;
        $this->current_order_items = $item_ids;
        $this->placeholders['{supplier_name}'] = $supplier->post_title;

        // Send email
        if ($this->is_enabled()) {
            // Set recipient property
            $this->recipient = $supplier_email;

            // Get BCC settings
            $bcc_admin = get_option('smfw_bcc_admin', true);
            $bcc_enabled = ($bcc_admin === true || $bcc_admin === 1 || $bcc_admin === '1');
            $admin_email = get_option('smfw_admin_email', get_option('admin_email'));
            
            // Get headers
            $headers = $this->get_headers();
            
            // Add BCC if enabled
            if ($bcc_enabled && is_email($admin_email) && $admin_email !== $supplier_email) {
                if (is_array($headers)) {
                    $headers = implode("\r\n", $headers) . "\r\n";
                }
                $headers .= 'BCC: ' . sanitize_email($admin_email) . "\r\n";
            }
            
            // Send email
            $result = $this->send(
                $supplier_email,
                $this->get_subject(),
                $this->get_content(),
                $headers,
                $this->get_attachments()
            );

            // Determine status
            $status = $result ? 'sent' : 'failed';

            // Log email attempt
            $this->log_email_attempt($order, $supplier_id, $supplier->post_title, $supplier_email, $item_ids, $status);

            // Log to WooCommerce logger
            if (function_exists('wc_get_logger')) {
                $wc_logger = wc_get_logger();
                if ($result) {
                    $message = sprintf(
                        'Supplier notification sent to %s (%s) for order #%s',
                        $supplier->post_title,
                        $supplier_email,
                        $order->get_order_number()
                    );
                    if ($bcc_enabled && is_email($admin_email)) {
                        $message .= sprintf(' (BCC: %s)', $admin_email);
                    }
                    $wc_logger->info($message, ['source' => 'suppliers-manager']);
                } else {
                    $wc_logger->error(
                        sprintf(
                            'Failed to send supplier notification to %s (%s) for order #%s',
                            $supplier->post_title,
                            $supplier_email,
                            $order->get_order_number()
                        ),
                        ['source' => 'suppliers-manager']
                    );
                }
            }
        }
    }

    /**
     * Log email attempt to database
     *
     * @since  3.0.0
     * @param  WC_Order $order          Order object
     * @param  int      $supplier_id    Supplier post ID
     * @param  string   $supplier_name  Supplier name
     * @param  string   $supplier_email Supplier email
     * @param  array    $item_ids       Order item IDs
     * @param  string   $status         Email status (sent/failed)
     * @return void
     */
    protected function log_email_attempt(
        WC_Order $order,
        int $supplier_id,
        string $supplier_name,
        string $supplier_email,
        array $item_ids,
        string $status
    ): void {
        // Check if logging is enabled
        $logging_enabled = get_option('smfw_enable_email_history', true);
        if ($logging_enabled !== true && $logging_enabled !== 1 && $logging_enabled !== '1') {
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
            // Log error but don't stop email sending
            if (function_exists('wc_get_logger')) {
                $wc_logger = wc_get_logger();
                $wc_logger->error(
                    'Failed to log email to history: ' . $e->getMessage(),
                    ['source' => 'suppliers-manager']
                );
            }
        }
    }

    /**
     * Get email subject
     *
     * @since  3.0.0
     * @return string Email subject
     */
    public function get_default_subject(): string
    {
        return __('New order #{order_number} from {site_title}', 'suppliers-manager-for-woocommerce');
    }

    /**
     * Get email heading
     *
     * @since  3.0.0
     * @return string Email heading
     */
    public function get_default_heading(): string
    {
        return __('New Order Notification', 'suppliers-manager-for-woocommerce');
    }

    /**
     * Get email content (HTML)
     *
     * @since  3.0.0
     * @return string HTML email content
     */
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

    /**
     * Get email content (plain text)
     *
     * @since  3.0.0
     * @return string Plain text email content
     */
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

    /**
     * Initialize form fields for email settings
     *
     * @since  3.0.0
     * @return void
     */
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
                    /* translators: %s: List of placeholder tags */
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
                    /* translators: %s: List of placeholder tags */
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

    /**
     * Get email recipient
     *
     * @since  3.0.0
     * @return string Recipient email address
     */
    public function get_recipient(): string
    {
        return $this->recipient ?? '';
    }
}
