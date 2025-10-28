<?php
/**
 * Plugin Loader Class
 *
 * @package    Suppliers_Manager_For_WooCommerce
 * @subpackage Includes
 * @author     Mike Lvd
 * @since      3.0.0
 */

declare(strict_types=1);

namespace Suppliers_Manager_For_WooCommerce;

/**
 * Loader class
 *
 * Registers all actions and filters for the plugin.
 *
 * @since 3.0.0
 */
class Loader
{
    /**
     * Array of actions registered with WordPress
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $actions = [];

    /**
     * Array of filters registered with WordPress
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $filters = [];

    /**
     * Add a new action to the collection
     *
     * @since  3.0.0
     * @param  string   $hook          The name of the WordPress action
     * @param  object   $component     The object on which the action is defined
     * @param  string   $callback      The name of the function definition
     * @param  int      $priority      Optional. Priority at which the function should fire. Default 10
     * @param  int      $accepted_args Optional. Number of arguments that should be passed. Default 1
     * @return void
     */
    public function add_action(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Add a new filter to the collection
     *
     * @since  3.0.0
     * @param  string   $hook          The name of the WordPress filter
     * @param  object   $component     The object on which the filter is defined
     * @param  string   $callback      The name of the function definition
     * @param  int      $priority      Optional. Priority at which the function should fire. Default 10
     * @param  int      $accepted_args Optional. Number of arguments that should be passed. Default 1
     * @return void
     */
    public function add_filter(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Register the filters and actions with WordPress
     *
     * @since  3.0.0
     * @return void
     */
    public function run(): void
    {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
