<?php
/**
 * Settings Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Admin
 * @author     Mike Lvd
 * @since      2.1.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\Admin;

/**
 * Settings class
 *
 * Handles plugin settings page and configuration options.
 *
 * @since 2.1.0
 */
class Settings
{
    /**
     * Settings page slug
     */
    private const PAGE_SLUG = 'smfw-settings';

    /**
     * Settings option group
     */
    private const OPTION_GROUP = 'smfw_settings';

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
    }

    /**
     * Register settings page
     *
     * @since  2.1.0
     * @return void
     */
    public function add_settings_page(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Suppliers Manager Settings', 'suppliers-manager-for-woocommerce'),
            __('Suppliers Manager', 'suppliers-manager-for-woocommerce'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings and fields
     *
     * @since  2.1.0
     * @return void
     */
    public function register_settings(): void
    {
        // Register settings
        register_setting(
                self::OPTION_GROUP,
                'smfw_notification_status',
                [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'processing',
                ]
        );

        register_setting(
                self::OPTION_GROUP,
                'smfw_enable_email_history',
                [
                        'type'              => 'string',
                        'sanitize_callback' => [$this, 'sanitize_checkbox'],
                        'default'           => '1',
                ]
        );

        register_setting(
                self::OPTION_GROUP,
                'smfw_bcc_admin',
                [
                        'type'              => 'string',
                        'sanitize_callback' => [$this, 'sanitize_checkbox'],
                        'default'           => '1',
                ]
        );

        register_setting(
                self::OPTION_GROUP,
                'smfw_admin_email',
                [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_email',
                        'default'           => get_option('admin_email'),
                ]
        );

        register_setting(
                self::OPTION_GROUP,
                'smfw_history_retention_days',
                [
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'default'           => 90,
                ]
        );

        register_setting(
                self::OPTION_GROUP,
                'smfw_delete_data_on_uninstall',
                [
                        'type'              => 'string',
                        'sanitize_callback' => [$this, 'sanitize_checkbox'],
                        'default'           => 'no',
                ]
        );

        // General Settings Section
        add_settings_section(
                'smfw_general_section',
                __('General Settings', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_general_section'],
                self::PAGE_SLUG
        );

        // Email History Section
        add_settings_section(
                'smfw_history_section',
                __('Email History Settings', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_history_section'],
                self::PAGE_SLUG
        );

        // Advanced Settings Section
        add_settings_section(
                'smfw_advanced_section',
                __('Advanced Settings', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_advanced_section'],
                self::PAGE_SLUG
        );

        // Order Status Field
        add_settings_field(
                'smfw_notification_status',
                __('Notification Order Status', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_status_field'],
                self::PAGE_SLUG,
                'smfw_general_section'
        );

        // BCC Admin Field
        add_settings_field(
                'smfw_bcc_admin',
                __('Send Copy to Admin', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_bcc_field'],
                self::PAGE_SLUG,
                'smfw_general_section'
        );

        // Admin Email Field
        add_settings_field(
                'smfw_admin_email',
                __('Admin Email Address', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_admin_email_field'],
                self::PAGE_SLUG,
                'smfw_general_section'
        );

        // Enable History Field
        add_settings_field(
                'smfw_enable_email_history',
                __('Enable Email History', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_history_enable_field'],
                self::PAGE_SLUG,
                'smfw_history_section'
        );

        // History Retention Field
        add_settings_field(
                'smfw_history_retention_days',
                __('History Retention Period', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_retention_field'],
                self::PAGE_SLUG,
                'smfw_history_section'
        );

        // Delete Data on Uninstall Field
        add_settings_field(
                'smfw_delete_data_on_uninstall',
                __('Delete Data on Uninstall', 'suppliers-manager-for-woocommerce'),
                [$this, 'render_delete_data_field'],
                self::PAGE_SLUG,
                'smfw_advanced_section'
        );
    }

    /**
     * Sanitize checkbox value
     *
     * @since  2.1.0
     * @param  mixed $value Input value
     * @return string '1' or '0' for standard checkboxes, 'yes' or 'no' for specific cases
     */
    public function sanitize_checkbox($value): string
    {
        // Check if this is the delete_data_on_uninstall field (needs 'yes'/'no')
        if (isset($_POST['smfw_delete_data_on_uninstall']) && $value === 'yes') {
            return 'yes';
        }

        // For delete_data_on_uninstall, return 'no' if not checked
        if (current_filter() === 'sanitize_option_smfw_delete_data_on_uninstall') {
            return ($value === 'yes') ? 'yes' : 'no';
        }

        // Standard '1'/'0' for other checkboxes
        return ($value === '1' || $value === 1 || $value === true || $value === 'yes') ? '1' : '0';
    }

    /**
     * Render settings page
     *
     * @since  2.1.0
     * @return void
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'suppliers-manager-for-woocommerce'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('smfw_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Save Settings', 'suppliers-manager-for-woocommerce'));
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Quick Links', 'suppliers-manager-for-woocommerce'); ?></h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smfw-email-history')); ?>" class="button button-secondary">
                    <?php esc_html_e('View Email History', 'suppliers-manager-for-woocommerce'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=supplier&post_type=product')); ?>" class="button button-secondary">
                    <?php esc_html_e('Manage Suppliers', 'suppliers-manager-for-woocommerce'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=email&section=smfw_supplier_email')); ?>" class="button button-secondary">
                    <?php esc_html_e('Email Template Settings', 'suppliers-manager-for-woocommerce'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render general section description
     *
     * @since  2.1.0
     * @return void
     */
    public function render_general_section(): void
    {
        echo '<p>' . esc_html__('Configure when and how supplier notifications are sent.', 'suppliers-manager-for-woocommerce') . '</p>';
    }

    /**
     * Render history section description
     *
     * @since  2.1.0
     * @return void
     */
    public function render_history_section(): void
    {
        echo '<p>' . esc_html__('Manage email history and logging settings.', 'suppliers-manager-for-woocommerce') . '</p>';
    }

    /**
     * Render order status field
     *
     * @since  2.1.0
     * @return void
     */
    public function render_status_field(): void
    {
        $value = get_option('smfw_notification_status', 'processing');
        $statuses = wc_get_order_statuses();
        ?>
        <select name="smfw_notification_status" id="smfw_notification_status">
            <?php foreach ($statuses as $status_key => $status_label) : ?>
                <?php $status_key = str_replace('wc-', '', $status_key); ?>
                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($value, $status_key); ?>>
                    <?php echo esc_html($status_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select the order status that triggers supplier notifications.', 'suppliers-manager-for-woocommerce'); ?>
        </p>
        <?php
    }

    /**
     * Render BCC admin field
     *
     * @since  2.1.0
     * @return void
     */
    public function render_bcc_field(): void
    {
        $value = get_option('smfw_bcc_admin', '1');
        $checked = ($value === '1' || $value === 1 || $value === true);
        ?>
        <fieldset>
            <label>
                <input type="hidden" name="smfw_bcc_admin" value="0">
                <input type="checkbox" name="smfw_bcc_admin" value="1" <?php checked($checked, true); ?>>
                <?php esc_html_e('Send a copy of all supplier emails to admin', 'suppliers-manager-for-woocommerce'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled, a copy of each supplier notification will be sent to the admin email address.', 'suppliers-manager-for-woocommerce'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Render admin email field
     *
     * @since  2.1.0
     * @return void
     */
    public function render_admin_email_field(): void
    {
        $value = get_option('smfw_admin_email', get_option('admin_email'));
        ?>
        <input type="email" name="smfw_admin_email" id="smfw_admin_email"
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('Email address to receive copies of supplier notifications.', 'suppliers-manager-for-woocommerce'); ?>
        </p>
        <?php
    }

    /**
     * Render email history enable field
     *
     * @since  2.1.0
     * @return void
     */
    public function render_history_enable_field(): void
    {
        $value = get_option('smfw_enable_email_history', '1');
        $checked = ($value === '1' || $value === 1 || $value === true);
        ?>
        <fieldset>
            <label>
                <input type="hidden" name="smfw_enable_email_history" value="0">
                <input type="checkbox" name="smfw_enable_email_history" value="1" <?php checked($checked, true); ?>>
                <?php esc_html_e('Keep a log of all sent supplier emails', 'suppliers-manager-for-woocommerce'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('When enabled, all supplier notifications will be logged in the database for future reference.', 'suppliers-manager-for-woocommerce'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Render retention period field
     *
     * @since  2.1.0
     * @return void
     */
    public function render_retention_field(): void
    {
        $value = get_option('smfw_history_retention_days', 90);
        ?>
        <input type="number" name="smfw_history_retention_days" id="smfw_history_retention_days"
               value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text">
        <?php esc_html_e('days', 'suppliers-manager-for-woocommerce'); ?>
        <p class="description">
            <?php esc_html_e('Email history older than this will be automatically deleted. Default: 90 days.', 'suppliers-manager-for-woocommerce'); ?>
        </p>
        <?php
    }

    /**
     * Render advanced section description
     *
     * @since  3.0.0
     * @return void
     */
    public function render_advanced_section(): void
    {
        echo '<p>' . esc_html__('Configure advanced plugin behavior and data management.', 'suppliers-manager-for-woocommerce') . '</p>';
    }

    /**
     * Render delete data on uninstall field
     *
     * @since  3.0.0
     * @return void
     */
    public function render_delete_data_field(): void
    {
        $value = get_option('smfw_delete_data_on_uninstall', 'no');
        $checked = ($value === 'yes' || $value === '1' || $value === 1 || $value === true);
        ?>
        <fieldset>
            <label>
                <input type="hidden" name="smfw_delete_data_on_uninstall" value="no">
                <input type="checkbox" name="smfw_delete_data_on_uninstall" value="yes" <?php checked($checked, true); ?>>
                <?php esc_html_e('Delete all plugin data when uninstalling', 'suppliers-manager-for-woocommerce'); ?>
            </label>
            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e('When enabled, all plugin data will be permanently deleted when you uninstall the plugin, including:', 'suppliers-manager-for-woocommerce'); ?>
            </p>
            <ul style="margin-left: 20px; margin-top: 8px; list-style-type: disc;">
                <li><?php esc_html_e('All supplier posts and their metadata', 'suppliers-manager-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Product-supplier relationships', 'suppliers-manager-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Email history logs', 'suppliers-manager-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Plugin settings and configuration', 'suppliers-manager-for-woocommerce'); ?></li>
            </ul>
            <p class="description" style="margin-top: 10px; color: #d63638; font-weight: 600;">
                ⚠️ <?php esc_html_e('Warning: This action cannot be undone. Make sure to backup your data before uninstalling.', 'suppliers-manager-for-woocommerce'); ?>
            </p>
        </fieldset>
        <?php
    }
}