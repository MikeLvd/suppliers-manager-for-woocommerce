<?php
/**
 * Supplier Custom Post Type Handler
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
 * Supplier Post Type class
 *
 * Handles registration and management of the supplier custom post type.
 *
 * @since 3.0.0
 */
class Supplier_Post_Type
{
    /**
     * Post type name
     */
    private const POST_TYPE = 'supplier';

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
     * Register supplier custom post type
     *
     * @since  3.0.0
     * @return void
     */
    public function register_post_type(): void
    {
        $labels = [
            'name'                  => _x('Suppliers', 'Post type general name', 'suppliers-manager-for-woocommerce'),
            'singular_name'         => _x('Supplier', 'Post type singular name', 'suppliers-manager-for-woocommerce'),
            'menu_name'             => _x('Suppliers', 'Admin Menu text', 'suppliers-manager-for-woocommerce'),
            'name_admin_bar'        => _x('Supplier', 'Add New on Toolbar', 'suppliers-manager-for-woocommerce'),
            'add_new'               => __('Add New', 'suppliers-manager-for-woocommerce'),
            'add_new_item'          => __('Add New Supplier', 'suppliers-manager-for-woocommerce'),
            'new_item'              => __('New Supplier', 'suppliers-manager-for-woocommerce'),
            'edit_item'             => __('Edit Supplier', 'suppliers-manager-for-woocommerce'),
            'view_item'             => __('View Supplier', 'suppliers-manager-for-woocommerce'),
            'all_items'             => __('All Suppliers', 'suppliers-manager-for-woocommerce'),
            'search_items'          => __('Search Suppliers', 'suppliers-manager-for-woocommerce'),
            'parent_item_colon'     => __('Parent Suppliers:', 'suppliers-manager-for-woocommerce'),
            'not_found'             => __('No suppliers found.', 'suppliers-manager-for-woocommerce'),
            'not_found_in_trash'    => __('No suppliers found in Trash.', 'suppliers-manager-for-woocommerce'),
            'featured_image'        => _x('Supplier Logo', 'Overrides the "Featured Image" phrase', 'suppliers-manager-for-woocommerce'),
            'set_featured_image'    => _x('Set supplier logo', 'Overrides the "Set featured image" phrase', 'suppliers-manager-for-woocommerce'),
            'remove_featured_image' => _x('Remove supplier logo', 'Overrides the "Remove featured image" phrase', 'suppliers-manager-for-woocommerce'),
            'use_featured_image'    => _x('Use as supplier logo', 'Overrides the "Use as featured image" phrase', 'suppliers-manager-for-woocommerce'),
            'archives'              => _x('Supplier archives', 'The post type archive label', 'suppliers-manager-for-woocommerce'),
            'insert_into_item'      => _x('Insert into supplier', 'Overrides the "Insert into post" phrase', 'suppliers-manager-for-woocommerce'),
            'uploaded_to_this_item' => _x('Uploaded to this supplier', 'Overrides the "Uploaded to this post" phrase', 'suppliers-manager-for-woocommerce'),
            'filter_items_list'     => _x('Filter suppliers list', 'Screen reader text for the filter links', 'suppliers-manager-for-woocommerce'),
            'items_list_navigation' => _x('Suppliers list navigation', 'Screen reader text for the pagination', 'suppliers-manager-for-woocommerce'),
            'items_list'            => _x('Suppliers list', 'Screen reader text for the items list', 'suppliers-manager-for-woocommerce'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'woocommerce',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'capabilities'       => [
                'edit_post'          => 'manage_woocommerce',
                'read_post'          => 'manage_woocommerce',
                'delete_post'        => 'manage_woocommerce',
                'edit_posts'         => 'manage_woocommerce',
                'edit_others_posts'  => 'manage_woocommerce',
                'delete_posts'       => 'manage_woocommerce',
                'publish_posts'      => 'manage_woocommerce',
                'read_private_posts' => 'manage_woocommerce',
            ],
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-businessman',
            'supports'           => ['title', 'editor', 'thumbnail'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add custom columns to supplier list
     *
     * @since  3.0.0
     * @param  array<string, string> $columns Existing columns
     * @return array<string, string> Modified columns
     */
    public function add_custom_columns(array $columns): array
    {
        // Remove date column temporarily
        $date = $columns['date'] ?? '';
        unset($columns['date']);

        // Add custom columns
        $columns['supplier_logo'] = __('Logo', 'suppliers-manager-for-woocommerce');
        $columns['supplier_email'] = __('Email', 'suppliers-manager-for-woocommerce');
        $columns['supplier_phone'] = __('Phone', 'suppliers-manager-for-woocommerce');
        $columns['supplier_products'] = __('Products', 'suppliers-manager-for-woocommerce');
        $columns['supplier_status'] = __('Status', 'suppliers-manager-for-woocommerce');

        // Re-add date column at the end
        if ($date) {
            $columns['date'] = $date;
        }

        return $columns;
    }

    /**
     * Render custom column content
     *
     * @since  3.0.0
     * @param  string $column  Column name
     * @param  int    $post_id Post ID
     * @return void
     */
    public function render_custom_columns(string $column, int $post_id): void
    {
        switch ($column) {
            case 'supplier_logo':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [50, 50]);
                } else {
                    echo '<span class="dashicons dashicons-businessman" style="font-size: 40px; color: #ccc;"></span>';
                }
                break;

            case 'supplier_email':
                $email = get_post_meta($post_id, '_supplier_email', true);
                if ($email) {
                    printf('<a href="mailto:%s">%s</a>', esc_attr($email), esc_html($email));
                } else {
                    echo '—';
                }
                break;

            case 'supplier_phone':
                $phone = get_post_meta($post_id, '_supplier_telephone', true);
                echo $phone ? esc_html($phone) : '—';
                break;

            case 'supplier_products':
                $count = $this->relationships->get_supplier_products_count($post_id);
                if ($count > 0) {
                    printf(
                        '<a href="%s">%d</a>',
                        esc_url(add_query_arg(['supplier_id' => $post_id], admin_url('edit.php?post_type=product'))),
                        $count
                    );
                } else {
                    echo '0';
                }
                break;

            case 'supplier_status':
                $status = get_post_status($post_id);
                $status_obj = get_post_status_object($status);
                if ($status_obj) {
                    printf(
                        '<span class="supplier-status status-%s">%s</span>',
                        esc_attr($status),
                        esc_html($status_obj->label)
                    );
                }
                break;
        }
    }

    /**
     * Make custom columns sortable
     *
     * @since  3.0.0
     * @param  array<string, string> $columns Sortable columns
     * @return array<string, string> Modified sortable columns
     */
    public function make_columns_sortable(array $columns): array
    {
        $columns['supplier_email'] = 'supplier_email';
        $columns['supplier_phone'] = 'supplier_phone';
        $columns['supplier_products'] = 'supplier_products';
        $columns['supplier_status'] = 'post_status';

        return $columns;
    }

    /**
     * Handle custom column sorting
     *
     * @since  3.0.0
     * @param  \WP_Query $query Query object
     * @return void
     */
    public function handle_column_sorting(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        switch ($orderby) {
            case 'supplier_email':
                $query->set('meta_key', '_supplier_email');
                $query->set('orderby', 'meta_value');
                break;

            case 'supplier_phone':
                $query->set('meta_key', '_supplier_telephone');
                $query->set('orderby', 'meta_value');
                break;

            case 'supplier_products':
                // Custom sorting by products count would require complex SQL
                // For now, keep default sorting
                break;
        }
    }

    /**
     * Add filter dropdowns to supplier list
     *
     * @since  3.0.0
     * @param  string $post_type Current post type
     * @return void
     */
    public function add_filter_dropdowns(string $post_type): void
    {
        if ($post_type !== self::POST_TYPE) {
            return;
        }

        // Status filter is already added by WordPress
        
        // Add custom filters if needed in future
    }

    /**
     * Customize enter title placeholder
     *
     * @since  3.0.0
     * @param  string   $title Placeholder text
     * @param  \WP_Post $post  Post object
     * @return string Modified placeholder
     */
    public function customize_title_placeholder(string $title, \WP_Post $post): string
    {
        if ($post->post_type === self::POST_TYPE) {
            $title = __('Enter supplier name', 'suppliers-manager-for-woocommerce');
        }

        return $title;
    }

    /**
     * Add supplier count to dashboard "At a Glance" widget
     *
     * @since  3.0.0
     * @param  array $items Glance items
     * @return array Modified items
     */
    public function add_glance_item(array $items): array
    {
        if (!current_user_can('manage_woocommerce')) {
            return $items;
        }

        $count = wp_count_posts(self::POST_TYPE);
        $published = (int) ($count->publish ?? 0);

        if ($published > 0) {
            $text = sprintf(
                _n('%s Supplier', '%s Suppliers', $published, 'suppliers-manager-for-woocommerce'),
                number_format_i18n($published)
            );

            $items[] = sprintf(
                '<a href="%s" class="supplier-count">%s</a>',
                esc_url(admin_url('edit.php?post_type=supplier')),
                esc_html($text)
            );
        }

        return $items;
    }

    /**
     * Delete supplier relationships when supplier is deleted
     *
     * @since  3.0.0
     * @param  int $post_id Post ID
     * @return void
     */
    public function delete_supplier_relationships(int $post_id): void
    {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        $this->relationships->delete_supplier_relationships($post_id);
    }

    /**
     * Get post type name
     *
     * @since  3.0.0
     * @return string Post type name
     */
    public static function get_post_type(): string
    {
        return self::POST_TYPE;
    }
}
