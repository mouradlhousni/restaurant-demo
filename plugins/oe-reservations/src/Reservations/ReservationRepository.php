<?php
/**
 * Reservations data access.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Reservations;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table: no WordPress API exists for it, and reads must be fresh (availability).
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only the table name (not user input) is interpolated; values always go through prepare().

/**
 * All SQL of the plugin lives here. Every value goes through `$wpdb->prepare()`.
 */
final class ReservationRepository {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_NO_SHOW   = 'no_show';

	/** Statuses that hold seats. */
	public const ACTIVE_STATUSES = array( self::STATUS_PENDING, self::STATUS_CONFIRMED );

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'oe_reservations';
	}

	/**
	 * Status labels.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_PENDING   => __( 'Pending', 'oe-reservations' ),
			self::STATUS_CONFIRMED => __( 'Confirmed', 'oe-reservations' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'oe-reservations' ),
			self::STATUS_NO_SHOW   => __( 'No-show', 'oe-reservations' ),
		);
	}

	/**
	 * Seat-holding reservations of a day, as the availability engine expects them.
	 *
	 * @param string $date       Date (Y-m-d).
	 * @param bool   $for_update Lock the rows (inside a transaction).
	 * @return array<int, array{time: string, guests: int}>
	 */
	public static function active_for_date( string $date, bool $for_update = false ): array {
		global $wpdb;

		$table = self::table();
		$lock  = $for_update && self::supports_locking() ? ' FOR UPDATE' : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT reservation_time AS time, guests FROM {$table} WHERE reservation_date = %s AND status IN (%s, %s)" . $lock, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$date,
				self::STATUS_PENDING,
				self::STATUS_CONFIRMED
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $row ): array => array(
				'time'   => substr( (string) $row['time'], 0, 5 ),
				'guests' => (int) $row['guests'],
			),
			(array) $rows
		);
	}

	/**
	 * Inserts a reservation only if `$guard` still accepts it, atomically.
	 *
	 * Double-booking prevention: a MySQL named lock per date serializes concurrent bookings of
	 * the same day, and the day's rows are re-read with SELECT … FOR UPDATE inside a transaction.
	 * The guard (the availability engine) therefore decides on fresh data, and nothing can be
	 * inserted between its decision and the INSERT.
	 *
	 * @param array<string, mixed>                                                  $data  Validated reservation data (date, time, guests, name, email, phone, notes, status, source).
	 * @param callable(array<int, array{time: string, guests: int}>): (string|null) $guard Returns an error code, or null to accept.
	 * @return array{id: int, token: string}|\WP_Error
	 */
	public static function create( array $data, callable $guard ) {
		global $wpdb;

		$date    = (string) $data['date'];
		$locking = self::supports_locking();
		$lock    = 'oe_booking_' . $wpdb->prefix . $date;

		if ( $locking && '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, 10 ) ) ) {
			return new \WP_Error( 'oe_busy', __( 'We are processing many requests right now. Please try again in a moment.', 'oe-reservations' ), array( 'status' => 503 ) );
		}

		try {
			$wpdb->query( 'START TRANSACTION' );

			$error = $guard( self::active_for_date( $date, true ) );
			if ( null !== $error ) {
				$wpdb->query( 'ROLLBACK' );

				return new \WP_Error( 'oe_unavailable', $error, array( 'status' => 409 ) );
			}

			$token = bin2hex( random_bytes( 32 ) );
			$now   = current_time( 'mysql', true );

			$inserted = $wpdb->insert(
				self::table(),
				array(
					'reservation_date' => $date,
					'reservation_time' => $data['time'] . ':00',
					'guests'           => (int) $data['guests'],
					'name'             => (string) $data['name'],
					'email'            => (string) $data['email'],
					'phone'            => (string) ( $data['phone'] ?? '' ),
					'notes'            => (string) ( $data['notes'] ?? '' ),
					'status'           => (string) ( $data['status'] ?? self::STATUS_PENDING ),
					'token_hash'       => self::hash_token( $token ),
					'source'           => (string) ( $data['source'] ?? 'web' ),
					'created_at'       => $data['created_at'] ?? $now,
					'updated_at'       => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( ! $inserted ) {
				$wpdb->query( 'ROLLBACK' );

				return new \WP_Error( 'oe_db_error', __( 'The reservation could not be saved. Please try again.', 'oe-reservations' ), array( 'status' => 500 ) );
			}

			$id = (int) $wpdb->insert_id;
			$wpdb->query( 'COMMIT' );
		} finally {
			if ( $locking ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
		}

		return array(
			'id'    => $id,
			'token' => $token,
		);
	}

	/**
	 * A reservation.
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;

		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * A reservation from its cancellation link (constant-time token comparison).
	 *
	 * @param int    $id    ID.
	 * @param string $token Raw token from the link.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_token( int $id, string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		global $wpdb;

		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row || ! hash_equals( (string) $row['token_hash'], self::hash_token( $token ) ) ) {
			return null;
		}

		return self::hydrate( $row );
	}

	/**
	 * Changes the status of reservations.
	 *
	 * @param array<int, int> $ids    IDs.
	 * @param string          $status New status.
	 * @return int Rows updated.
	 */
	public static function update_status( array $ids, string $status ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids || ! isset( self::statuses()[ $status ] ) ) {
			return 0;
		}

		$table        = self::table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		return (int) $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One %d per ID, built above.
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $status, current_time( 'mysql', true ) ), $ids )
			)
		);
	}

	/**
	 * Issues a new cancellation token (the previous link stops working).
	 *
	 * @param int $id Reservation ID.
	 * @return string Raw token, to put in an email.
	 */
	public static function rotate_token( int $id ): string {
		global $wpdb;

		$token = bin2hex( random_bytes( 32 ) );
		$wpdb->update(
			self::table(),
			array( 'token_hash' => self::hash_token( $token ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $token;
	}

	/**
	 * Filtered, paginated list (admin screen, CSV export).
	 *
	 * @param array<string, mixed> $args from, to (Y-m-d), status, search, orderby, order, per_page (-1 = all), page.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'from'     => '',
				'to'       => '',
				'status'   => '',
				'search'   => '',
				'orderby'  => 'reservation_date',
				'order'    => 'ASC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['from'] ) {
			$where[]  = 'reservation_date >= %s';
			$params[] = $args['from'];
		}
		if ( $args['to'] ) {
			$where[]  = 'reservation_date <= %s';
			$params[] = $args['to'];
		}
		if ( $args['status'] && isset( self::statuses()[ $args['status'] ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// Whitelisted ORDER BY (identifiers cannot be placeholders).
		$columns = array(
			'reservation_date' => 'reservation_date %1$s, reservation_time %1$s',
			'guests'           => 'guests %1$s',
			'name'             => 'name %1$s',
			'status'           => 'status %1$s',
			'created_at'       => 'created_at %1$s',
		);
		$order   = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$orderby = sprintf( $columns[ $args['orderby'] ] ?? $columns['reservation_date'], $order ) . ', id ' . $order;

		$table     = self::table();
		$where_sql = implode( ' AND ', $where );
		$limit_sql = '';

		if ( (int) $args['per_page'] > 0 ) {
			$limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['per_page'], ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'] );
		}

		$sql   = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby}";
		$count = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( $params ) {
			$sql   = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count = $wpdb->prepare( $count, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql . $limit_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => array_map( array( self::class, 'hydrate' ), (array) $rows ),
			'total' => (int) $wpdb->get_var( $count ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Number of reservations per status, optionally for one day.
	 *
	 * @param string $date Date (Y-m-d) or empty for all.
	 * @return array<string, int>
	 */
	public static function count_by_status( string $date = '' ): array {
		global $wpdb;

		$table = self::table();
		$rows  = $date
			? $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n, SUM(guests) AS covers FROM {$table} WHERE reservation_date = %s GROUP BY status", $date ), ARRAY_A )
			: $wpdb->get_results( "SELECT status, COUNT(*) AS n, SUM(guests) AS covers FROM {$table} GROUP BY status", ARRAY_A );

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['n'];
		}

		return $counts;
	}

	/**
	 * Deletes every reservation (used by the seed command).
	 *
	 * @return void
	 */
	public static function truncate(): void {
		global $wpdb;

		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Typed row.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	public static function hydrate( array $row ): array {
		return array(
			'id'         => (int) $row['id'],
			'date'       => (string) $row['reservation_date'],
			'time'       => substr( (string) $row['reservation_time'], 0, 5 ),
			'guests'     => (int) $row['guests'],
			'name'       => (string) $row['name'],
			'email'      => (string) $row['email'],
			'phone'      => (string) $row['phone'],
			'notes'      => (string) $row['notes'],
			'status'     => (string) $row['status'],
			'source'     => (string) $row['source'],
			'created_at' => (string) $row['created_at'],
			'updated_at' => (string) $row['updated_at'],
		);
	}

	/**
	 * Hash stored for a cancellation token (the raw token only exists in the email).
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function hash_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Whether the database supports named locks and row locks (MySQL/MariaDB, not SQLite in Playground).
	 *
	 * @return bool
	 */
	private static function supports_locking(): bool {
		return ! ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE );
	}
}
