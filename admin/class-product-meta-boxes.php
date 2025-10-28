<?php
/**
 * Product Meta Boxes for Supplier Assignment
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Admin
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\Admin;

use Suppliers_Manager_For_WooCommerce\Supplier_Relationships;

/**
 * Product Meta Boxes class
 *
 * Handles supplier assignment meta boxes for products.
 *
 * @since 3.0.0
 */
class Product_Meta_Boxes
{
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
     */
    public function __construct()
    {
        $this->relationships = new Supplier_Relationships();
    }

    /**
     * Add meta boxes
     *
     * @since  3.0.0
     * @return void
     */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'smfw_product_suppliers',
            __('Suppliers', 'suppliers-manager-for-woocommerce'),
            [$this, 'render_suppliers_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render suppliers meta box
     *
     * @since  3.0.0
     * @param  \WP_Post $post Post object
     * @return void
     */
    public function render_suppliers_meta_box(\WP_Post $post): void
    {
        // Nonce field
        wp_nonce_field('smfw_save_product_suppliers', 'smfw_product_suppliers_nonce');

        // Get assigned suppliers
        $assigned_suppliers = $this->relationships->get_product_suppliers($post->ID);
        $primary_supplier = $this->relationships->get_primary_supplier($post->ID);

        // Get all suppliers
        $suppliers = get_posts([
            'post_type'      => 'supplier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (empty($suppliers)) {
            ?>
            <p><?php esc_html_e('No suppliers available.', 'suppliers-manager-for-woocommerce'); ?></p>
            <p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=supplier')); ?>" class="button button-secondary">
                    <?php esc_html_e('Add Supplier', 'suppliers-manager-for-woocommerce'); ?>
                </a>
            </p>
            <?php
            return;
        }

        ?>
        <div class="smfw-product-suppliers-wrapper">
            <p class="description">
                <?php esc_html_e('Select one or more suppliers for this product.', 'suppliers-manager-for-woocommerce'); ?>
            </p>

            <div class="smfw-suppliers-list" style="margin-top: 10px; max-height: 300px; overflow-y: auto;">
                <?php foreach ($suppliers as $supplier) : ?>
                    <?php
                    $is_assigned = in_array($supplier->ID, $assigned_suppliers, true);
                    $is_primary = ($supplier->ID === $primary_supplier);
                    $supplier_email = get_post_meta($supplier->ID, '_supplier_email', true);
                    ?>
                    <div class="smfw-supplier-item" style="margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 3px; background: #f9f9f9;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" 
                                   name="product_suppliers[]" 
                                   value="<?php echo esc_attr($supplier->ID); ?>"
                                   <?php checked($is_assigned); ?>
                                   style="margin: 0 8px 0 0;">
                            
                            <?php if (has_post_thumbnail($supplier->ID)) : ?>
                                <span style="margin-right: 8px;">
                                    <?php echo get_the_post_thumbnail($supplier->ID, [30, 30]); ?>
                                </span>
                            <?php endif; ?>

                            <span style="flex: 1;">
                                <strong><?php echo esc_html($supplier->post_title); ?></strong>
                                <?php if ($supplier_email) : ?>
                                    <br>
                                    <small style="color: #666;"><?php echo esc_html($supplier_email); ?></small>
                                <?php endif; ?>
                            </span>

                            <?php if ($is_primary) : ?>
                                <span class="primary-badge" style="margin-left: 8px; padding: 2px 6px; background: #2271b1; color: #fff; border-radius: 3px; font-size: 11px;">
                                    <?php esc_html_e('Primary', 'suppliers-manager-for-woocommerce'); ?>
                                </span>
                            <?php endif; ?>
                        </label>

                        <?php if ($is_assigned) : ?>
                            <div style="margin-left: 38px; margin-top: 5px;">
                                <label style="display: flex; align-items: center;">
                                    <input type="radio" 
                                           name="primary_supplier" 
                                           value="<?php echo esc_attr($supplier->ID); ?>"
                                           <?php checked($is_primary); ?>
                                           style="margin: 0 5px 0 0;">
                                    <small><?php esc_html_e('Set as primary', 'suppliers-manager-for-woocommerce'); ?></small>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p style="margin-top: 15px;">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=supplier')); ?>" 
                   class="button button-secondary" target="_blank">
                    <?php esc_html_e('Add New Supplier', 'suppliers-manager-for-woocommerce'); ?>
                </a>
            </p>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Auto-check primary radio when supplier is checked
            $('.smfw-supplier-item input[type="checkbox"]').on('change', function() {
                var $checkbox = $(this);
                var $container = $checkbox.closest('.smfw-supplier-item');
                var $radioContainer = $container.find('div');
                
                if ($checkbox.is(':checked')) {
                    $radioContainer.show();
                } else {
                    $radioContainer.hide();
                    $radioContainer.find('input[type="radio"]').prop('checked', false);
                }
            });

            // Initialize visibility
            $('.smfw-supplier-item input[type="checkbox"]').each(function() {
                var $checkbox = $(this);
                var $container = $checkbox.closest('.smfw-supplier-item');
                var $radioContainer = $container.find('div');
                
                if (!$checkbox.is(':checked')) {
                    $radioContainer.hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save supplier assignments
     *
     * @since  3.0.0
     * @param  int      $post_id Post ID
     * @param  \WP_Post $post    Post object
     * @return void
     */
    public function save_suppliers(int $post_id, \WP_Post $post): void
    {
        // Security checks
        if (!isset($_POST['smfw_product_suppliers_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['smfw_product_suppliers_nonce'])), 'smfw_save_product_suppliers')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_products')) {
            return;
        }

        // Get selected suppliers
        $supplier_ids = [];
        if (isset($_POST['product_suppliers']) && is_array($_POST['product_suppliers'])) {
            $supplier_ids = array_map('intval', wp_unslash($_POST['product_suppliers']));
        }

        // Get primary supplier
        $primary_supplier = 0;
        if (isset($_POST['primary_supplier'])) {
            $primary_supplier = (int) $_POST['primary_supplier'];
        }

        // Validate that primary supplier is in the selected suppliers
        if ($primary_supplier > 0 && !in_array($primary_supplier, $supplier_ids, true)) {
            $primary_supplier = 0;
        }

        // Update relationships
        $this->relationships->update_product_suppliers($post_id, $supplier_ids, $primary_supplier);

        // Log the update
        if (function_exists('wc_get_logger')) {
            $wc_logger = wc_get_logger();
            $wc_logger->debug(
                sprintf(
                    'Product #%d suppliers updated: %d suppliers assigned%s',
                    $post_id,
                    count($supplier_ids),
                    $primary_supplier ? ' (primary: ' . $primary_supplier . ')' : ''
                ),
                ['source' => 'suppliers-manager']
            );
        }
    }
}
