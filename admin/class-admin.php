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
 * Handles admin-specific functionality including product columns,
 * order actions, and email notifications.
 *
 * @since 3.0.0
 */
class Admin
{
    /**
     * Plugin identifier
     *
     * @var string
     */
    private string $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     */
    private string $version;

    /**
     * Supplier relationships instance
     *
     * @var Supplier_Relationships
     */
    private Supplier_Relationships $relationships;

    /**
     * Initialize the class
     *
     * @since 3.0.0
     * @param string $plugin_name Plugin identifier
     * @param string $version     Plugin version
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->relationships = new Supplier_Relationships();
    }

    /**
     * Add custom columns to product list table
     *
     * @since  3.0.0
     * @param  array<string, string> $columns Existing columns
     * @return array<string, string> Modified columns
     */
    public function add_product_columns(array $columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            // Add supplier column after product_tag
            if ($key === 'product_tag') {
                $new_columns['supplier'] = __('Suppliers', 'suppliers-manager-for-woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * Render supplier column content in product list
     *
     * @since  3.0.0
     * @param  string $column  Column name
     * @param  int    $post_id Post ID
     * @return void
     */
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

    /**
     * Add custom fields to product general tab
     *
     * @since  3.0.0
     * @return void
     */
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

    /**
     * Save product custom fields
     *
     * @since  3.0.0
     * @param  int $post_id Product ID
     * @return void
     */
    public function save_product_fields(int $post_id): void
    {
        // Security checks
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_product', $post_id)) {
            return;
        }

        // Save notify supplier checkbox
        $notify_supplier = isset($_POST['smfw_notify_supplier']) ? 'yes' : 'no';
        update_post_meta($post_id, '_smfw_notify_supplier', $notify_supplier);
    }

    /**
     * Register custom email class
     *
     * @since  3.0.0
     * @param  array<string, object> $emails Existing email classes
     * @return array<string, object> Modified email classes
     */
    public function register_email_class(array $emails): array
    {
        require_once SMFW_PLUGIN_DIR . 'includes/emails/class-supplier-email.php';
        $emails['SMFW_Supplier_Email'] = new \Suppliers_Manager_For_WooCommerce\Emails\Supplier_Email();

        return $emails;
    }

    /**
     * Add custom order actions
     *
     * @since  3.0.0
     * @param  array<string, string> $actions Existing order actions
     * @return array<string, string> Modified order actions
     */
    public function add_order_actions(array $actions): array
    {
        $actions['smfw_notify_supplier'] = __('Notify suppliers', 'suppliers-manager-for-woocommerce');
        return $actions;
    }

    /**
     * Process notify supplier order action
     *
     * @since  3.0.0
     * @param  WC_Order $order Order object
     * @return void
     */
    public function process_notify_supplier_action(WC_Order $order): void
    {
        $this->send_supplier_notifications($order->get_id());
    }

    /**
     * Handle order status change
     *
     * @since  3.0.0
     * @param  int    $order_id   Order ID
     * @param  string $old_status Old status
     * @param  string $new_status New status
     * @return void
     */
    public function handle_order_status_change(int $order_id, string $old_status, string $new_status): void
    {
        // Get configured notification status (default to 'processing')
        $notification_status = get_option('smfw_notification_status', 'processing');

        // Check if we should send notifications for this status change
        if ($new_status === $notification_status) {
            $this->send_supplier_notifications($order_id);
        }
    }

    /**
     * Send notifications to suppliers
     *
     * @since  3.0.0
     * @param  int $order_id Order ID
     * @return void
     */
    private function send_supplier_notifications(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        if (empty($emails)) {
            return;
        }

        foreach ($emails as $email) {
            if ($email->id === 'smfw_supplier_email') {
                $email->trigger($order_id);
                break;
            }
        }
    }

    /**
     * Filter products by supplier in admin
     *
     * @since  3.0.0
     * @param  \WP_Query $query Query object
     * @return void
     */
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

        // Get products for this supplier
        $product_ids = $this->relationships->get_supplier_products($supplier_id);

        if (empty($product_ids)) {
            // No products found, show nothing
            $query->set('post__in', [0]);
        } else {
            $query->set('post__in', $product_ids);
        }
    }
}
