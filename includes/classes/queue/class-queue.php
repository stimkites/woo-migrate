<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/21/20
 * Time: 12:59 PM
 */

namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Queue
 *
 * @package Wetail\Woo\Migration
 */
final class Queue {

	/**
	 * Initialize queue
	 *
	 * @param $operations
	 */
	public static function init( $operations ){
		self::cleanup();
		self::add( $operations );
	}

	/**
	 * Cleanup queue
	 */
	protected static function cleanup(){
		global $wpdb;
		$t = "{$wpdb->prefix}woo_migrate";
		$wpdb->query( "DROP IF EXISTS $t" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS $t ( " .
		              "`id`       bigint(20) not null auto_increment, " .
		              "`call`     varchar(77) not null default '', " .
		              "`start`    bigint(20) not null default 0, " .
		              "`total`    bigint(20) not null default 0, " .
		              "`active`   smallint(1) not null default 0, " .
		              "`added`    TIMESTAMP not null default CURRENT_TIMESTAMP, " .
		              "UNIQUE( `id` ), PRIMARY KEY( `id` ) )" );
	}

	/**
	 * Add operations to the queue
	 *
	 * @param $operations
	 *
	 * @return int
	 */
	protected static function add( $operations ){
		global $wpdb;
		$t = "{$wpdb->prefix}woo_migrate";
		$wpdb->insert( $t, $operations );
		return $wpdb->rows_affected;
	}


	
}