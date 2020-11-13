<?php
/**
 * Plugin Name: Woo Migrate
 * Plugin URI: https://nexus.ooo
 * Description: Simple way to migrate your WooCommerce data - orders, products, customers, taxonomies and many more - from one WP instance into another.
 * Version: 0.0.2
 * Author: Nexus OOO
 * Author URI: https://nexus.ooo
 * License: GPL3
 *
 * @Note: SASS/SCSS/JS is built using phpStorm file watchers
 *
 * @Note: Do not forget to update the version below as it is involved during migration process!
 */

namespace Wetail\Woo\Migration;

/**
 * Plugin version for verifications
 */
const VERSION = '0.0.2';

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
 * Initialize PSR-4 compressed autoloader
 */
require_once "loader.php";