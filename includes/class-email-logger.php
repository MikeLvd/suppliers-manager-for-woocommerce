<?php
/**
 * Email Logger Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      2.1.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

use WC_Order;

/**
 * Email Logger class
 *
 * Handles logging of sent supplier emails to database.
 *
 * @since 2.1.0
 */
class Email_Logger
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
     * @since 2.1.0
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smfw_email_history';
    }

    /**
     * Create database table
     *
     * @since  2.1.0
     * @return void
     */
    public function create_table(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            supplier_id bigint(20) unsigned NOT NULL,
            supplier_name varchar(255) NOT NULL,
            supplier_email varchar(255) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            subject text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'sent',
            items_count int(11) NOT NULL DEFAULT 0,
            sent_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY supplier_id (supplier_id),
            KEY sent_at (sent_at),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log sent email
     *
     * @since  2.1.0
     * @param  int      $order_id       Order ID
     * @param  int      $supplier_id    Supplier term ID
     * @param  string   $supplier_name  Supplier name
     * @param  string   $supplier_email Supplier email
     * @param  string   $recipient      Actual recipient email
     * @param  string   $subject        Email subject
     * @param  int      $items_count    Number of items in email
     * @param  string   $status         Email status (sent/failed)
     * @return int|false Insert ID on success, false on failure
     */
    public function log_email(
        int $order_id,
        int $supplier_id,
        string $supplier_name,
        string $supplier_email,
        string $recipient,
        string $subject,
        int $items_count,
        string $status = 'sent'
    ) {
        global $wpdb;

        // Check if history is enabled
        if (!get_option('smfw_enable_email_history', true)) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'order_id'       => $order_id,
                'supplier_id'    => $supplier_id,
                'supplier_name'  => $supplier_name,
                'supplier_email' => $supplier_email,
                'recipient_email' => $recipient,
                'subject'        => $subject,
                'status'         => $status,
                'items_count'    => $items_count,
                'sent_at'        => current_time('mysql'),
                'created_at'     => current_time('mysql'),
            ],
            [
                '%d', // order_id
                '%d', // supplier_id
                '%s', // supplier_name
                '%s', // supplier_email
                '%s', // recipient_email
                '%s', // subject
                '%s', // status
                '%d', // items_count
                '%s', // sent_at
                '%s', // created_at
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get email history with filters
     *
     * @since  2.1.0
     * @param  array $args Query arguments
     * @return array Array of email log entries
     */
    public function get_history(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'order_id'    => 0,
            'supplier_id' => 0,
            'status'      => '',
            'date_from'   => '',
            'date_to'     => '',
            'limit'       => 20,
            'offset'      => 0,
            'orderby'     => 'sent_at',
            'order'       => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ['1=1'];
        $where_values = [];

        if ($args['order_id'] > 0) {
            $where[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }

        if ($args['supplier_id'] > 0) {
            $where[] = 'supplier_id = %d';
            $where_values[] = $args['supplier_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'sent_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'sent_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);

        // Build ORDER BY clause
        $allowed_orderby = ['id', 'order_id', 'supplier_name', 'sent_at', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'sent_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Build query
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, ...$where_values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get total count with filters
     *
     * @since  2.1.0
     * @param  array $args Query arguments
     * @return int Total count
     */
    public function get_total_count(array $args = []): int
    {
        global $wpdb;

        // Build WHERE clause (same as get_history)
        $where = ['1=1'];
        $where_values = [];

        if (!empty($args['order_id'])) {
            $where[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }

        if (!empty($args['supplier_id'])) {
            $where[] = 'supplier_id = %d';
            $where_values[] = $args['supplier_id'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'sent_at >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'sent_at <= %s';
            $where_values[] = $args['date_to'];
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, ...$where_values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Delete old email history
     *
     * @since  2.1.0
     * @param  int $days Number of days to retain
     * @return int|false Number of rows deleted, false on failure
     */
    public function cleanup_old_history(int $days = 90)
    {
        global $wpdb;

        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE sent_at < %s",
                $date
            )
        );
    }

    /**
     * Get statistics
     *
     * @since  2.1.0
     * @return array Statistics data
     */
    public function get_statistics(): array
    {
        global $wpdb;

        $stats = [];

        // Total emails sent
        $stats['total_sent'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'sent'"
        );

        // Total failed
        $stats['total_failed'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"
        );

        // This month
        $stats['this_month'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE sent_at >= %s AND status = 'sent'",
                gmdate('Y-m-01 00:00:00')
            )
        );

        // Today
        $stats['today'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE DATE(sent_at) = %s AND status = 'sent'",
                gmdate('Y-m-d')
            )
        );

        return $stats;
    }
}
