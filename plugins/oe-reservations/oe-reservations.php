<?php
/**
 * Plugin Name:       Olive & Ember Reservations
 * Plugin URI:        https://github.com/mouradlhousni/restaurant-demo
 * Description:       Restaurant menu and a from-scratch table reservation system: availability engine, REST API, transactional emails, admin screens, Gutenberg blocks and WP-CLI seeding.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Olive & Ember
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oe-reservations
 *
 * @package OliveEmber\Reservations
 */

defined( 'ABSPATH' ) || exit;

define( 'OE_RESERVATIONS_VERSION', '1.1.0' );
define( 'OE_RESERVATIONS_FILE', __FILE__ );
define( 'OE_RESERVATIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'OE_RESERVATIONS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Minimal PSR-4 autoloader for the OliveEmber\Reservations namespace.
 * Composer's autoloader is used for development (tests, tooling); the plugin itself
 * ships without a vendor folder so it can be installed straight from Git (Playground).
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'OliveEmber\\Reservations\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = OE_RESERVATIONS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

require_once OE_RESERVATIONS_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( \OliveEmber\Reservations\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \OliveEmber\Reservations\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \OliveEmber\Reservations\Plugin::class, 'instance' ) );
