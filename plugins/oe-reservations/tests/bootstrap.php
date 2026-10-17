<?php
/**
 * PHPUnit bootstrap.
 *
 * The availability engine is plain PHP (no WordPress calls), so its unit tests
 * run without a WordPress test install: only the Composer autoloader is needed.
 *
 * @package OliveEmber\Reservations
 */

// Plugin files bail out when loaded outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
