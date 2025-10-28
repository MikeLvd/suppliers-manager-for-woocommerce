<?php
/**
 * Plugin Activator Class with Migration
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

/**
 * Activator class
 *
 * Handles plugin activation, database setup, and migration from v2.x taxonomy to v3.0 CPT.
 *
 * @since 3.0.0
 */
class Activator
{
    /**
     * Execute activation tasks
     *
     * @since  3.0.0
     * @return void
     */
    public static function activate(): void
    {
        // Load required classes
        if (!class_exists('Suppliers_Manager_For_WooCommerce\Email_Logger')) {
            require_once SMFW_PLUGIN_DIR . 'includes/class-email-logger.php';
        }
        if (!class_exists('Suppliers_Manager_For_WooCommerce\Supplier_Relationships')) {
            require_once SMFW_PLUGIN_DIR . 'includes/class-supplier-relationships.php';
        }

        // Create email history table
        $logger = new Email_Logger();
        $logger->create_table();

        // Create supplier relationships table
        $relationships = new Supplier_Relationships();
        $relationships->create_table();

        // Check if this is an upgrade from v2.x (taxonomy-based)
        $old_version = get_option('smfw_version', '0.0.0');
        $needs_migration = version_compare($old_version, '3.0.0', '<') && self::has_legacy_taxonomy_data();

        if ($needs_migration) {
            // Run migration from taxonomy to CPT
            self::migrate_taxonomy_to_cpt();
        }

        // Flush rewrite rules to ensure custom post types work
        flush_rewrite_rules();

        // Set plugin version
        update_option('smfw_version', SMFW_VERSION);

        // Set default settings if they don't exist
        if (false === get_option('smfw_notification_status')) {
            update_option('smfw_notification_status', 'processing');
        }

        if (false === get_option('smfw_enable_email_history')) {
            update_option('smfw_enable_email_history', '1');
        }

        if (false === get_option('smfw_bcc_admin')) {
            update_option('smfw_bcc_admin', '1');
        }

        if (false === get_option('smfw_admin_email')) {
            update_option('smfw_admin_email', get_option('admin_email'));
        }

        if (false === get_option('smfw_history_retention_days')) {
            update_option('smfw_history_retention_days', 90);
        }

        // Schedule cleanup cron job
        if (!wp_next_scheduled('smfw_cleanup_email_history')) {
            wp_schedule_event(time(), 'daily', 'smfw_cleanup_email_history');
        }

        // Log activation
        if (function_exists('wc_get_logger')) {
            $wc_logger = wc_get_logger();
            $message = 'Suppliers Manager for WooCommerce v' . SMFW_VERSION . ' activated';
            if ($needs_migration) {
                $message .= ' (migrated from v' . $old_version . ')';
            }
            $wc_logger->info($message, ['source' => 'suppliers-manager']);
        }
    }

    /**
     * Check if legacy taxonomy data exists
     *
     * @since  3.0.0
     * @return bool True if legacy data exists
     */
    private static function has_legacy_taxonomy_data(): bool
    {
        $terms = get_terms([
            'taxonomy'   => 'supplier',
            'hide_empty' => false,
            'fields'     => 'ids',
            'number'     => 1,
        ]);

        return !is_wp_error($terms) && !empty($terms);
    }

    /**
     * Migrate taxonomy data to Custom Post Type
     *
     * @since  3.0.0
     * @return void
     */
    private static function migrate_taxonomy_to_cpt(): void
    {
        global $wpdb;

        // Get all supplier terms
        $terms = get_terms([
            'taxonomy'   => 'supplier',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        $relationships = new Supplier_Relationships();
        $migrated = 0;
        $errors = 0;

        foreach ($terms as $term) {
            try {
                // Create supplier post
                $post_data = [
                    'post_title'   => $term->name,
                    'post_content' => $term->description ?? '',
                    'post_status'  => 'publish',
                    'post_type'    => 'supplier',
                    'post_author'  => get_current_user_id() ?: 1,
                ];

                $supplier_id = wp_insert_post($post_data, true);

                if (is_wp_error($supplier_id)) {
                    throw new \Exception('Failed to create supplier post: ' . $supplier_id->get_error_message());
                }

                // Migrate term meta to post meta
                $email = get_term_meta($term->term_id, 'supplier_email', true);
                $telephone = get_term_meta($term->term_id, 'supplier_telephone', true);
                $address = get_term_meta($term->term_id, 'supplier_address', true);
                $contact = get_term_meta($term->term_id, 'supplier_contact', true);

                if ($email) {
                    update_post_meta($supplier_id, '_supplier_email', sanitize_email($email));
                }
                if ($telephone) {
                    update_post_meta($supplier_id, '_supplier_telephone', sanitize_text_field($telephone));
                }
                if ($address) {
                    update_post_meta($supplier_id, '_supplier_address', sanitize_textarea_field($address));
                }
                if ($contact) {
                    update_post_meta($supplier_id, '_supplier_contact', sanitize_text_field($contact));
                }

                // Store old term ID for reference
                update_post_meta($supplier_id, '_migrated_from_term_id', $term->term_id);

                // Get all products assigned to this supplier term
                $products = get_objects_in_term($term->term_id, 'supplier');

                if (!is_wp_error($products) && !empty($products)) {
                    foreach ($products as $product_id) {
                        // Create relationship in new table
                        $relationships->add_relationship((int) $product_id, $supplier_id, false);
                    }
                }

                $migrated++;

            } catch (\Exception $e) {
                $errors++;
                if (function_exists('wc_get_logger')) {
                    $wc_logger = wc_get_logger();
                    $wc_logger->error(
                        sprintf('Migration error for term %d (%s): %s', $term->term_id, $term->name, $e->getMessage()),
                        ['source' => 'suppliers-manager-migration']
                    );
                }
            }
        }

        // Store migration stats
        update_option('smfw_migration_stats', [
            'migrated'   => $migrated,
            'errors'     => $errors,
            'total'      => count($terms),
            'date'       => current_time('mysql'),
            'from_version' => get_option('smfw_version', '2.1.1'),
        ]);

        // Log migration completion
        if (function_exists('wc_get_logger')) {
            $wc_logger = wc_get_logger();
            $wc_logger->info(
                sprintf(
                    'Migration completed: %d suppliers migrated, %d errors from %d total terms',
                    $migrated,
                    $errors,
                    count($terms)
                ),
                ['source' => 'suppliers-manager-migration']
            );
        }

        // Add admin notice about migration
        set_transient('smfw_migration_notice', [
            'migrated' => $migrated,
            'errors'   => $errors,
            'total'    => count($terms),
        ], 60 * 60 * 24); // 24 hours
    }

    /**
     * Display migration notice
     *
     * @since  3.0.0
     * @return void
     */
    public static function display_migration_notice(): void
    {
        $notice = get_transient('smfw_migration_notice');
        
        if (!$notice) {
            return;
        }

        $class = $notice['errors'] > 0 ? 'notice-warning' : 'notice-success';
        
        printf(
            '<div class="notice %s is-dismissible"><p><strong>%s</strong></p><p>%s</p></div>',
            esc_attr($class),
            esc_html__('Suppliers Manager Migration Complete', 'suppliers-manager-for-woocommerce'),
            sprintf(
                /* translators: 1: migrated count, 2: total count, 3: errors count */
                esc_html__('Successfully migrated %1$d of %2$d suppliers to the new Custom Post Type architecture. %3$s', 'suppliers-manager-for-woocommerce'),
                $notice['migrated'],
                $notice['total'],
                $notice['errors'] > 0 
                    ? sprintf(esc_html__('%d errors occurred. Check WooCommerce logs for details.', 'suppliers-manager-for-woocommerce'), $notice['errors'])
                    : esc_html__('No errors occurred.', 'suppliers-manager-for-woocommerce')
            )
        );

        // Delete transient after displaying
        delete_transient('smfw_migration_notice');
    }
}

// Hook to display migration notice
add_action('admin_notices', [__NAMESPACE__ . '\\Activator', 'display_migration_notice']);
