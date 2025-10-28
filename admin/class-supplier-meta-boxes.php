<?php
/**
 * Supplier Meta Boxes
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
 * Supplier Meta Boxes class
 *
 * Handles meta boxes for supplier custom post type.
 *
 * @since 3.0.0
 */
class Supplier_Meta_Boxes
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
        // Contact Information
        add_meta_box(
            'smfw_supplier_contact',
            __('Contact Information', 'suppliers-manager-for-woocommerce'),
            [$this, 'render_contact_info_meta_box'],
            'supplier',
            'normal',
            'high'
        );

        // Assigned Products
        add_meta_box(
            'smfw_supplier_products',
            __('Assigned Products', 'suppliers-manager-for-woocommerce'),
            [$this, 'render_products_meta_box'],
            'supplier',
            'side',
            'default'
        );

        // Supplier Stats
        add_meta_box(
            'smfw_supplier_stats',
            __('Supplier Statistics', 'suppliers-manager-for-woocommerce'),
            [$this, 'render_stats_meta_box'],
            'supplier',
            'side',
            'low'
        );
    }

    /**
     * Render contact information meta box
     *
     * @since  3.0.0
     * @param  \WP_Post $post Post object
     * @return void
     */
    public function render_contact_info_meta_box(\WP_Post $post): void
    {
        // Nonce field for security
        wp_nonce_field('smfw_save_supplier_meta', 'smfw_supplier_meta_nonce');

        // Get current values
        $email = get_post_meta($post->ID, '_supplier_email', true);
        $telephone = get_post_meta($post->ID, '_supplier_telephone', true);
        $address = get_post_meta($post->ID, '_supplier_address', true);
        $contact = get_post_meta($post->ID, '_supplier_contact', true);
        $website = get_post_meta($post->ID, '_supplier_website', true);
        $notes = get_post_meta($post->ID, '_supplier_notes', true);
        ?>
        <div class="smfw-supplier-contact-fields">
            <p class="form-field">
                <label for="supplier_email">
                    <?php esc_html_e('Email Address', 'suppliers-manager-for-woocommerce'); ?>
                    <span class="required">*</span>
                </label>
                <input type="email" id="supplier_email" name="supplier_email" 
                       value="<?php echo esc_attr($email); ?>" 
                       class="widefat" required>
                <span class="description">
                    <?php esc_html_e('Required for email notifications', 'suppliers-manager-for-woocommerce'); ?>
                </span>
            </p>

            <p class="form-field">
                <label for="supplier_telephone">
                    <?php esc_html_e('Telephone', 'suppliers-manager-for-woocommerce'); ?>
                </label>
                <input type="text" id="supplier_telephone" name="supplier_telephone" 
                       value="<?php echo esc_attr($telephone); ?>" 
                       class="widefat">
            </p>

            <p class="form-field">
                <label for="supplier_contact">
                    <?php esc_html_e('Primary Contact Person', 'suppliers-manager-for-woocommerce'); ?>
                </label>
                <input type="text" id="supplier_contact" name="supplier_contact" 
                       value="<?php echo esc_attr($contact); ?>" 
                       class="widefat">
            </p>

            <p class="form-field">
                <label for="supplier_website">
                    <?php esc_html_e('Website', 'suppliers-manager-for-woocommerce'); ?>
                </label>
                <input type="url" id="supplier_website" name="supplier_website" 
                       value="<?php echo esc_attr($website); ?>" 
                       class="widefat" placeholder="https://">
            </p>

            <p class="form-field">
                <label for="supplier_address">
                    <?php esc_html_e('Address', 'suppliers-manager-for-woocommerce'); ?>
                </label>
                <textarea id="supplier_address" name="supplier_address" 
                          rows="3" class="widefat"><?php echo esc_textarea($address); ?></textarea>
            </p>

            <p class="form-field">
                <label for="supplier_notes">
                    <?php esc_html_e('Internal Notes', 'suppliers-manager-for-woocommerce'); ?>
                </label>
                <textarea id="supplier_notes" name="supplier_notes" 
                          rows="4" class="widefat"><?php echo esc_textarea($notes); ?></textarea>
                <span class="description">
                    <?php esc_html_e('Private notes for internal use only', 'suppliers-manager-for-woocommerce'); ?>
                </span>
            </p>
        </div>

        <style>
            .smfw-supplier-contact-fields .form-field {
                margin-bottom: 15px;
            }
            .smfw-supplier-contact-fields label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }
            .smfw-supplier-contact-fields .required {
                color: #d63638;
            }
            .smfw-supplier-contact-fields .description {
                display: block;
                margin-top: 5px;
                font-style: italic;
                color: #646970;
            }
        </style>
        <?php
    }

    /**
     * Render assigned products meta box
     *
     * @since  3.0.0
     * @param  \WP_Post $post Post object
     * @return void
     */
    public function render_products_meta_box(\WP_Post $post): void
    {
        $product_ids = $this->relationships->get_supplier_products($post->ID);
        $count = count($product_ids);

        if ($count > 0) {
            echo '<p>';
            printf(
                /* translators: %d: number of products */
                esc_html(_n('%d product assigned', '%d products assigned', $count, 'suppliers-manager-for-woocommerce')),
                $count
            );
            echo '</p>';

            echo '<p>';
            printf(
                '<a href="%s" class="button button-secondary">%s</a>',
                esc_url(add_query_arg(['supplier_id' => $post->ID], admin_url('edit.php?post_type=product'))),
                esc_html__('View Products', 'suppliers-manager-for-woocommerce')
            );
            echo '</p>';

            // Show first few products
            if ($count <= 10) {
                echo '<ul style="margin-top: 15px; padding-left: 15px;">';
                foreach ($product_ids as $product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        printf(
                            '<li><a href="%s" target="_blank">%s</a></li>',
                            esc_url(get_edit_post_link($product_id)),
                            esc_html($product->get_name())
                        );
                    }
                }
                echo '</ul>';
            }
        } else {
            echo '<p>' . esc_html__('No products assigned yet.', 'suppliers-manager-for-woocommerce') . '</p>';
            echo '<p>';
            printf(
                '<a href="%s" class="button button-secondary">%s</a>',
                esc_url(admin_url('edit.php?post_type=product')),
                esc_html__('Assign Products', 'suppliers-manager-for-woocommerce')
            );
            echo '</p>';
        }
    }

    /**
     * Render statistics meta box
     *
     * @since  3.0.0
     * @param  \WP_Post $post Post object
     * @return void
     */
    public function render_stats_meta_box(\WP_Post $post): void
    {
        $product_count = $this->relationships->get_supplier_products_count($post->ID);
        $created_date = get_the_date('Y-m-d H:i:s', $post);
        $modified_date = get_the_modified_date('Y-m-d H:i:s', $post);

        ?>
        <div class="smfw-supplier-stats">
            <p>
                <strong><?php esc_html_e('Products:', 'suppliers-manager-for-woocommerce'); ?></strong>
                <?php echo esc_html(number_format_i18n($product_count)); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Created:', 'suppliers-manager-for-woocommerce'); ?></strong>
                <?php echo esc_html(get_the_date('', $post)); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Last Modified:', 'suppliers-manager-for-woocommerce'); ?></strong>
                <?php echo esc_html(get_the_modified_date('', $post)); ?>
            </p>
        </div>
        <style>
            .smfw-supplier-stats p {
                margin: 8px 0;
            }
        </style>
        <?php
    }

    /**
     * Save contact information
     *
     * @since  3.0.0
     * @param  int      $post_id Post ID
     * @param  \WP_Post $post    Post object
     * @return void
     */
    public function save_contact_info(int $post_id, \WP_Post $post): void
    {
        // Security checks
        if (!isset($_POST['smfw_supplier_meta_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['smfw_supplier_meta_nonce'])), 'smfw_save_supplier_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Save email (required)
        if (isset($_POST['supplier_email'])) {
            $email = sanitize_email(wp_unslash($_POST['supplier_email']));
            if (is_email($email)) {
                update_post_meta($post_id, '_supplier_email', $email);
            }
        }

        // Save telephone
        if (isset($_POST['supplier_telephone'])) {
            update_post_meta(
                $post_id, 
                '_supplier_telephone', 
                sanitize_text_field(wp_unslash($_POST['supplier_telephone']))
            );
        }

        // Save address
        if (isset($_POST['supplier_address'])) {
            update_post_meta(
                $post_id, 
                '_supplier_address', 
                sanitize_textarea_field(wp_unslash($_POST['supplier_address']))
            );
        }

        // Save contact
        if (isset($_POST['supplier_contact'])) {
            update_post_meta(
                $post_id, 
                '_supplier_contact', 
                sanitize_text_field(wp_unslash($_POST['supplier_contact']))
            );
        }

        // Save website
        if (isset($_POST['supplier_website'])) {
            $website = esc_url_raw(wp_unslash($_POST['supplier_website']));
            update_post_meta($post_id, '_supplier_website', $website);
        }

        // Save notes
        if (isset($_POST['supplier_notes'])) {
            update_post_meta(
                $post_id, 
                '_supplier_notes', 
                sanitize_textarea_field(wp_unslash($_POST['supplier_notes']))
            );
        }
    }
}
