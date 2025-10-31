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

    /**
     * Add bulk actions to products list
     *
     * @since  3.0.2
     * @param  array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function add_product_bulk_actions(array $actions): array
    {
        $actions['smfw_bulk_add_suppliers'] = __('Add Suppliers', 'suppliers-manager-for-woocommerce');
        $actions['smfw_bulk_remove_suppliers'] = __('Remove Suppliers', 'suppliers-manager-for-woocommerce');
        $actions['smfw_bulk_replace_suppliers'] = __('Replace Suppliers', 'suppliers-manager-for-woocommerce');

        return $actions;
    }

    /**
     * Handle bulk actions for products
     *
     * @since  3.0.2
     * @param  string $redirect_to Redirect URL
     * @param  string $action      Bulk action name
     * @param  array  $post_ids    Selected post IDs
     * @return string Modified redirect URL
     */
    public function handle_product_bulk_actions(string $redirect_to, string $action, array $post_ids): string
    {
        // Check if this is one of our bulk actions
        if (!in_array($action, ['smfw_bulk_add_suppliers', 'smfw_bulk_remove_suppliers', 'smfw_bulk_replace_suppliers'], true)) {
            return $redirect_to;
        }

        // Store selected product IDs in transient
        $transient_key = 'smfw_bulk_edit_' . get_current_user_id();
        set_transient($transient_key, [
            'action' => $action,
            'product_ids' => $post_ids,
        ], 300); // 5 minutes

        // Redirect to our bulk edit page
        return admin_url('admin.php?page=smfw-bulk-edit&transient=' . urlencode($transient_key));
    }

    /**
     * Register bulk edit page
     *
     * @since  3.0.2
     * @return void
     */
    public function add_bulk_edit_page(): void
    {
        add_submenu_page(
            null, // Hidden from menu
            __('Bulk Edit Suppliers', 'suppliers-manager-for-woocommerce'),
            __('Bulk Edit Suppliers', 'suppliers-manager-for-woocommerce'),
            'edit_products',
            'smfw-bulk-edit',
            [$this, 'render_bulk_edit_page']
        );
    }

    /**
     * Render bulk edit page
     *
     * @since  3.0.2
     * @return void
     */
    public function render_bulk_edit_page(): void
    {
        if (!current_user_can('edit_products')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'suppliers-manager-for-woocommerce'));
        }

        // Get transient data
        $transient_key = isset($_GET['transient']) ? sanitize_text_field(wp_unslash($_GET['transient'])) : '';
        $data = get_transient($transient_key);

        if (!$data || !isset($data['action']) || !isset($data['product_ids'])) {
            wp_die(esc_html__('Invalid or expired bulk edit session.', 'suppliers-manager-for-woocommerce'));
        }

        $action = $data['action'];
        $product_ids = $data['product_ids'];

        // Get all suppliers
        $suppliers = get_posts([
            'post_type'      => 'supplier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        // Analyze supplier assignments across selected products
        $supplier_stats = $this->analyze_supplier_assignments($product_ids, $suppliers);

        // Determine action title
        $action_titles = [
            'smfw_bulk_add_suppliers' => __('Add Suppliers to Products', 'suppliers-manager-for-woocommerce'),
            'smfw_bulk_remove_suppliers' => __('Remove Suppliers from Products', 'suppliers-manager-for-woocommerce'),
            'smfw_bulk_replace_suppliers' => __('Replace Suppliers for Products', 'suppliers-manager-for-woocommerce'),
        ];

        $page_title = $action_titles[$action] ?? __('Bulk Edit Suppliers', 'suppliers-manager-for-woocommerce');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($page_title); ?></h1>

            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <p>
                    <strong><?php esc_html_e('Selected Products:', 'suppliers-manager-for-woocommerce'); ?></strong>
                    <?php echo esc_html(count($product_ids)); ?>
                </p>

                <?php if (empty($suppliers)) : ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e('No suppliers available. Please create suppliers first.', 'suppliers-manager-for-woocommerce'); ?></p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=supplier')); ?>" class="button button-primary">
                                <?php esc_html_e('Add New Supplier', 'suppliers-manager-for-woocommerce'); ?>
                            </a>
                        </p>
                    </div>
                <?php else : ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('smfw_bulk_edit_suppliers', 'smfw_bulk_edit_nonce'); ?>
                        <input type="hidden" name="action" value="smfw_process_bulk_edit">
                        <input type="hidden" name="bulk_action" value="<?php echo esc_attr($action); ?>">
                        <input type="hidden" name="transient_key" value="<?php echo esc_attr($transient_key); ?>">

                        <table class="form-table" role="presentation">
                            <tbody>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Select Suppliers', 'suppliers-manager-for-woocommerce'); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <p class="description" style="margin-bottom: 15px;">
                                            <?php
                                            if ($action === 'smfw_bulk_add_suppliers') {
                                                esc_html_e('Select suppliers to add to the selected products. Existing suppliers will be preserved.', 'suppliers-manager-for-woocommerce');
                                            } elseif ($action === 'smfw_bulk_remove_suppliers') {
                                                esc_html_e('Checked suppliers are currently assigned to one or more selected products. Select suppliers to remove.', 'suppliers-manager-for-woocommerce');
                                            } else {
                                                esc_html_e('Select suppliers to replace all existing suppliers for the selected products.', 'suppliers-manager-for-woocommerce');
                                            }
                                            ?>
                                        </p>

                                        <?php if ($action === 'smfw_bulk_remove_suppliers' && !empty($supplier_stats['has_suppliers'])) : ?>
                                            <div class="notice notice-info inline" style="margin-bottom: 15px;">
                                                <p>
                                                    <strong><?php esc_html_e('Legend:', 'suppliers-manager-for-woocommerce'); ?></strong>
                                                    <?php esc_html_e('The number in parentheses shows how many products have each supplier assigned.', 'suppliers-manager-for-woocommerce'); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                            <?php foreach ($suppliers as $supplier) : ?>
                                                <?php
                                                $supplier_email = get_post_meta($supplier->ID, '_supplier_email', true);
                                                $assigned_count = $supplier_stats['assignments'][$supplier->ID] ?? 0;
                                                $is_assigned = $assigned_count > 0;

                                                // For remove action, pre-check suppliers that are assigned
                                                $should_check = ($action === 'smfw_bulk_remove_suppliers' && $is_assigned);

                                                // Highlight assigned suppliers for remove action
                                                $item_style = '';
                                                if ($action === 'smfw_bulk_remove_suppliers' && $is_assigned) {
                                                    $item_style = 'background: #fff3cd; border-color: #ffc107;';
                                                }
                                                ?>
                                                <label style="display: block; margin-bottom: 12px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; <?php echo esc_attr($item_style); ?>">
                                                    <input type="checkbox"
                                                           name="supplier_ids[]"
                                                           value="<?php echo esc_attr($supplier->ID); ?>"
                                                           style="margin-right: 10px;"
                                                        <?php checked($should_check, true); ?>>

                                                    <?php if (has_post_thumbnail($supplier->ID)) : ?>
                                                        <?php echo get_the_post_thumbnail($supplier->ID, [30, 30], ['style' => 'vertical-align: middle; margin-right: 10px;']); ?>
                                                    <?php endif; ?>

                                                    <strong><?php echo esc_html($supplier->post_title); ?></strong>

                                                    <?php if ($action === 'smfw_bulk_remove_suppliers' && $is_assigned) : ?>
                                                        <span style="color: #d63638; font-weight: 600; margin-left: 8px;">
    															(<?php
                                                            printf(
                                                            /* translators: 1: assigned count, 2: total products */
                                                                esc_html__('%1$d/%2$d products', 'suppliers-manager-for-woocommerce'),
                                                                $assigned_count,
                                                                count($product_ids)
                                                            );
                                                            ?>)
    														</span>
                                                    <?php elseif ($action === 'smfw_bulk_remove_suppliers') : ?>
                                                        <span style="color: #999; font-style: italic; margin-left: 8px;">
    															<?php esc_html_e('(Not assigned)', 'suppliers-manager-for-woocommerce'); ?>
    														</span>
                                                    <?php endif; ?>

                                                    <?php if ($supplier_email) : ?>
                                                        <br>
                                                        <small style="color: #666; margin-left: 40px;"><?php echo esc_html($supplier_email); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <p style="margin-top: 15px;">
                                            <button type="button" class="button" onclick="jQuery('input[name=\'supplier_ids[]\']').prop('checked', true);">
                                                <?php esc_html_e('Select All', 'suppliers-manager-for-woocommerce'); ?>
                                            </button>
                                            <button type="button" class="button" onclick="jQuery('input[name=\'supplier_ids[]\']').prop('checked', false);">
                                                <?php esc_html_e('Deselect All', 'suppliers-manager-for-woocommerce'); ?>
                                            </button>
                                            <?php if ($action === 'smfw_bulk_remove_suppliers') : ?>
                                                <button type="button" class="button" onclick="jQuery('input[name=\'supplier_ids[]\']:checked').prop('checked', false);">
                                                    <?php esc_html_e('Clear Selection', 'suppliers-manager-for-woocommerce'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>

                            <?php if ($action === 'smfw_bulk_add_suppliers' || $action === 'smfw_bulk_replace_suppliers') : ?>
                                <tr>
                                    <th scope="row">
                                        <label for="primary_supplier_id"><?php esc_html_e('Primary Supplier (Optional)', 'suppliers-manager-for-woocommerce'); ?></label>
                                    </th>
                                    <td>
                                        <select name="primary_supplier_id" id="primary_supplier_id" class="regular-text">
                                            <option value=""><?php esc_html_e('— No Primary Supplier —', 'suppliers-manager-for-woocommerce'); ?></option>
                                            <?php foreach ($suppliers as $supplier) : ?>
                                                <option value="<?php echo esc_attr($supplier->ID); ?>">
                                                    <?php echo esc_html($supplier->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Optionally set one supplier as the primary supplier for all selected products.', 'suppliers-manager-for-woocommerce'); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Apply Changes', 'suppliers-manager-for-woocommerce'); ?>">
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button">
                                <?php esc_html_e('Cancel', 'suppliers-manager-for-woocommerce'); ?>
                            </a>
                        </p>
                    </form>

                <?php endif; ?>
            </div>

            <div style="background: #f0f0f1; border-left: 4px solid #72aee6; padding: 15px; margin: 20px 0;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Selected Products', 'suppliers-manager-for-woocommerce'); ?></h3>

                <?php if ($action === 'smfw_bulk_remove_suppliers' && !empty($supplier_stats['has_suppliers'])) : ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ccd0d4; border-radius: 3px;">
                        <strong><?php esc_html_e('Current Supplier Summary:', 'suppliers-manager-for-woocommerce'); ?></strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ($supplier_stats['supplier_names'] as $supplier_id => $supplier_name) : ?>
                                <?php if (isset($supplier_stats['assignments'][$supplier_id]) && $supplier_stats['assignments'][$supplier_id] > 0) : ?>
                                    <li>
                                        <strong><?php echo esc_html($supplier_name); ?>:</strong>
                                        <?php
                                        printf(
                                        /* translators: 1: assigned count, 2: total products */
                                            esc_html__('Assigned to %1$d of %2$d products', 'suppliers-manager-for-woocommerce'),
                                            $supplier_stats['assignments'][$supplier_id],
                                            count($product_ids)
                                        );
                                        ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <ul style="columns: 2; column-gap: 30px;">
                    <?php foreach ($product_ids as $product_id) : ?>
                        <?php
                        $product = wc_get_product($product_id);
                        $product_supplier_ids = $this->relationships->get_product_suppliers($product_id);
                        ?>
                        <?php if ($product) : ?>
                            <li style="margin-bottom: 8px;">
                                <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>" target="_blank">
                                    <?php echo esc_html($product->get_name()); ?>
                                </a>
                                <?php if (!empty($product_supplier_ids)) : ?>
                                    <br>
                                    <small style="color: #666; margin-left: 10px;">
                                        <?php
                                        $supplier_names = [];
                                        foreach ($product_supplier_ids as $sid) {
                                            $s = get_post($sid);
                                            if ($s) {
                                                $supplier_names[] = $s->post_title;
                                            }
                                        }
                                        if (!empty($supplier_names)) {
                                            echo '└ ' . esc_html(implode(', ', $supplier_names));
                                        }
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Analyze supplier assignments across selected products
     *
     * @since  3.0.2
     * @param  array $product_ids Product IDs
     * @param  array $suppliers   Supplier posts
     * @return array Analysis results
     */
    private function analyze_supplier_assignments(array $product_ids, array $suppliers): array
    {
        $stats = [
            'assignments' => [],      // supplier_id => count of products
            'has_suppliers' => false, // whether any product has suppliers
            'supplier_names' => [],   // supplier_id => supplier name
        ];

        // Initialize counts
        foreach ($suppliers as $supplier) {
            $stats['assignments'][$supplier->ID] = 0;
            $stats['supplier_names'][$supplier->ID] = $supplier->post_title;
        }

        // Count assignments
        foreach ($product_ids as $product_id) {
            $product_suppliers = $this->relationships->get_product_suppliers($product_id);

            if (!empty($product_suppliers)) {
                $stats['has_suppliers'] = true;

                foreach ($product_suppliers as $supplier_id) {
                    if (isset($stats['assignments'][$supplier_id])) {
                        $stats['assignments'][$supplier_id]++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Process bulk edit suppliers
     *
     * @since  3.0.2
     * @return void
     */
    public function process_bulk_edit_suppliers(): void
    {
        // Verify nonce
        if (!isset($_POST['smfw_bulk_edit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['smfw_bulk_edit_nonce'])), 'smfw_bulk_edit_suppliers')) {
            wp_die(esc_html__('Security check failed.', 'suppliers-manager-for-woocommerce'));
        }

        // Check permissions
        if (!current_user_can('edit_products')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'suppliers-manager-for-woocommerce'));
        }

        // Get data from transient
        $transient_key = isset($_POST['transient_key']) ? sanitize_text_field(wp_unslash($_POST['transient_key'])) : '';
        $data = get_transient($transient_key);

        if (!$data || !isset($data['product_ids'])) {
            wp_die(esc_html__('Invalid or expired bulk edit session.', 'suppliers-manager-for-woocommerce'));
        }

        $product_ids = $data['product_ids'];
        $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
        $supplier_ids = isset($_POST['supplier_ids']) && is_array($_POST['supplier_ids'])
            ? array_map('intval', wp_unslash($_POST['supplier_ids']))
            : [];
        $primary_supplier_id = isset($_POST['primary_supplier_id']) ? intval($_POST['primary_supplier_id']) : 0;

        // Validate that primary supplier is in the selected suppliers
        if ($primary_supplier_id > 0 && !in_array($primary_supplier_id, $supplier_ids, true)) {
            $primary_supplier_id = 0;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($product_ids as $product_id) {
            try {
                $result = false;

                switch ($action) {
                    case 'smfw_bulk_add_suppliers':
                        $result = $this->bulk_add_suppliers($product_id, $supplier_ids, $primary_supplier_id);
                        break;

                    case 'smfw_bulk_remove_suppliers':
                        $result = $this->bulk_remove_suppliers($product_id, $supplier_ids);
                        break;

                    case 'smfw_bulk_replace_suppliers':
                        $result = $this->bulk_replace_suppliers($product_id, $supplier_ids, $primary_supplier_id);
                        break;
                }

                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (\Exception $e) {
                $error_count++;
            }
        }

        // Delete transient
        delete_transient($transient_key);

        // Redirect with success message
        $redirect_url = add_query_arg([
            'post_type' => 'product',
            'smfw_bulk_updated' => $success_count,
            'smfw_bulk_errors' => $error_count,
        ], admin_url('edit.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Bulk add suppliers to product
     *
     * @since  3.0.2
     * @param  int   $product_id         Product ID
     * @param  array $new_supplier_ids   Supplier IDs to add
     * @param  int   $primary_supplier_id Primary supplier ID
     * @return bool Success status
     */
    private function bulk_add_suppliers(int $product_id, array $new_supplier_ids, int $primary_supplier_id = 0): bool
    {
        if (empty($new_supplier_ids)) {
            return false;
        }

        // Get existing suppliers
        $existing_supplier_ids = $this->relationships->get_product_suppliers($product_id);

        // Merge with new suppliers (avoid duplicates)
        $all_supplier_ids = array_unique(array_merge($existing_supplier_ids, $new_supplier_ids));

        // If no primary is set for this product, use the new primary (if provided)
        $current_primary = $this->relationships->get_primary_supplier($product_id);
        if (!$current_primary && $primary_supplier_id > 0) {
            $primary_to_set = $primary_supplier_id;
        } else {
            $primary_to_set = $current_primary ?? 0;
        }

        return $this->relationships->update_product_suppliers($product_id, $all_supplier_ids, $primary_to_set);
    }

    /**
     * Bulk remove suppliers from product
     *
     * @since  3.0.2
     * @param  int   $product_id          Product ID
     * @param  array $suppliers_to_remove Supplier IDs to remove
     * @return bool Success status
     */
    private function bulk_remove_suppliers(int $product_id, array $suppliers_to_remove): bool
    {
        if (empty($suppliers_to_remove)) {
            return false;
        }

        // Get existing suppliers
        $existing_supplier_ids = $this->relationships->get_product_suppliers($product_id);

        // Remove specified suppliers
        $remaining_supplier_ids = array_diff($existing_supplier_ids, $suppliers_to_remove);

        // Get current primary (use null coalescing operator to ensure int type)
        $current_primary = $this->relationships->get_primary_supplier($product_id) ?? 0;

        // If primary is being removed, clear it
        if ($current_primary > 0 && in_array($current_primary, $suppliers_to_remove, true)) {
            $current_primary = 0;
        }

        return $this->relationships->update_product_suppliers($product_id, $remaining_supplier_ids, $current_primary);
    }

    /**
     * Bulk replace suppliers for product
     *
     * @since  3.0.2
     * @param  int   $product_id         Product ID
     * @param  array $new_supplier_ids   New supplier IDs
     * @param  int   $primary_supplier_id Primary supplier ID
     * @return bool Success status
     */
    private function bulk_replace_suppliers(int $product_id, array $new_supplier_ids, int $primary_supplier_id = 0): bool
    {
        if (empty($new_supplier_ids)) {
            // If empty, remove all suppliers
            return $this->relationships->update_product_suppliers($product_id, [], 0);
        }

        return $this->relationships->update_product_suppliers($product_id, $new_supplier_ids, $primary_supplier_id);
    }

    /**
     * Display bulk edit admin notices
     *
     * @since  3.0.2
     * @return void
     */
    public function display_bulk_edit_notices(): void
    {
        global $pagenow;

        if ($pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
            return;
        }

        if (isset($_GET['smfw_bulk_updated'])) {
            $updated = intval($_GET['smfw_bulk_updated']);
            $errors = isset($_GET['smfw_bulk_errors']) ? intval($_GET['smfw_bulk_errors']) : 0;

            if ($updated > 0) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    sprintf(
                    /* translators: %d: number of products updated */
                        esc_html(_n('%d product updated successfully.', '%d products updated successfully.', $updated, 'suppliers-manager-for-woocommerce')),
                        $updated
                    )
                );
            }

            if ($errors > 0) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    sprintf(
                    /* translators: %d: number of products with errors */
                        esc_html(_n('%d product failed to update.', '%d products failed to update.', $errors, 'suppliers-manager-for-woocommerce')),
                        $errors
                    )
                );
            }
        }
    }
}