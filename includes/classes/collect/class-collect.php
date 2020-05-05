<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:36 PM
 */

namespace Wetail\Woo\Migration;

defined( __NAMESPACE__ . '\LNG' ) or die();

/**
 * Class Collect
 *
 * @package Wetail\Woo\Migration
 */
final class Collect {

	/**
	 * Media to add into Zip upon syncing
	 *
	 * @var array
	 */
	public static $media = [];

	/**
	 * Write all settings on Woo to files
	 *
	 * @return bool
	 */
	protected static function write_settings(){

		global $wpdb;

		$tbs = [];

		/**
		 * WooCommerce tables to export as settings
		 */
		foreach( [
			'wc_tax_rate_classes',
			'wc_webhooks',
			'woocommerce_shipping_zones',
			'woocommerce_shipping_zone_locations',
			'woocommerce_shipping_zone_methods',
			'woocommerce_tax_rates',
			'woocommerce_tax_rate_locations',
			'woocommerce_attribute_taxonomies'
		] as $t )
			$tbs[] = "{$wpdb->prefix}$t";

		/**
		 * These options are included for WooCommrce
		 */
		$woo_opts = [
			'%woocommerce%', 'wc_%', '%shop%', '%product_cat%', '%vendo%', '%rtnd%', '%wfx_fix%', '%fortnox%', '%pacsoft%',
			'%aelia%', '%klarna%',
		];

		// Gateways and gateway settings
		$gateways = get_option( 'woocommerce_gateway_order' );
		if( ! empty( $gateways ) )
			$woo_opts = array_merge( $woo_opts, array_map( function( $a ) { return "%$a%"; }, array_keys( $gateways ) ) );

		$options_condition = "option_name LIKE '" . implode( "' OR option_name LIKE '",  $woo_opts ) . "' ";
		$options = $wpdb->get_results(
			"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE " . $options_condition
		);

		// Write options and dump settings tables
		exec(
			"mysqldump -u " . DB_USER . " -p" . DB_PASSWORD . " " . DB_NAME . " " . implode( " ", $tbs )
			. " --single-transaction > " . Writer::fn( 'woo_settings' ), $dummy, $fail
		);

		return  ! $fail && Writer::put( 'woo_options', $options );

	}

	/**
	 * Get all ids for post attachment
	 *
	 * @param $meta_value
	 *
	 * @return array|mixed|object
	 */
	protected static function get_attachment_ids( $meta_value ){
		if( $meta_value[0] === 'a' )
			return unserialize( $meta_value );
		if( $meta_value[0] === '[' )
			return json_decode( $meta_value, true );
		if( false !== strpos( $meta_value, "," ) )
			return explode( ",", str_replace( '"', '', $meta_value ) );
		return [ (int)$meta_value ];
	}

	/**
	 * Collect all data on orders
	 *
	 * @YES: including products/images/customers/categories
	 *
	 * @param $row
	 *
	 * @return array
	 */
	private static function collect_orders_data( $row ){
		global $wpdb;
		$order_meta = get_post_meta( $row->ID );
		$items = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = {$row->ID}"
		);
		$order_items = [];
		foreach( $items as $item ) {
			$item_meta = [];
			foreach( $wpdb->get_results(
				"SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta " .
				"WHERE order_item_id = {$item->order_item_id}"
			) as $_meta )
				$item_meta[ $_meta->meta_key ][] = $_meta->meta_value;
			$ri = [
				'item' => $item,
				'meta' => $item_meta
			];
			if( $item->order_item_type === 'coupon' &&
			    'yes' === ( options('orders')['coupons'] ?? '' ) ) {
				if( $_row = $wpdb->get_row(
					"SELECT * FROM {$wpdb->posts} WHERE `post_type` = 'shop_coupon' ".
					"AND `post_name` = '{$item->order_item_name}' LIMIT 1"
				) )
					$ri['coupon'] = self::collect( $_row, 'coupons' );
			}
			if( $item->order_item_type === 'line_item' &&
			    'yes' === ( options('orders')['products'] ?? '' ) ) {
				if( $_row = get_post( $item_meta['_product_id'][0] ) )
					$ri['product'] = self::collect( $_row, 'products' );
			}
			$order_items[] = $ri;
		}
		$result =  [
			'order'     => $row,
			'meta'      => $order_meta,
			'items'     => $order_items,
			'comments'  => $wpdb->get_results(
				"SELECT * FROM {$wpdb->comments} WHERE `comment_post_ID` = {$row->ID}"
			)
		];

		if( 'yes' === ( options('orders')['customers'] ?? '' )
		    && ( $customer_id = $order_meta['_customer_user'][0] ) )
			$result['customer'] = self::collect( new \WP_User( $customer_id ), 'customers' );

		return $result;
	}

	/**
	 * Collect all data on coupons
	 *
	 * @param $row
	 *
	 * @return array
	 */
	private static function collect_coupons_data( $row ){
		return [
			'coupon'    => $row,
			'meta'      => get_post_meta( $row->ID )
		];
	}

	/**
	 * Collect data on attachments and store for further adding into Zip
	 *
	 * @param $row
	 *
	 * @return array
	 */
	private static function collect_attachments_data( $row ){
		$meta = get_post_meta( $row->ID );
		if( empty( $meta ) || ! isset( $meta['_wp_attached_file'] ) )
			$file = explode( "uploads/", $row->guid )[1] ?? null;
		else
			$file = $meta['_wp_attached_file'][0];
		if( $file )
			self::$media[] = $file;
		return [
			'attachment'    => $row,
			'meta'          => $meta
		];
	}

	/**
	 * Collect products data
	 *
	 * @param $row
	 *
	 * @return array
	 */
	private static function collect_products_data( $row ){
		global $wpdb;
		$product_meta = get_post_meta( $row->ID );
		$attachments = [];
		foreach( $product_meta as $m=>$values )
			if( '_thumbnail_id' === $m || false !== strpos( $m, '_gallery' ) )
				foreach( $values as $mvalue )
					foreach( self::get_attachment_ids( $mvalue ) as $id )
						$attachments[] = self::collect( get_post( $id ), 'attachments' );
		$children = [];
		if( $crows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->posts} WHERE `post_type` = 'product_variation' " .
			"AND `post_parent` = {$row->ID}" ) ) {
			foreach ( $crows as $crow ) {
				$children[] = self::collect( $crow, 'products' );
			}
		}
		$terms = [];
		if( $rel_terms = $wpdb->get_results(
			"SELECT * FROM {$wpdb->term_relationships} WHERE `object_id` = {$row->ID}"
		) ) {
			foreach ( $rel_terms as $rel_term ) {
				$terms[] = self::collect( $rel_term->term_taxonomy_id, 'categories' );
			}
		}
		return [
			'product'       => $row,
			'meta'          => $product_meta,
			'attachments'   => $attachments,
			'children'      => $children,
			'terms'         => $terms
		];
	}

	/**
	 * Collect customers data
	 *
	 * @param $row
	 *
	 * @return array
	 */
	private static function collect_customers_data( $row ){
		return [
			'customer'  => $row,
			'meta'      => get_user_meta( $row->ID )
		];
	}

	private static function collect_terms_data( $row ){
		$term = get_term( $row );
		return [
			'term'      => $term,
			'meta'      => get_term_meta( $term->term_id )
		];
	}

	/**
	 * Collect all data
	 *
	 * @param $row
	 * @param null $data_type
	 *
	 * @return array
	 */
	protected static function collect( $row, $data_type = null ){
		if( empty( $row ) ) return [];
		if( null === $data_type )
			$data_type = options( 'data-type' );
		log( 'Collecting for ' . ( $row->ID ?? $row ) . ' [' . $data_type . ']...' );
		switch( $data_type ){
			case 'orders'       : return self::collect_orders_data( $row );
			case 'coupons'      : return self::collect_coupons_data( $row );
			case 'attachments'  : return self::collect_attachments_data( $row );
			case 'products'     : return self::collect_products_data( $row );
			case 'customers'    : return self::collect_customers_data( $row );
			case 'categories'   : return self::collect_terms_data( $row );
		}
		return null;
	}

	/**
	 * Create range condition
	 *
	 * @return string
	 */
	protected static function range(){
		$dt = options( 'data-type' );
		if( ! $range = @options( $dt )['range'] )
			return '';
		$id = $dt === 'categories' ? 'term_id' : 'ID';
		if( false !== strpos( $range, '-' ) ){
			$parts = explode( "-", $range );
			$min = $parts[0];
			$max = ( isset( $parts[1] ) ? $parts[1] : null );
			return " AND $id > $min " . ( $max ? " AND $id < $max " : "" );
		}
		return " AND $id = $range ";
	}

	/**
	 * Get rows for syncing
	 *
	 * @return array
	 */
	protected static function get_rows(){
		global $wpdb;
		switch( options( 'data-type' ) ) {

			case 'orders':
				return $wpdb->get_results(
					log( "SELECT * FROM {$wpdb->posts} WHERE `post_type` = 'shop_order' " . self::range() )
				);

			case 'products':
				return $wpdb->get_results(
					log( "SELECT * FROM {$wpdb->posts} WHERE `post_type` = 'product' " . self::range() )
				);

			case 'coupons':
				return $wpdb->get_results(
					log( "SELECT * FROM {$wpdb->posts} WHERE `post_type` = 'shop_coupon'" . self::range() )
				);

			case 'customers':
				return $wpdb->get_results(
					log( "SELECT * FROM {$wpdb->users} WHERE 1 = 1 " . self::range() )
				);

			case 'categories':
				return $wpdb->get_results(
					log( "SELECT * FROM {$wpdb->term_taxonomy} WHERE `taxonomy` = 'product_cat'" . self::range() )
				);
		}

		return [];
	}


	/**
	 * Launch data transfer
	 *
	 * @return array | bool
	 */
	public static function launch(){

		status( 'Launching migration...', 5 );

		// Prepare data
		$rows = self::get_rows();
		$ttl = count( $rows );
		if( empty( $rows ) )
			return status( 'No data was found for transferring. Check IDs range.', 5, true );
		else
			log( 'Found ' . $ttl . ' rows for export' );

		return self::queue( $rows );
	}

	/**
	 * Create queue for collecting data, trigger cron and respond to Ajax
	 *
	 * @param $rows
	 *
	 * @return array | \WP_REST_Response
	 */
	protected static function queue( $rows ){



		// Write plugin version (security reasons)
		if( ! Writer::put( 'version', [ 'version' => VERSION ] ) )
			return Writer::fail( status( 'Impossible to write into output file plugin version!', 5, true ) );

		// Write settings
		if( 'yes' === options( 'pre-settings') )
			if( self::write_settings() )
				status( 'Settings and version are written. Collecting data...', 8 );
			else
				return Writer::fail( status( 'Settings could not be written properly into output files.', 8, true ) );

		Api::alive();

		status( 'Collecting...', 10 );

		// Write data
		foreach( $rows as $i=>$row )
			if( Writer::put( $row->ID, self::collect( $row ) ) ) {
				Api::alive();
				status( 'Collecting ' . ( $i + 1 ) . ' of ' . $ttl . '...', 10 + 89 / $ttl * ( $i + 1 ) );
			} else
				return
					status(
						Writer::fail(
							'Could not write data into result file. Check free space!'
						),
						10 + 90 / $ttl * ( $i + 1 ),
						true
					);

		// Add media
		if( ! empty( self::$media ) && ( 'yes' === ( options( options( 'data-type' ) )['media'] ?? '' ) ) )
			Writer::put( 'media', self::$media );

		Api::alive();

		status( 'Creating Zip...', 1 );

		// Make Zip
		if( ! $fz = Writer::zip() )
			return Writer::fail( status( 'Could not create Zip. Check free space!', 99, true ) );

		// Calc hash
		$hash = md5_file( $fz );

		$zsize = filesize( $fz );

		$verify = [
			'url'   => trailingslashit( wp_get_upload_dir()['url'] ) . basename( $fz ),
			'zip'   => $fz,
			'hash'  => $hash,
			'zsize' => $zsize
		];

		status( 'Data collected!', 100, false,  $verify );

		return [ 'result' => 'Ok' ];
	}

}