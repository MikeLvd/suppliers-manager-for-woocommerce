<?php
/**
 * Supplier notification email template (Plain Text)
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Templates\Emails\Plain
 * @version    2.0.0
 *
 * @var WC_Order $order              Order object
 * @var string   $email_heading      Email heading
 * @var string   $supplier_name      Supplier name
 * @var int      $supplier_id        Supplier term ID
 * @var array    $order_items        Order item IDs
 * @var string   $additional_content Additional email content
 * @var bool     $sent_to_admin      Whether sent to admin
 * @var bool     $plain_text         Whether plain text email
 * @var WC_Email $email              Email object
 */

defined('ABSPATH') || exit;

/**
 * Email header
 *
 * @hooked WC_Emails::email_header()
 */
do_action('woocommerce_email_header', $email_heading, $email);

/* translators: %s: Supplier name */
printf(esc_html__('Hello %s,', 'suppliers-manager-for-woocommerce'), esc_html($supplier_name));

echo "\n\n";

esc_html_e('A new order has been placed with products from your inventory. Please find the order details below:', 'suppliers-manager-for-woocommerce');

echo "\n\n";

echo '=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=';
echo "\n\n";

/* translators: %s: Order number */
printf(esc_html__('Order #%s', 'suppliers-manager-for-woocommerce'), esc_html($order->get_order_number()));

echo "\n\n";

// Table header
echo str_pad(esc_html__('Product', 'suppliers-manager-for-woocommerce'), 40) . ' ';
echo str_pad(esc_html__('SKU', 'suppliers-manager-for-woocommerce'), 15) . ' ';
echo str_pad(esc_html__('Quantity', 'suppliers-manager-for-woocommerce'), 10, ' ', STR_PAD_LEFT);
echo "\n";
echo str_repeat('-', 65);
echo "\n";

// Table rows
foreach ($order_items as $item_id) {
    $item = new WC_Order_Item_Product($item_id);
    $product = $item->get_product();

    if (!$product) {
        continue;
    }

    // Get proper product ID for variations
    $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
    $actual_product = wc_get_product($product_id);

    if (!$actual_product) {
        continue;
    }

    $product_name = $item->get_name();
    $sku = $actual_product->get_sku() ?: esc_html__('N/A', 'suppliers-manager-for-woocommerce');
    $quantity = $item->get_quantity();

    echo str_pad(substr($product_name, 0, 38), 40) . ' ';
    echo str_pad(substr($sku, 0, 13), 15) . ' ';
    echo str_pad((string)$quantity, 10, ' ', STR_PAD_LEFT);
    echo "\n";

    // Display variation attributes
    $formatted_meta = $item->get_formatted_meta_data();
    if (!empty($formatted_meta)) {
        foreach ($formatted_meta as $meta) {
            echo '  ' . $meta->display_key . ': ' . strip_tags($meta->display_value);
            echo "\n";
        }
    }
}

echo str_repeat('-', 65);
echo "\n\n";

// Additional content
if ($additional_content) {
    echo wp_strip_all_tags(wpautop(wptexturize($additional_content)));
    echo "\n\n";
}

/* translators: %s: Site admin email */
printf(
    esc_html__('If you have any questions, please contact us at %s', 'suppliers-manager-for-woocommerce'),
    esc_html(get_option('admin_email'))
);

echo "\n\n";

esc_html_e('Thank you for your continued partnership.', 'suppliers-manager-for-woocommerce');

echo "\n\n";

/**
 * Email footer
 *
 * @hooked WC_Emails::email_footer()
 */
do_action('woocommerce_email_footer', $email);
