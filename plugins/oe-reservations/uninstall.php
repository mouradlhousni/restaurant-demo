<?php
/**
 * Uninstall: removes every piece of data the plugin created.
 *
 * @package OliveEmber\Reservations
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Custom table (table names cannot be passed as placeholders).
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oe_reservations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

foreach ( array( 'oe_settings', 'oe_db_version', 'oe_reservation_page' ) as $oe_option ) {
	delete_option( $oe_option );
}
