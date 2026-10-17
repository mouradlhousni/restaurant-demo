<?php
/**
 * Booking use case.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Reservations;

use OliveEmber\Reservations\Availability\AvailabilityService;
use OliveEmber\Reservations\Email\Mailer;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Validates a booking request, stores it atomically and sends the emails.
 *
 * Transport-agnostic: the REST controller only maps HTTP to this class.
 */
final class BookingService {

	/**
	 * Field limits.
	 */
	public const NAME_MAX  = 100;
	public const NOTES_MAX = 500;

	/**
	 * Human message for an availability reason code.
	 *
	 * @param string $code DAY_* or SLOT_* code.
	 * @return string
	 */
	public static function reason_message( string $code ): string {
		$rules = Settings::rules();

		$messages = array(
			AvailabilityService::DAY_CLOSED        => __( 'We are closed on this day. Please choose another date.', 'oe-reservations' ),
			AvailabilityService::DAY_EXCEPTIONAL   => __( 'We are exceptionally closed on this day. Please choose another date.', 'oe-reservations' ),
			AvailabilityService::DAY_PAST          => __( 'This date is in the past. Please choose another date.', 'oe-reservations' ),
			/* translators: %d: number of days. */
			AvailabilityService::DAY_BEYOND_WINDOW => sprintf( _n( 'Bookings open %d day ahead. Please choose an earlier date.', 'Bookings open %d days ahead. Please choose an earlier date.', $rules->max_advance_days, 'oe-reservations' ), $rules->max_advance_days ),
			/* translators: %d: largest party size. */
			AvailabilityService::DAY_INVALID_PARTY => sprintf( __( 'Online bookings are for 1 to %d guests. For larger parties, please call us.', 'oe-reservations' ), $rules->max_party ),
			AvailabilityService::SLOT_FULL         => __( 'Sorry, this time has just been fully booked. Please choose another time.', 'oe-reservations' ),
			AvailabilityService::SLOT_TOO_SOON     => __( 'This time is too close to book online. Please choose a later time or call us.', 'oe-reservations' ),
			AvailabilityService::SLOT_NOT_OFFERED  => __( 'This time is not available for booking. Please choose a time from the list.', 'oe-reservations' ),
		);

		return $messages[ $code ] ?? __( 'This time is not available. Please choose another one.', 'oe-reservations' );
	}

	/**
	 * Validates and normalizes raw input.
	 *
	 * @param array<string, mixed> $input Raw input (date, time, guests, name, email, phone, notes).
	 * @return array<string, mixed>|\WP_Error Clean data, or field errors in `data.fields`.
	 */
	public static function validate( array $input ) {
		$errors = array();

		$date = sanitize_text_field( (string) ( $input['date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! \OliveEmber\Reservations\Availability\BookingRules::is_valid_date( $date ) ) {
			$errors['date'] = __( 'Please choose a valid date.', 'oe-reservations' );
		}

		$time = sanitize_text_field( (string) ( $input['time'] ?? '' ) );
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$errors['time'] = __( 'Please choose a time.', 'oe-reservations' );
		}

		$guests = filter_var( $input['guests'] ?? null, FILTER_VALIDATE_INT );
		if ( false === $guests || $guests < 1 || $guests > Settings::rules()->max_party ) {
			$errors['guests'] = self::reason_message( AvailabilityService::DAY_INVALID_PARTY );
		}

		$name = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );
		if ( mb_strlen( $name ) < 2 ) {
			$errors['name'] = __( 'Please enter your name.', 'oe-reservations' );
		} elseif ( mb_strlen( $name ) > self::NAME_MAX ) {
			/* translators: %d: maximum length. */
			$errors['name'] = sprintf( __( 'Your name must be %d characters or fewer.', 'oe-reservations' ), self::NAME_MAX );
		}

		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
		if ( ! $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address, e.g. name@example.com.', 'oe-reservations' );
		}

		$phone = trim( sanitize_text_field( (string) ( $input['phone'] ?? '' ) ) );
		if ( ! preg_match( '/^\+?[0-9 ().\-]{6,25}$/', $phone ) || preg_match_all( '/\d/', $phone ) < 6 ) {
			$errors['phone'] = __( 'Please enter a phone number we can reach you on, e.g. +33 6 12 34 56 78.', 'oe-reservations' );
		}

		$notes = trim( sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ) );
		if ( mb_strlen( $notes ) > self::NOTES_MAX ) {
			/* translators: %d: maximum length. */
			$errors['notes'] = sprintf( __( 'Notes must be %d characters or fewer.', 'oe-reservations' ), self::NOTES_MAX );
		}

		if ( $errors ) {
			return new \WP_Error(
				'oe_invalid_fields',
				__( 'Please check the highlighted fields.', 'oe-reservations' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		return array(
			'date'   => $date,
			'time'   => $time,
			'guests' => (int) $guests,
			'name'   => $name,
			'email'  => $email,
			'phone'  => $phone,
			'notes'  => $notes,
		);
	}

	/**
	 * Books a table.
	 *
	 * In demo mode, everything up to the final availability check runs for real, but nothing
	 * is inserted and no email is sent: a fictitious confirmation is returned instead.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function book( array $input ) {
		$data = self::validate( $input );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$availability = Settings::availability();
		$now          = new \DateTimeImmutable( 'now', wp_timezone() );
		$status       = Settings::get( 'auto_confirm' ) ? ReservationRepository::STATUS_CONFIRMED : ReservationRepository::STATUS_PENDING;
		$guard        = static fn( array $existing ): ?string => $availability->check( $data['date'], $data['time'], $data['guests'], $existing, $now );

		if ( oe_is_demo_mode() ) {
			$error = $guard( ReservationRepository::active_for_date( $data['date'] ) );
			if ( null !== $error ) {
				return self::unavailable( $error );
			}

			return self::response( $data + array( 'status' => $status ), 0, true );
		}

		$created = ReservationRepository::create( $data + array( 'status' => $status ), $guard );
		if ( is_wp_error( $created ) ) {
			return 'oe_unavailable' === $created->get_error_code() ? self::unavailable( (string) $created->get_error_message() ) : $created;
		}

		$reservation = ReservationRepository::find( $created['id'] );
		if ( $reservation ) {
			Mailer::customer( $reservation, ReservationRepository::STATUS_CONFIRMED === $status ? 'confirmed' : 'received', $created['token'] );
			Mailer::restaurant( $reservation, 'new' );

			/**
			 * Fires after a reservation has been booked online.
			 *
			 * @param array<string, mixed> $reservation Reservation.
			 */
			do_action( 'oe_reservation_created', $reservation );
		}

		return self::response( $data + array( 'status' => $status ), $created['id'], false );
	}

	/**
	 * Error for an unavailable slot.
	 *
	 * @param string $code Reason code.
	 * @return \WP_Error
	 */
	private static function unavailable( string $code ): \WP_Error {
		return new \WP_Error(
			'oe_unavailable',
			self::reason_message( $code ),
			array(
				'status' => 409,
				'reason' => $code,
			)
		);
	}

	/**
	 * Public summary of a booking.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   Reservation ID (0 in demo mode).
	 * @param bool                 $demo Demo mode.
	 * @return array<string, mixed>
	 */
	private static function response( array $data, int $id, bool $demo ): array {
		$confirmed = ReservationRepository::STATUS_CONFIRMED === $data['status'];

		if ( $demo ) {
			$message = __( 'Demo mode: the form works, but no data is stored or sent.', 'oe-reservations' );
		} elseif ( $confirmed ) {
			/* translators: %s: email address. */
			$message = sprintf( __( 'Your table is booked. A confirmation has been sent to %s.', 'oe-reservations' ), $data['email'] );
		} else {
			/* translators: %s: email address. */
			$message = sprintf( __( 'Thank you! Your request has been received; we will confirm it by email at %s shortly.', 'oe-reservations' ), $data['email'] );
		}

		return array(
			'demo'        => $demo,
			'message'     => $message,
			'reservation' => array(
				'reference' => $demo ? 'DEMO-' . strtoupper( substr( md5( $data['email'] . $data['date'] . $data['time'] ), 0, 6 ) ) : self::reference( $id ),
				'status'    => $data['status'],
				'date'      => $data['date'],
				'time'      => $data['time'],
				'datetime'  => self::format_datetime( $data['date'], $data['time'] ),
				'guests'    => $data['guests'],
				'name'      => $data['name'],
				'email'     => $data['email'],
			),
		);
	}

	/**
	 * Short public reference of a reservation.
	 *
	 * @param int $id ID.
	 * @return string
	 */
	public static function reference( int $id ): string {
		return 'OE-' . str_pad( (string) $id, 5, '0', STR_PAD_LEFT );
	}

	/**
	 * "Friday 9 October 2026 at 19:30" in the site locale.
	 *
	 * @param string $date Date (Y-m-d).
	 * @param string $time Time (HH:MM).
	 * @return string
	 */
	public static function format_datetime( string $date, string $time ): string {
		$moment = new \DateTimeImmutable( $date . ' ' . $time, wp_timezone() );

		/* translators: 1: date, 2: time. */
		return sprintf( __( '%1$s at %2$s', 'oe-reservations' ), wp_date( 'l j F Y', $moment->getTimestamp() ), wp_date( get_option( 'time_format', 'H:i' ), $moment->getTimestamp() ) );
	}
}
