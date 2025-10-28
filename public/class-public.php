<?php
/**
 * Public Frontend Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Public
 * @author     Mike Lvd
 * @since      2.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce\PublicFrontend;

/**
 * Public class
 *
 * Handles public-facing functionality.
 * Currently, this plugin doesn't require public-facing features.
 *
 * @since 2.0.0
 */
class PublicFrontend
{
    /**
     * Plugin identifier
     *
     * @var string
     */
    private string $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     */
    private string $version;

    /**
     * Initialize the class
     *
     * @since 2.0.0
     * @param string $plugin_name Plugin identifier
     * @param string $version     Plugin version
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Get plugin name
     *
     * @since  2.0.0
     * @return string Plugin identifier
     */
    public function get_plugin_name(): string
    {
        return $this->plugin_name;
    }

    /**
     * Get plugin version
     *
     * @since  2.0.0
     * @return string Plugin version
     */
    public function get_version(): string
    {
        return $this->version;
    }
}
