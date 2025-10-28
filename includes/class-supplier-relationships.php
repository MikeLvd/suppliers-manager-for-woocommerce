<?php
/**
 * Supplier Relationships Manager
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

/**
 * Supplier Relationships class
 *
 * Manages many-to-many relationships between products and suppliers
 * using a custom database table.
 *
 * @since 3.0.0
 */
class Supplier_Relationships
{
    /**
     * Database table name
     *
     * @var string
     */
    private string $table_name;

    /**
     * Initialize the class
     *
     * @since 3.0.0
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smfw_product_suppliers';
    }

    /**
     * Create relationships table
     *
     * @since  3.0.0
     * @return void
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            supplier_id bigint(20) unsigned NOT NULL,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY product_supplier (product_id, supplier_id),
            KEY product_id (product_id),
            KEY supplier_id (supplier_id),
            KEY is_primary (is_primary)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add product-supplier relationship
     *
     * @since  3.0.0
     * @param  int  $product_id  Product ID
     * @param  int  $supplier_id Supplier post ID
     * @param  bool $is_primary  Is this the primary supplier
     * @return int|false Relationship ID on success, false on failure
     */
    public function add_relationship(int $product_id, int $supplier_id, bool $is_primary = false)
    {
        global $wpdb;

        // If setting as primary, unset other primary relationships for this product
        if ($is_primary) {
            $this->unset_primary_supplier($product_id);
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'product_id'  => $product_id,
                'supplier_id' => $supplier_id,
                'is_primary'  => $is_primary ? 1 : 0,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Remove product-supplier relationship
     *
     * @since  3.0.0
     * @param  int $product_id  Product ID
     * @param  int $supplier_id Supplier post ID
     * @return int|false Number of rows deleted, false on failure
     */
    public function remove_relationship(int $product_id, int $supplier_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            [
                'product_id'  => $product_id,
                'supplier_id' => $supplier_id,
            ],
            ['%d', '%d']
        );
    }

    /**
     * Get all suppliers for a product
     *
     * @since  3.0.0
     * @param  int $product_id Product ID
     * @return array Array of supplier IDs
     */
    public function get_product_suppliers(int $product_id): array
    {
        global $wpdb;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT supplier_id FROM {$this->table_name} WHERE product_id = %d ORDER BY is_primary DESC, id ASC",
                $product_id
            )
        );

        return array_map('intval', $results);
    }

    /**
     * Get all products for a supplier
     *
     * @since  3.0.0
     * @param  int $supplier_id Supplier post ID
     * @return array Array of product IDs
     */
    public function get_supplier_products(int $supplier_id): array
    {
        global $wpdb;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT product_id FROM {$this->table_name} WHERE supplier_id = %d ORDER BY id ASC",
                $supplier_id
            )
        );

        return array_map('intval', $results);
    }

    /**
     * Get primary supplier for a product
     *
     * @since  3.0.0
     * @param  int $product_id Product ID
     * @return int|null Supplier ID or null if no primary supplier
     */
    public function get_primary_supplier(int $product_id): ?int
    {
        global $wpdb;

        $supplier_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT supplier_id FROM {$this->table_name} WHERE product_id = %d AND is_primary = 1 LIMIT 1",
                $product_id
            )
        );

        return $supplier_id ? (int) $supplier_id : null;
    }

    /**
     * Set primary supplier for a product
     *
     * @since  3.0.0
     * @param  int $product_id  Product ID
     * @param  int $supplier_id Supplier post ID
     * @return bool True on success, false on failure
     */
    public function set_primary_supplier(int $product_id, int $supplier_id): bool
    {
        global $wpdb;

        // First unset all primary flags for this product
        $this->unset_primary_supplier($product_id);

        // Then set the new primary supplier
        $result = $wpdb->update(
            $this->table_name,
            ['is_primary' => 1],
            [
                'product_id'  => $product_id,
                'supplier_id' => $supplier_id,
            ],
            ['%d'],
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Unset primary supplier for a product
     *
     * @since  3.0.0
     * @param  int $product_id Product ID
     * @return int|false Number of rows updated, false on failure
     */
    public function unset_primary_supplier(int $product_id)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            ['is_primary' => 0],
            ['product_id' => $product_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Update product suppliers (replace all)
     *
     * @since  3.0.0
     * @param  int   $product_id   Product ID
     * @param  array $supplier_ids Array of supplier post IDs
     * @param  int   $primary_id   Primary supplier ID (optional)
     * @return bool True on success, false on failure
     */
    public function update_product_suppliers(int $product_id, array $supplier_ids, int $primary_id = 0): bool
    {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete existing relationships
            $wpdb->delete(
                $this->table_name,
                ['product_id' => $product_id],
                ['%d']
            );

            // Add new relationships
            foreach ($supplier_ids as $supplier_id) {
                $is_primary = ($supplier_id === $primary_id);
                
                $result = $wpdb->insert(
                    $this->table_name,
                    [
                        'product_id'  => $product_id,
                        'supplier_id' => (int) $supplier_id,
                        'is_primary'  => $is_primary ? 1 : 0,
                        'created_at'  => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%s']
                );

                if ($result === false) {
                    throw new \Exception('Failed to insert relationship');
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');
            return true;

        } catch (\Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Delete all relationships for a product
     *
     * @since  3.0.0
     * @param  int $product_id Product ID
     * @return int|false Number of rows deleted, false on failure
     */
    public function delete_product_relationships(int $product_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            ['product_id' => $product_id],
            ['%d']
        );
    }

    /**
     * Delete all relationships for a supplier
     *
     * @since  3.0.0
     * @param  int $supplier_id Supplier post ID
     * @return int|false Number of rows deleted, false on failure
     */
    public function delete_supplier_relationships(int $supplier_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            ['supplier_id' => $supplier_id],
            ['%d']
        );
    }

    /**
     * Check if relationship exists
     *
     * @since  3.0.0
     * @param  int $product_id  Product ID
     * @param  int $supplier_id Supplier post ID
     * @return bool True if relationship exists, false otherwise
     */
    public function relationship_exists(int $product_id, int $supplier_id): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE product_id = %d AND supplier_id = %d",
                $product_id,
                $supplier_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Get products count for a supplier
     *
     * @since  3.0.0
     * @param  int $supplier_id Supplier post ID
     * @return int Products count
     */
    public function get_supplier_products_count(int $supplier_id): int
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE supplier_id = %d",
                $supplier_id
            )
        );

        return (int) $count;
    }

    /**
     * Get table name
     *
     * @since  3.0.0
     * @return string Table name
     */
    public function get_table_name(): string
    {
        return $this->table_name;
    }
}
