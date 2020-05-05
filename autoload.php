<?php

namespace Wetail\Woo\Migration;

/**
 * PSR-4 compliant autoloader
 *
 * @param $path
 */

/**
 * @Stim edition
 *
 * Version 0.0.3
 *
 *  - Static
 *  - Includes Functions automatic loader
 *
 * Allows to load all necessary classes in non-"namespace" dependent way, does not check any class names not under current
 *
 * Usage args:
 *  - dir       : initial directory for lookup, by default set to current __DIR__ value
 *  - path      : relative path to folder containing sub-folders with all classes to load (e.g. 'includes')
 *  - classes   : relative path for all classes to load (e.g. directory 'classes')
 *  - functions : relative path for all functions to load (e.g. directory 'functions')
 *  - prefix    : class name prefix to lookup for (e.g. 'class') to avoid loading auxiliary files
 *  - separator : symbol separating class prefix from class name, default '-'
 *
 * All in all, leave args untouched,
 * if your parental (includes/classes or includes/functions)
 * hierarchy is built as follows:
 *
 * + plugin
 *   + includes
 *     + classes
 *       class-one.php
 *       class-two.php
 *     + functions
 *       some-functions.php
 * etc.
 */

if ( class_exists( __NAMESPACE__ . '\__Autoloader' ) ) return __Autoloader::init();

final class __Autoloader {

    /**
     * List of classes to load from
     * 
     * @var array
     */
    protected static $list = [];

    /**
     * Dynamically assigned args from calling side
     *
     * @var array
     */
    private static $args = [];

    /**
     * Default paths and prefix
     */
    const defaults = [
        'prefix'    => 'class',
        'dir'       => __DIR__,
        'path'      => 'includes',
        'functions' => 'functions',
        'classes'   => 'classes',
        'separator' => '-'
    ];

    /**
     * __Autoloader initialization
     * 
     * @param array $args
     * 
     * @return bool
     */
    public static function init( $args = [] ){

        self::$args = array_merge( self::defaults, $args );

        $mask =  rtrim( self::$args['dir'], '/' ) . '/'
                . trim( self::$args['path'], '/' ) . '/'
                . trim( self::$args['classes'], '/' ) . '/'
                . trim( self::$args['prefix'], self::$args['separator'] )
                . self::$args['separator']
                . '*.php';

        self::$list = self::index( self::scan( $mask ) );

        if ( ! function_exists( 'get_plugins' ) )
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';

        spl_autoload_register( __CLASS__ . '::load' );

        self::load_functions();
        
        return true;

    }

    /**
     * Recursively scan for all files with the given mask
     *
     * @param $mask
     * @param int $flags
     * @return array
     */
    public static function scan( $mask, $flags = 0 ){
        $files = glob( $mask, $flags );
        foreach ( glob( dirname( $mask ) . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT ) as $dir ) {
            $files = array_merge( $files, self::scan( $dir . '/' . basename( $mask ), $flags ) );
        }
        return ( $files ? $files : [] );
    }

    /**
     * Make indexed list of class files
     *
     * @param array $files
     *
     * @return array
     */
    private static function index( $files ){
        $r = [];
        $prefix = trim( self::$args['prefix'], self::$args['separator'] ) . self::$args['separator'];
        foreach( $files as $file ){
            $parts = explode( '/', $file );
            $last_part = end( $parts );
            $index = substr( $last_part, strpos( $last_part, $prefix ) + strlen( $prefix ) );
            $index = substr( $index, 0, strlen( $index ) - 4 );
            $r[ $index ] = $file;
        }
        return $r;
    }

    /**
     * Find the file to load
     *
     * @param $class_name
     * @return mixed
     * @throws \Exception
     */
    private static function find_file( $class_name ){

        //This will prevent looping into our files not from our namespace
        if( __NAMESPACE__ !== substr( $class_name, 0, strlen( __NAMESPACE__ ) ) ) return '';

        $parts = explode( '\\', $class_name );
        $index = strtolower( str_replace( '_', self::$args['separator'],  ltrim( end( $parts ), '_' ) ) );
        if( empty( self::$list[ $index ] ) || !file_exists( self::$list[ $index ] ) )
            throw new \Exception( 'File for class <b>"' . $class_name
                . '"</b> with index <i>"' . $index
                . '"</i> not found!', 0 );

        return self::$list[ $index ];
    }

    /**
     * Load corresponding class
     *
     * @param $class
     */
    public static function load( $class ){
        try {
            $file = self::find_file( $class );
        }catch( \Exception $e ){
            die( __NAMESPACE__ . ' AUTOLOADER EXCEPTION:<br/>' . $e->getMessage() );
        }finally{
            if ( empty( $file ) ) return;
            require_once $file;
        }
    }

    /**
     * Autoload for all functions
     */
    private static function load_functions(){
        $fun_mask =  rtrim( self::$args['dir'], '/' ) . '/'
                    . trim( self::$args['path'], '/' ) . '/'
                    . trim( self::$args['functions'], '/' ) . '/'
                    . '*.php';
        foreach( self::scan( $fun_mask ) as $file )
            if( file_exists( $file ) )
                require_once $file;
    }

}