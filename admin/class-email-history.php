<?php
/**
 * Email History Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Admin
 * @author     Mike Lvd
 * @since      2.1.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\Admin;

use Suppliers_Manager_For_WooCommerce\Email_Logger;

/**
 * Email History class
 *
 * Handles email history admin page and display.
 *
 * @since 2.1.0
 */
class Email_History
{
    /**
     * Page slug
     */
    private const PAGE_SLUG = 'smfw-email-history';

    /**
     * Email logger instance
     *
     * @var Email_Logger
     */
    private Email_Logger $logger;

    /**
     * Plugin identifier
     *
     * @var string
     */
    private string $plugin_name;

    /**
     * Initialize the class
     *
     * @since 2.1.0
     * @param string $plugin_name Plugin identifier
     */
    public function __construct(string $plugin_name)
    {
        $this->plugin_name = $plugin_name;
        $this->logger = new Email_Logger();
    }

    /**
     * Register history page
     *
     * @since  2.1.0
     * @return void
     */
    public function add_history_page(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Supplier Email History', 'suppliers-manager-for-woocommerce'),
            __('Email History', 'suppliers-manager-for-woocommerce'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_history_page']
        );
    }

    /**
     * Render history page
     *
     * @since  2.1.0
     * @return void
     */
    public function render_history_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'suppliers-manager-for-woocommerce'));
        }

        // Get filter parameters
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $supplier_id = isset($_GET['supplier_id']) ? absint($_GET['supplier_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;

        // Get statistics
        $stats = $this->logger->get_statistics();

        // Get history
        $args = [
            'order_id'    => $order_id,
            'supplier_id' => $supplier_id,
            'status'      => $status,
            'limit'       => $per_page,
            'offset'      => ($paged - 1) * $per_page,
            'orderby'     => 'sent_at',
            'order'       => 'DESC',
        ];

        $history = $this->logger->get_history($args);
        $total = $this->logger->get_total_count($args);
        $total_pages = ceil($total / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Supplier Email History', 'suppliers-manager-for-woocommerce'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=smfw-settings')); ?>" class="page-title-action">
                <?php esc_html_e('Settings', 'suppliers-manager-for-woocommerce'); ?>
            </a>
            <hr class="wp-header-end">

            <!-- Statistics -->
            <div class="smfw-stats" style="margin: 20px 0; display: flex; gap: 20px;">
                <div class="smfw-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e('Total Sent', 'suppliers-manager-for-woocommerce'); ?></h3>
                    <p style="margin: 0; font-size: 28px; font-weight: 600; color: #2271b1;"><?php echo esc_html(number_format_i18n($stats['total_sent'])); ?></p>
                </div>
                <div class="smfw-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e('This Month', 'suppliers-manager-for-woocommerce'); ?></h3>
                    <p style="margin: 0; font-size: 28px; font-weight: 600; color: #00a32a;"><?php echo esc_html(number_format_i18n($stats['this_month'])); ?></p>
                </div>
                <div class="smfw-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e('Today', 'suppliers-manager-for-woocommerce'); ?></h3>
                    <p style="margin: 0; font-size: 28px; font-weight: 600; color: #007cba;"><?php echo esc_html(number_format_i18n($stats['today'])); ?></p>
                </div>
                <?php if ($stats['total_failed'] > 0) : ?>
                <div class="smfw-stat-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php esc_html_e('Failed', 'suppliers-manager-for-woocommerce'); ?></h3>
                    <p style="margin: 0; font-size: 28px; font-weight: 600; color: #d63638;"><?php echo esc_html(number_format_i18n($stats['total_failed'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="supplier_id">
                            <option value=""><?php esc_html_e('All Suppliers', 'suppliers-manager-for-woocommerce'); ?></option>
                            <?php
                            // Get suppliers from Custom Post Type (v3.0.0+)
                            $suppliers = get_posts([
                                    'post_type'      => 'supplier',
                                    'post_status'    => ['publish', 'draft'], // Include both active and inactive
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                            ]);

                            if (!empty($suppliers)) {
                                foreach ($suppliers as $supplier) {
                                    printf(
                                            '<option value="%d" %s>%s</option>',
                                            esc_attr($supplier->ID),
                                            selected($supplier_id, $supplier->ID, false),
                                            esc_html($supplier->post_title)
                                    );
                                }
                            }
                            ?>
                        </select>

                        <select name="status">
                            <option value=""><?php esc_html_e('All Statuses', 'suppliers-manager-for-woocommerce'); ?></option>
                            <option value="sent" <?php selected($status, 'sent'); ?>><?php esc_html_e('Sent', 'suppliers-manager-for-woocommerce'); ?></option>
                            <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'suppliers-manager-for-woocommerce'); ?></option>
                        </select>

                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'suppliers-manager-for-woocommerce'); ?>">

                        <?php if ($order_id || $supplier_id || $status) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">
                                <?php esc_html_e('Clear Filters', 'suppliers-manager-for-woocommerce'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="tablenav-pages">
                        <?php
                        if ($total_pages > 1) {
                            echo wp_kses_post(
                                paginate_links([
                                    'base'      => add_query_arg('paged', '%#%'),
                                    'format'    => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                ])
                            );
                        }
                        ?>
                    </div>
                </div>
            </form>

            <!-- History Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 80px;"><?php esc_html_e('Order', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Supplier', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Recipient', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Subject', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col" style="width: 60px;"><?php esc_html_e('Items', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col" style="width: 80px;"><?php esc_html_e('Status', 'suppliers-manager-for-woocommerce'); ?></th>
                        <th scope="col" style="width: 150px;"><?php esc_html_e('Sent At', 'suppliers-manager-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)) : ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <?php esc_html_e('No email history found.', 'suppliers-manager-for-woocommerce'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($history as $entry) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $entry['order_id'] . '&action=edit')); ?>">
                                        #<?php echo esc_html($entry['order_id']); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($entry['supplier_name']); ?></strong>
                                    <br>
                                    <small><?php echo esc_html($entry['supplier_email']); ?></small>
                                </td>
                                <td><?php echo esc_html($entry['recipient_email']); ?></td>
                                <td><?php echo esc_html($entry['subject']); ?></td>
                                <td style="text-align: center;"><?php echo esc_html($entry['items_count']); ?></td>
                                <td>
                                    <?php if ($entry['status'] === 'sent') : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                        <?php esc_html_e('Sent', 'suppliers-manager-for-woocommerce'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
                                        <?php esc_html_e('Failed', 'suppliers-manager-for-woocommerce'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        wp_date(
                                            get_option('date_format') . ' ' . get_option('time_format'),
                                            strtotime($entry['sent_at'])
                                        )
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total'     => $total_pages,
                                'current'   => $paged,
                            ])
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
