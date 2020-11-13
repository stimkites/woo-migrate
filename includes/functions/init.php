<?php
/**
 * Main plugin entry point and basic functions
 */

namespace Wetail\Woo\Migration;

use WP_REST_Response;

/**
 * Logging data - shortcut function
 *
 * @param mixed $data
 *
 * @return mixed
 */
function log( $data ) {
	return Migrate::log( $data );
}

/**
 * Set or get status on current operation
 *
 * @param string $operation
 * @param int $progress
 * @param bool $fail
 * @param bool | array $completed
 *
 * @return bool| array | WP_REST_Response
 */
function status( $operation = '', $progress = null, $fail = false, $completed = false ) {
	log( $operation );
	if( ! empty( $operation ) && ! empty( $progress ) ) {
		$stat = [
			'operation' => $operation,
			'progress'  => $progress,
			'active'    => ! $fail,
			'completed' => $completed
		];
		update_option( '__wtwm_stat', $stat );
		return $operation;
	}
	if( get_transient( '__wtwm_migrate_data_from' ) )
		return Api::call( 'status' );
	return get_option( '__wtwm_stat' );
}

/**
 * Get option value or set all options shortcut
 *
 * @param string $key
 *
 * @return mixed
 */
function options( $key = null ){
	
	return Migrate::options( $key );
	
}

/**
 * Load plugin
 */
Migrate::load();