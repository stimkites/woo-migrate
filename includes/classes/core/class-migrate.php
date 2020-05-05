<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:17 PM
 */

namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Migrate
 *
 * @package Wetail\Woo\Migration
 */
final class Migrate {

	/**
	 * Prevent duplicated calls
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Woo tab and settings slug
	 */
	const slug = 'migrationissimo';

	/**
	 * Load all
	 */
	public static function load(){
		Ajax::init();
		Api ::init();
		add_action( 'plugins_loaded', __CLASS__ . '::init' );
	}

	/**
	 * Check dependencies
	 *
	 * @return bool
	 */
	private static function deps(){
		foreach( DEPS as $dep )
			if ( ! class_exists( $dep ) ) {
				log( 'Could not satisfy dependency: ' . $dep );
				add_filter( 'plugin_row_meta',  function( $meta, $plugin ){
					if( ID !== $plugin ) return $meta;
					$meta []= '<p style="margin: 20px 0;padding: 10px;border-left:4px solid orangered"><b>' .
					            __( 'Plugin could not be loaded due to missing dependency. Check debug log!', LNG ) .
					          '</b></p>';
					return $meta;
				}, 10, 2 );
				return false;
			}
		return true;
	}

	/**
	 * Initialize hooks
	 */
	public static function init(){

		if( ! self::deps() || self::$initialized ) return;

		add_action( 'init', __CLASS__ . '::textdomain' );
		
		// Add settings tab as the last one in Woo
		add_filter( 'woocommerce_settings_tabs_array',              __CLASS__ . '::add_tab',    9999, 1 );
		add_action( 'woocommerce_settings_tabs_' . self::slug,      __CLASS__ . '::render_tab'          );

		if( isset( $_POST['wtwm-save-options'] ) )
			add_action( 'woocommerce_init',                         __CLASS__ . '::options'             );

		// Settings link from plugins page
		add_filter( 'plugin_action_links_' . ID,                    __CLASS__ . '::settings_link'       );

		if( self::is_it_me() ){

			// Add custom scripts
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::assets'  );

			// Add help screen
			add_action( 'current_screen',   __CLASS__ . '::help'    );

		}

		self::$initialized = true;
	}

	/************************************************* WP Admin stuff *************************************************/

	/**
	 * Load text domain properly
	 */
	public static function textdomain(){
		$locale = ( is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale() );
		load_textdomain( LNG, PATH . '/languages/' . NAME . '-' . $locale . '.mo' );
		load_plugin_textdomain( LNG, false, NAME . '/languages' );
	}

	/**
	 * Add a help screen from readme.md
	 */
	public static function help(){
		foreach( [ 'README.md', 'readme.md', 'readme.MD', 'README.MD' ]  as $fn )
			if( file_exists( PATH . '/' . $fn ) && ( $help = @file_get_contents( PATH . '/' . $fn ) ) ) {
				$screen = get_current_screen();
				$parser = new Parsedown();
				$screen->add_help_tab( [
					'id'	    => '__wtwm_help_info',
					'title'	    => 'Woo Migration',
					'content'	=> $parser->text( $help )
				] );
				return;
			}
	}

	/**
	 * Define if we are under our settings page
	 *
	 * @return bool
	 */
	protected static function is_it_me(){
		return ( isset( $_GET['page'] )
		         && 'wc-settings' === $_GET['page']
		         && isset( $_GET['tab'] )
		         && self::slug === $_GET['tab'] );
	}

	/**
	 * Add scripts
	 */
	public static function assets(){
		wp_enqueue_script( 'wtwm-admin-script', URL . '/js/admin.js', [ 'jquery' ], time()  );
		wp_enqueue_style ( 'wtwm-admin-styles', URL . '/css/admin.css', null, time()        );
	}

	/**
	 * Show settings link in plugins page
	 *
	 * @param $l
	 * @return array
	 */
	public static function settings_link( $l ) {
		return array_merge( [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . self::slug ) . '">'
			. __( 'Settings', 'woocommerce' )
			. '</a>'
		], $l
		);
	}

	/**
	 * Settings tab init
	 *
	 * @param array $tabs
	 * @return array
	 */
	public static function add_tab( $tabs ){
		$tabs[ self::slug ] = 'Woo Migrate';
		return $tabs;
	}

	/**
	 * Settings tab content
	 */
	public static function render_tab(){
		include TPL . '/settings.php';
	}

	/**
	 * Get or set options
	 */
	private static $options = null;

	/**
	 * @param string $key
	 *
	 * @return array | string
	 */
	public static function options( $key = '' ){
		if( ! $key && isset( $_POST['wtwm-save-options'] ) )
			return update_option( self::slug, self::log( $_POST ) );
		if( null === self::$options )
			self::$options = get_option( self::slug );
		if( $key )
			return self::$options[ $key ] ?? null;
		return self::$options;
	}

	/**
	 * Debug info
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public static function log( $data ){
		if( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || empty( $data ) ) return $data;
		$mem = '[' . ceil( memory_get_peak_usage( true ) / 1024 / 1024 ) . 'Mb] ';
		$log = $mem . print_r( $data, 1 );
		if( is_callable( 'wc_get_logger' ) && $logger = wc_get_logger() )
			$logger->debug( $log, [ 'source' => 'wetail-woo-migrate' ] );
		else
			error_log( sprintf( "[%s] %s", 'WOO_MIGRATE', $log ) );
		return $data;
	}

}