<?php
/**
 * Created by PhpStorm.
 * User: stim
 * Date: 4/14/20
 * Time: 12:33 PM
 */

namespace Wetail\Woo\Migration;

/**
 * Get option value or set all options
 *
 * @param string $key
 *
 * @return mixed
 */
function options( $key = null ){

	return Migrate::options( $key );

}