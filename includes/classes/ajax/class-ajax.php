<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:23 PM
 */

namespace Wetail\Woo\Migration;

use http\Env\Request;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Ajax
 *
 * @package Wetail\Woo\Migration
 */
final class Ajax {

	/**
	 * Initialize ajax hooks
	 */
	public static function init(){
		add_action( 'wp_ajax_wtwmgr', __CLASS__ . '::actions' );
	}

	/**
	 * Ajax actions
	 */
	public static function actions(){
		if( empty( $_POST['do'] ) ) return wp_send_json( [ 'error' => 'No action verb to perform!' ] );
		switch( $_POST['do'] ){
			case 'connect':
				options();
				return wp_send_json( Api::check_connection() );
			case 'launch':
				if( empty( $_POST['url'] ) )
					$_POST['url'] = options( 'url' );
				options();
				return wp_send_json( Api::trigger() );
			case 'transfer':
				return wp_send_json( Api::transfer() );
			case 'cancel':
				status( 'Cancelled!', 1, true );
				Writer::fail();
				delete_transient( '__wtwm_migrate_data_from' );
				$dir = wp_get_upload_dir()['path'] . '/woomigrate/';
				$zipfile = rtrim( $dir, '/' ) . '.zip';
				if( file_exists( $zipfile ) )
					unlink( $zipfile );
				delete_transient( '__wtwm_migrate_data_from' );
				Api::call( 'interrupt' );
				return wp_send_json( [ 'result' => 'Ok' ] );
			case 'stop':
				status( 'Interrupted...', 1, true );
				delete_transient( '__wtwm_migrate_data_from' );
				Api::call( 'interrupt' );
			default:
				$status = status();
				if( $status['completed'] && ! file_exists( $status['completed']['zip'] ) )
					status( 'Zip File removed!', 1, true );
				return wp_send_json( status() );
		}
	}



}