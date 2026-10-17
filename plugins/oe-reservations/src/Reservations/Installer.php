<?php
/**
 * Database schema.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the `{prefix}oe_reservations` table with dbDelta.
 *
 * The schema version is stored in `oe_db_version`; bumping SCHEMA_VERSION runs dbDelta
 * again on the next request, so updates never require a reactivation.
 */
final class Installer {

	public const SCHEMA_VERSION = '1.0.0';
	public const VERSION_OPTION = 'oe_db_version';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 1 );
	}

	/**
	 * Runs the migration when the stored schema version is outdated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::SCHEMA_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Creates or updates the table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = ReservationRepository::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta format: two spaces after PRIMARY KEY, one field per line.
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reservation_date date NOT NULL,
			reservation_time time NOT NULL,
			guests smallint(5) unsigned NOT NULL,
			name varchar(100) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(40) NOT NULL DEFAULT '',
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			token_hash char(64) NOT NULL,
			source varchar(20) NOT NULL DEFAULT 'web',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY date_status (reservation_date,status),
			KEY email (email)
			) {$charset};"
		);

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}
}
