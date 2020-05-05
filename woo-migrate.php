<?php
/**
 * Plugin Name: WooCommerce Site-to-Site Migration
 * Plugin URI: https://wetail.io
 * Description: Simple way to migrate your Woo data from one WP instance into another.
 * Version: 0.0.1
 * Author: Wetail AB
 * Author URI: https://wetail.io
 * License: GPL3
 *
 * @Note: SASS/SCSS/JS is built using phpStorm file watchers
 *
 * @Note: Do not forget to update the version below!
 */

namespace Wetail\Woo\Migration;

/**
 * Plugin version for verifications
 */
const VERSION = '0.0.1';

/**
 * Plugin constants
 */
define( __NAMESPACE__ . '\LNG',   'wetail-migrate'                               );
define( __NAMESPACE__ . '\PATH',  __DIR__                                        );
define( __NAMESPACE__ . '\TPL',   PATH . '/assets/templates'                     );
define( __NAMESPACE__ . '\INDEX', __FILE__                                       );
define( __NAMESPACE__ . '\NAME',  dirname( plugin_basename( __FILE__ ) )         );
define( __NAMESPACE__ . '\URL',   plugins_url( basename( __DIR__ ) . '/assets' ) );
define( __NAMESPACE__ . '\ID',    plugin_basename( __FILE__ )                    );

/**
 * Plugin dependencies
 */
const DEPS = [ 'WooCommerce' ];

/**
 * Initialize autoloader
 */
require_once "autoload.php";

/**
 * Load plugin
 */
Migrate::load();