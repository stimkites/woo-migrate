<?php

namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Crontrol - Autosync cron job control
 *
 * @package Wetail\Sitoo
 */
final class Crontrol {

	/**
	 * Cron intervals and scripts to run
     * - every 15th minute in Live mode for main operations,
     * - every 3rd minute in Live mode for stock sync
	 * - every minute in test mode on all operations
	 *
	 * @var array
	 */
	private static $cron_jobs = [
	    'wp'   => [
            'live' => [
                'stock' => '3min',
                'main'  => '15min'
            ],
            'test' => [
                'stock' => '1min',
                'main'  => '1min'
            ]
        ],
        'sys'  => [
            'live' => [
                'stock' => '*/3 * * * *',
                'main'  => '*/15 * * * *'
            ],
            'test' => [
                'stock' => '* * * * *',
                'main'  => '* * * * *'
            ]
        ],
		'ext'  => [
			'live' => [
				'stock' => 3*60,
				'cron'  => 15*60
			],
			'test' => [
				'stock' => 60,
				'cron'  => 60
			]
		]
	];

	/**
	 * Add hook for WP Cron
	 */
	public static function init(){
	    if( 'wp' === self::cron_type() )
	        foreach( self::$cron_jobs[ 'wp' ][ self::mode() ] as $script=>$interval )
		        add_action( 'sitoo_cron_' . $script,  __CLASS__ . '::run_cron_' . $script );
		add_filter( 'cron_schedules',      __CLASS__ . '::wp_cron_intervals' );
		register_deactivation_hook( INDEX, __CLASS__ . '::disable_cron'      );
	}

	/**
	 * Add proper WP interval
	 *
	 * @param $schedules
	 *
	 * @return mixed
	 */
	public static function wp_cron_intervals( $schedules ) {
		if( ! isset( $schedules[ '15min' ] ) )
			$schedules[ '15mins' ] = array(
				'interval' => 900,
				'display' => __( 'Every 15 minutes', SLUG )
			);
        if( ! isset( $schedules[ '3min' ] ) )
            $schedules[ '3mins' ] = array(
                'interval' => 180,
                'display' => __( 'Every 3 minutes', SLUG )
            );
		if( ! isset( $schedules[ '1min' ] ) )
			$schedules[ '1min' ] = array(
				'interval' => 60,
				'display' => __( 'Every minute', SLUG )
			);
		return $schedules;
	}

	/**
	 * Run main cron job via wp-cron event
	 */
	public static function run_cron_main(){
		shell_exec( self::cron_command() );
	}

    /**
     * Run stock cron job via wp-cron event
     */
    public static function run_cron_stock(){
        shell_exec( self::cron_command( 'stock' ) );
    }

	/**
	 * Make a command to run in a shell
     *
     * @param string $script
	 *
	 * @return string
	 */
	protected static function cron_command( $script = 'main' ){
		return 'cd '
		       . PATH
		       . '/crons/ && php run-sitoo-croner-' . $script . '.php '
		       . '"'
		       . get_option( 'siteurl' )
		       . '"';
	}

    /**
     * Define cron job mode - test or live
     *
     * @return array|string
     */
	private static function mode(){
        if( ! ( $mode = Settings::options( 'mode' ) ) ) $mode = 'test';
        return $mode;
    }

	/**
	 * Make a complete task for crontab
	 *
	 * @return string
	 */
	protected static function make_task(){
	    if( ! ( $mode = Settings::options( 'mode' ) ) ) $mode = 'test';
	    $result = "";
	    foreach( self::$cron_jobs[ 'sys' ][ $mode ] as $script=>$interval )
		    $result .= $interval
			        . ' '
			        . self::cron_command( $script ) .
			        PHP_EOL;
	    return $result;
	}

    /**
     * Define the type of the cron job - system or wordpress one
     *
     * @return array|string
     */
	public static function cron_type(){
		$type = Settings::options( 'cron_type' );
		return ( $type ? $type : 'sys' );
	}

	/**
	 * Activate cron
	 *
	 * @return bool
	 */
	public static function enable_cron(){
		switch( self::cron_type() ){
			case 'sys':
				$output = shell_exec( 'crontab -l' ) . self::make_task();
				$fn = '/tmp/crontab_' . time();
				_self::log( 'SYS CRON JOB (enable):' . $output );
				if( ! file_put_contents( $fn, $output ) ) return false;
				exec( 'crontab ' . $fn );
				unlink( $fn );
			break;
			case 'wp':
			    foreach( self::$cron_jobs[ 'wp' ][ self::mode() ] as $script=>$interval )
				    if( ! wp_next_scheduled( 'sitoo_cron_' . $script ) )
                        wp_schedule_event(
                            time() + ( 60 * (int)str_replace( 'min', '', $interval ) ),
                            $interval,
                            'sitoo_cron_' . $script
                        );
			break;
			case 'ext':
				foreach ( self::$cron_jobs['ext'][self::mode() ] as $ep=>$time )
					Extcron::activate( $time, $ep );
			break;
		}
		return self::check_cron();
	}

	/**
	 * Deactivate cron
	 *
	 * @return bool
	 */
	public static function disable_cron(){
		switch( self::cron_type() ){
			case 'sys':
			    $crons = implode( PHP_EOL,
                    array_diff(
                        explode( PHP_EOL, shell_exec( 'crontab -l' ) ),
                        explode( PHP_EOL, self::make_task() )
                    )
                );
				$fn = '/tmp/crontab_' . time();
				_self::log( 'SYS CRON JOB (disable):' . $crons );
				if( ! file_put_contents( $fn, $crons . PHP_EOL ) ) return self::check_cron();
				exec( 'crontab ' . $fn );
				unlink( $fn );
			break;
			case 'wp':
			    foreach( self::$cron_jobs['wp'][self::mode()] as $script=>$interval )
				    wp_clear_scheduled_hook( 'sitoo_cron_' . $script );
			    //backward compatibility
                if( wp_get_schedule( 'woocommerce_sitoo_wp_cron' ) )
                    wp_clear_scheduled_hook( 'woocommerce_sitoo_wp_cron' );
            break;
			case 'ext':
				foreach ( self::$cron_jobs['ext'][self::mode() ] as $ep=>$time )
					Extcron::deactivate( $ep );
			break;
		}
		return self::check_cron();
	}

	/**
	 * Check cron
	 *
	 * @return bool
	 */
	public static function check_cron(){
		switch( self::cron_type() ){
			case 'sys':
				$output = shell_exec( 'crontab -l' );
				return ( false !== strpos( $output, self::make_task() ) );
			case 'wp':
			    foreach( self::$cron_jobs['wp'][self::mode()] as $script=>$interval )
				    if( wp_get_schedule( 'sitoo_cron_' . $script ) ) return true;
                if( wp_get_schedule( 'woocommerce_sitoo_wp_cron' ) ) return true; //backward compat
			break;
			case 'ext':
				return Extcron::check( 'cron' ) && Extcron::check( 'stock' );
		}
		return false;
	}

}