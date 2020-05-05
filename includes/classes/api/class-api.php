<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:18 PM
 */


namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Api
 *
 * @package Wetail\Woo\Migration
 */
final class Api {

	/**
	 * Trusted key
	 */
	const exkey = 'eyJhbGciOiJSUzU000000eyJzZXNzaW9uX2lkIjoiMWY3MTMzZjgtMDBjOS00OTQ3LTliYzctODU4NTZmYzllZGJhIiwic2Vzc';

	/**
	 * API prefix
	 */
	const PREFIX = '/woo-migrate';

	/**
	 * Operation status - slug for transient
	 */
	const stat = 'wtwm_opstatus';

	/**
	 * Initialize REST Api hooks
	 */
	public static function init(){
		add_action( 'rest_api_init',    __CLASS__ . '::register_all_routes' );
	}

	/******************************************* REMOTE CALLS *********************************************************/

	/**
	 * Check auth
	 *
	 * @return bool
	 */
	public static function auth(){
		return( ! empty( $_SERVER["HTTP_X_AUTH"] ) && self::exkey === $_SERVER["HTTP_X_AUTH"] );
	}

	/**
	 * Response and log data
	 *
	 * @param string $data
	 * @param int $code
	 *
	 * @return \WP_REST_Response
	 */
	private static function response( $data = '', $code = 200 ){
		log( 'Api::response' );
		return new \WP_REST_Response( log( $data ), $code );
	}

	/**
	 * Register routes
	 */
	public static function register_all_routes() {
		$namespace = self::PREFIX;
		$end_points = [
			'connect/(?P<timestamp>[\d]+)'    => [ 'GET',   __CLASS__ . '::connect'     ],
			'migrate/(?P<timestamp>[\d]+)'    => [ 'POST',  __CLASS__ . '::migrate'     ],
			'ping/(?P<timestamp>[\d]+)'       => [ 'HEAD',  __CLASS__ . '::ping'        ],
			'pong/(?P<timestamp>[\d]+)'       => [ 'POST',  __CLASS__ . '::pong'        ],
			'merge/(?P<timestamp>[\d]+)'      => [ 'POST',  __CLASS__ . '::merge'       ],
			'verify/(?P<timestamp>[\d]+)'     => [ 'GET',   __CLASS__ . '::verify'      ],
			'interrupt/(?P<timestamp>[\d]+)'  => [ 'GET',   __CLASS__ . '::interrupt'   ],
			'status/(?P<timestamp>[\d]+)'     => [ 'GET',   __CLASS__ . '::status'      ],
			'trigger/(?P<timestamp>[\d]+)'    => [ 'HEAD',  __CLASS__ . '::launch'      ]
		];
		foreach( $end_points as $end_point => $call )
			register_rest_route( $namespace, '/' . $end_point, [
					[
						'methods'             => $call[0],
						'callback'            => $call[1],
						'permission_callback' => __CLASS__ . '::auth'
					]
				]
			);
	}

	/**
	 * Interrupt current progress
	 *
	 * @Note: on merging may be dangerous, therefore bypassed
	 */
	public static function interrupt(){
		self::response( status( 'Interrupted...', 1, true ) );
	}

	/**
	 * Connection check
	 *
	 * @return \WP_REST_Response
	 */
	public static function connect(){
		log( __FUNCTION__ );
		global $wp_version;
		return self::response( [
			'self_version'  => VERSION,
			'woo_version'   => WC()->version,
			'wp_version'    => $wp_version,
			'free'          => disk_free_space( wp_get_upload_dir()['path'] ),
			'zip'           => class_exists( 'ZipArchive' )
		] );
	}

	/**
	 * Get status on the operation
	 *
	 * @return \WP_REST_Response | null | bool
	 */
	public static function status(){
		return self::response( status() );
	}

	/**
	 * Verification
	 *
	 * @return \WP_REST_Response
	 */
	public static function verify(){
		log( __FUNCTION__ );
		if( ! ( $received_data = get_transient( '__wtwm_migrate_data_to' ) ) )
			return self::response( [ 'error' => status( 'No data received!', 1, true ) ] );
		$fn = rtrim( Writer::fn( "" ), '/' ) . '.zip';
		if( ! file_exists( $fn ) )
			return self::response( [ 'error' => status( 'Zip is not found on remote host!', 1, true ) ] );
		$hash = md5_file( $fn );
		$size = filesize( $fn );
		if( $received_data['hash'] !== $hash )
			return self::response( [ 'error' => status( 'Failure on hash compare!', 1, true ) ] );
		if( $received_data['zsize'] !== $size )
			return self::response( [ 'error' => status( 'Failure on size compare!', 1, true ) ] );
		return self::response( true );
	}

	/**
	 * Perform launching on migration
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public static function migrate( $request ){
		log( __FUNCTION__ );
		$data = log( $request->get_json_params() );
		set_transient( '__wtwm_migrate_data_to', $data, 1800 );
		status( 'Transferring...', 1 );
		self::call( 'ping', 'HEAD', null, 1 );
		return self::response( true );
	}

	/**
	 * Ping-pong in action:
	 *  ping (from, local)    - send 4Mb of data on transferring, sync call pong
	 *  pong (to,   remote)   - response with the status on received data, async call ping
	 */

	/**
	 * Send chunk if needed
	 */
	public static function ping(){
		log( __FUNCTION__ );
		$send_info = get_transient( '__wtwm_migrate_data_from' );
		$offset = $send_info['sent'] ?? 0;
		log( 'Sending chunk...' );
		if( $offset < $send_info['zsize'] ){
			$f = fopen( $send_info['zip'], 'r' );
			fseek( $f, $offset );
			$chunk = fread( $f, 1024*1024 );
			fclose( $f );
			$r = self::call( 'pong', 'POST', $chunk, 600, true );
			if( $r && ! empty( $r['received'] ) ) {
				$send_info['sent'] = $r['received'];
				set_transient( '__wtwm_migrate_data_from', $send_info );
			}
		} else {
			// Verify call
			$r = self::call( 'verify' );
			if( ! $r || $r['error'] ) return self::response( false );
			self::call( 'merge', 'POST', null, 1 );
			status( 'Merge operation launched!', 1 );
		}
		return self::response( true );
	}

	/**
	 * Save chunk and trigger next one
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public static function pong( $request ){
		$data = $request->get_body();
		$received = Writer::raw( $data );
		$send_info = get_transient( '__wtwm_migrate_data_to' );
		$offset = $received;
		$progress = $send_info['zsize'] / 99 * $offset;
		status( 'Transferring data...', $progress );
		if( $received < $send_info['zsize'] )
			self::call( 'ping', 'HEAD', null, 1 );
		return self::response( [ 'received' => $received ] );
	}

	/**
	 * Trigger merge operation (unstoppable)
	 *
	 * @return \WP_REST_Response
	 */
	public static function merge(){
		return self::response( [ 'result' => 'Ok' ] );
	}


	/******************************************* LOCAL CALLS **********************************************************/

	/**
	 * Start data transferring
	 *
	 * @return array
	 */
	public static function transfer(){
		$stat = status();
		self::call( 'migrate', 'POST', $stat['completed'], 3 );
		set_transient( '__wtwm_migrate_data_from', $stat['completed'], 1800 );
		return [ 'result' => 'Ok' ];
	}

	/**
	 * Trigger local launch event
	 *
	 * @return array
	 */
	public static function trigger(){
		log( 'Triggering...' );
		$url = log( rtrim( get_rest_url(), '/' ) . Api::PREFIX . '/trigger/' . time() );
		@file_get_contents( $url, false,  stream_context_create( [
			'http' => [
				'method'            => 'HEAD',
				'timeout'           => 1,
				'ignore_errors'     => true,
				'convertHtmlToText' => false,
				'header'            => "X-Auth: " . Api::exkey . "\r\n" .
				                       "Content-type: application/json" . "\r\n"
			]
		] ) );
		return [ 'result' => 'Ok' ];
	}

	/**
	 * Perform a request from local to remote host
	 *
	 * @param string $ep
	 * @param string $method
	 * @param null $data
	 * @param int $timeout
	 * @param bool $raw
	 *
	 * @return array
	 */
	public static function call( $ep = 'status', $method = 'GET', $data = null, $timeout = 10, $raw = false ){

		$url = trailingslashit( options('url') ) . 'wp-json' . Api::PREFIX . '/' . ltrim( $ep, '/' ) . '/' . time();

		log( __FUNCTION__ . ': ' . $url );

		try{
			$r = @file_get_contents( $url, false, stream_context_create( [
				'http' => [
					'method'            => $method,
					'timeout'           => $timeout,
					'ignore_errors'     => true,
					'convertHtmlToText' => false,
					'header'            => "X-Auth: " . Api::exkey . "\r\n" .
					                       ( $raw
						                       ? "Content-type: multipart/form-data"
						                       : "Content-type: application/json" ) . "\r\n" .
					                       ( $raw ? "" : "Accept: application/json" )
				],
				'body' => ( $raw ? $data : json_encode( $data ) )
			] ) );

			if( ! empty( $r ) ) {
				$r = json_decode( $r, true );
				$r['header'] = $http_response_header[0];
			}

			return log( $r );

		} catch ( \Exception $e ){
			log( $e );

			return null;
		}
	}

	/**
	 * Check remote connection and launch
	 *
	 * @return array
	 */
	public static function check_connection(){
		$remote = self::call( 'connect' );
		if( ! $remote ) return wp_send_json( [ 'error' => 'Could not connect!' ] );
		if( false === strpos( $remote['header'], '200' ) )
			[
				'error' => 'There was an error [' . $remote['header'] . ']. Please, check log.',
				'log'   => $remote
			];
		if( $remote['self_version'] !== VERSION )
			return [
				'error' => 'Plugin versions you are trying to use are different. Local is ' . VERSION
				           . ', while remote one is ' . $remote['self_version'] . ' - please, update.'
			];
		if( $remote['woo_version'] !== WC()->version )
			return [
				'error' => 'WooCommerce versions are different. Local is ' . WC()->version
				           . ', while remote one is ' . $remote['woo_version'] . ' - please, update.'
			];
		global $wp_version;
		if( $remote['wp_version'] !== $wp_version )
			return [
				'error' => 'WordPress versions are different. Local is ' . $wp_version
				           . ', while remote one is ' . $remote['wp_version'] . ' - please, update.'
			];
		if( $remote['free'] < 1 * 1024 * 1024 * 1024 )
			return [
				'error' => 'Available free space on remote host is below 1Gb required (' .
				           floor( $remote['free'] / 1024 ) . 'Mb).'
			];
		if( empty( $remote['zip'] ) )
			return [
				'error' => 'Remote host has no ZipArchive PHP extension installed. Please, install.'
			];
		return [ 'result' => 'Ok' ];
	}

	/**
	 * Preform launch on data collecting
	 *
	 * @return \WP_REST_Response
	 */
	public static function launch(){
		return self::response( Collect::launch() );
	}

	/**
	 * Interrupt active operation
	 */
	public static function alive(){
		$stat = status();
		if( ! $stat['active'] ) die( Writer::fail( $stat ) );
	}



}