<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 1:07 PM
 */
namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Writer
 *
 * @package Wetail\Woo\Migration
 */
final class Writer {

	/**
	 * Whenever include media into Zip or not
	 *
	 * @var bool '
	 */
	protected static $include_media = false;

	/**
	 * Check if file is a valid zip one
	 *
	 * @param $file
	 * @return bool
	 */
	public static function check_zip( $file ){
		$fh = @fopen( $file, "r" );
		if ( ! $fh ) return false;
		$blob = @fgets( $fh, 5 );
		fclose( $fh );
		return false !== strpos( $blob, 'PK' );
	}

	/**
	 * Migration filename
	 */
	private static $dir = null;
	/**
	 *
	 * @param $suffix
	 *
	 * @return string | bool
	 */
	public static function fn( $suffix = 'migrate' ){
		if( null === self::$dir ) {
			$dir = wp_get_upload_dir()['path'] . '/woomigrate/';
			if( is_dir( $dir ) )
				self::$dir =  $dir;
			elseif ( ! mkdir( $dir ) )
				return false;
		}
		return ( $suffix ? self::$dir . $suffix . '.data' : self::$dir );
	}

	/**
	 * Force remove our temp directory and return the data
	 *
	 * @param null $return_data
	 *
	 * @return null
	 */
	public static function fail( $return_data = null ){
		$dir = wp_get_upload_dir()['path'] . '/woomigrate/';
		if( $files = glob( $dir . '*' ) )
			foreach( $files as $file )
				unlink( $file );
		rmdir( $dir );
		return $return_data;
	}

	/**
	 * Zip all files in a path
	 *
	 * @Note: this is not a recursive path scanning - only files from the specified folder
	 *
	 * @param $path
	 *
	 * @return bool|string
	 */
	public static function zip( $path = null ){
		if( empty( $path ) )
			$path = self::fn( "" );
		$files = glob( $path . '*' );
		if( empty( $files ) ) {
			log( 'No files to add to archive!' );
			return false;
		}
		$ttl = count( $files );
		if( self::$include_media )
			$ttl += count( Collect::$media );
		status( 'Adding ' . $ttl . ' files to Zip...', 5 );
		$zip = new \ZipArchive();
		$fz = log( rtrim( $path, '/' ) . '.zip' );
		if( $zip->open( $fz, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) === false ) {
			log( 'Zip archive could not be created/initialized!' );
			return false;
		}
		$fail = false;

		// Include regular files
		foreach( $files as $i=>$fn ) {
			Api::alive();

			if ( ! $zip->addFile( $fn, basename( $fn ) ) ) {
				$fail = log( 'File was not added: ' . $fn ) && true;
			}

			status( 'Adding to Zip ' . ( $i + 1 ) . ' of ' . $ttl . '...', 10 + 89 / $ttl * ( $i + 1 ) );

		}

		// Include media
		$upload_path = trailingslashit( wp_get_upload_dir()['basedir'] );
		foreach( Collect::$media as $i=>$fn ) {

			Api::alive();

			if ( ! $zip->addFile( $upload_path . $fn, $fn ) ) {
				$fail = log( 'Media file was not added: ' . $fn ) && true;
			}
			status( 'Adding to Zip ' . ( $i + 1 ) . ' of ' . $ttl . '...', 10 + 89 / $ttl * ( $i + 1 ) );
		}


		$zip->close();
		if( ! $fail ) {
			self::fail(); // Cleanup
			status( 'Zip created successfully!', 99 );
		}else
			status( 'Errors appeared during zipping!', 99, true );
		return $fz;
	}

	/**
	 * Unzip a file
	 *
	 * @param string $zipfile
	 *
	 * @return bool|mixed
	 */
	public static function unzip( $zipfile = '' ){
		if( empty( $zipfile ) )
			$zipfile = rtrim( self::fn( "" ), '/' ) . '.zip';
		$zip = new \ZipArchive;
		if( ! $zip->open( $zipfile ) ) {
			log( 'Could not open zip archive "' . $zipfile . '"!' );
			return false;
		}
		$exdir = str_replace( '.zip', '', $zipfile );
		if( ! is_dir( $exdir ) )
			if( ! mkdir( $exdir ) ) {
				log( 'Could not create directory "' . $exdir . '" for extracting!' );
				return false;
			}
		if( ! $zip->extractTo( $exdir ) ) {
			log( 'Could not extract into specific directory "' . $exdir . '"!' );
			return false;
		}
		$zip->close();
		unlink( $zipfile );
		return $exdir;
	}

	/**
	 * Write data file
	 *
	 * @param $part
	 * @param $data
	 *
	 * @return bool|int
	 */
	public static function put( $part, $data ){
		if( ! $fn = self::fn( $part ) ) return false;
		if( $part === 'media' && ! empty( $data ) ) self::$include_media = true;
		if( ! is_string( $data ) )
			$data = json_encode( $data );
		return @file_put_contents( $fn, $data );
	}

	/**
	 * Write raw file
	 *
	 * @param $data
	 *
	 * @return bool|int
	 */
	public static function raw( $data ){
		$zipfile = rtrim( self::fn( "" ), '/' ) . '.zip';
		@file_put_contents( $zipfile, $data, FILE_APPEND );
		return filesize( $zipfile );
	}
}