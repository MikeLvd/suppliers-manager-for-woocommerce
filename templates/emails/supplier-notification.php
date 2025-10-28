<?php
/**
 * Supplier notification email template (HTML)
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Templates\Emails
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
?>

<p>
    <?php
    /* translators: %s: Supplier name */
    printf(esc_html__('Hello %s,', 'suppliers-manager-for-woocommerce'), esc_html($supplier_name));
    ?>
</p>

<p>
    <?php esc_html_e('A new order has been placed with products from your inventory. Please find the order details below:', 'suppliers-manager-for-woocommerce'); ?>
</p>

<h2>
    <?php
    /* translators: %s: Order number */
    printf(esc_html__('Order #%s', 'suppliers-manager-for-woocommerce'), esc_html($order->get_order_number()));
    ?>
</h2>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;" border="1" bordercolor="#eee">
    <thead>
        <tr>
            <th scope="col" style="text-align: left; padding: 12px; border: 1px solid #eee;">
                <?php esc_html_e('Product', 'suppliers-manager-for-woocommerce'); ?>
            </th>
            <th scope="col" style="text-align: left; padding: 12px; border: 1px solid #eee;">
                <?php esc_html_e('SKU', 'suppliers-manager-for-woocommerce'); ?>
            </th>
            <th scope="col" style="text-align: center; padding: 12px; border: 1px solid #eee;">
                <?php esc_html_e('Quantity', 'suppliers-manager-for-woocommerce'); ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
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
            ?>
            <tr>
                <td style="text-align: left; vertical-align: middle; padding: 12px; border: 1px solid #eee;">
                    <?php echo esc_html($item->get_name()); ?>
                    <?php
                    // Display variation attributes
                    $formatted_meta = $item->get_formatted_meta_data();
                    if (!empty($formatted_meta)) {
                        echo '<br><small>';
                        foreach ($formatted_meta as $meta) {
                            echo '<strong>' . esc_html($meta->display_key) . ':</strong> ' . wp_kses_post($meta->display_value) . '<br>';
                        }
                        echo '</small>';
                    }
                    ?>
                </td>
                <td style="text-align: left; vertical-align: middle; padding: 12px; border: 1px solid #eee;">
                    <?php echo esc_html($actual_product->get_sku() ?: __('N/A', 'suppliers-manager-for-woocommerce')); ?>
                </td>
                <td style="text-align: center; vertical-align: middle; padding: 12px; border: 1px solid #eee;">
                    <strong><?php echo esc_html($item->get_quantity()); ?></strong>
                </td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>

<?php if ($additional_content) : ?>
    <div style="margin-top: 20px;">
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
    </div>
<?php endif; ?>

<p style="margin-top: 20px;">
    <?php
    /* translators: %s: Site admin email */
    printf(
        esc_html__('If you have any questions, please contact us at %s', 'suppliers-manager-for-woocommerce'),
        '<a href="mailto:' . esc_attr(get_option('admin_email')) . '">' . esc_html(get_option('admin_email')) . '</a>'
    );
    ?>
</p>

<p>
    <?php esc_html_e('Thank you for your continued partnership.', 'suppliers-manager-for-woocommerce'); ?>
</p>

<?php
/**
 * Email footer
 *
 * @hooked WC_Emails::email_footer()
 */
do_action('woocommerce_email_footer', $email);
