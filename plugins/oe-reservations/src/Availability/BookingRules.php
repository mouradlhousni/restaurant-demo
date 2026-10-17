<?php
/**
 * Booking rules value object.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Availability;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable, validated set of rules the availability engine works with.
 *
 * Plain PHP on purpose (no WordPress calls) so it can be unit-tested in isolation.
 */
final class BookingRules {

	/** Weekday keys, ISO order (1 = Monday). */
	public const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * Opening periods per weekday: day key => list of [open minutes, close minutes].
	 *
	 * @var array<string, array<int, array{0: int, 1: int}>>
	 */
	public readonly array $hours;

	/**
	 * Exceptional closure dates (Y-m-d) as a set.
	 *
	 * @var array<string, true>
	 */
	public readonly array $closures;

	/**
	 * Constructor.
	 *
	 * @param array<string, string|array<int, array{0: int, 1: int}>> $hours            Day key => periods ("12:00-14:30, 19:00-22:30" or parsed list).
	 * @param int                                                     $slot_interval    Minutes between two slot starts.
	 * @param int                                                     $service_duration Minutes a table is occupied.
	 * @param int                                                     $capacity         Covers that can be seated at the same time.
	 * @param int                                                     $max_party        Largest party bookable online.
	 * @param array<int, string>                                      $closures         Exceptional closure dates (Y-m-d).
	 * @param int                                                     $min_notice       Minimum minutes between now and the reservation.
	 * @param int                                                     $max_advance_days How many days ahead bookings are open.
	 * @throws \InvalidArgumentException On invalid values.
	 */
	public function __construct(
		array $hours,
		public readonly int $slot_interval = 30,
		public readonly int $service_duration = 90,
		public readonly int $capacity = 40,
		public readonly int $max_party = 8,
		array $closures = array(),
		public readonly int $min_notice = 120,
		public readonly int $max_advance_days = 60
	) {
		foreach ( array( 'slot_interval', 'service_duration', 'capacity', 'max_party' ) as $field ) {
			if ( $this->$field < 1 ) {
				throw new \InvalidArgumentException( "$field must be a positive integer." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain PHP class; escaped where displayed.
			}
		}
		if ( $this->min_notice < 0 || $this->max_advance_days < 0 ) {
			throw new \InvalidArgumentException( 'Notice and booking window cannot be negative.' );
		}

		$parsed = array();
		foreach ( self::DAYS as $day ) {
			$periods        = $hours[ $day ] ?? array();
			$parsed[ $day ] = is_string( $periods ) ? self::parse_periods( $periods ) : self::normalize_periods( $periods );
		}
		$this->hours = $parsed;

		$set = array();
		foreach ( $closures as $date ) {
			if ( self::is_valid_date( (string) $date ) ) {
				$set[ (string) $date ] = true;
			}
		}
		$this->closures = $set;
	}

	/**
	 * Builds the rules from the plugin settings array.
	 *
	 * @param array<string, mixed> $settings Settings (see Settings::defaults()).
	 * @return self
	 */
	public static function from_settings( array $settings ): self {
		return new self(
			(array) ( $settings['hours'] ?? array() ),
			(int) ( $settings['slot_interval'] ?? 30 ),
			(int) ( $settings['service_duration'] ?? 90 ),
			(int) ( $settings['capacity'] ?? 40 ),
			(int) ( $settings['max_party'] ?? 8 ),
			(array) ( $settings['closures'] ?? array() ),
			(int) ( $settings['min_notice'] ?? 120 ),
			(int) ( $settings['max_advance_days'] ?? 60 )
		);
	}

	/**
	 * Parses "12:00-14:30, 19:00-22:30" into sorted [open, close] minute pairs.
	 *
	 * Accepts "-", "–" or "to" as separator. "Closed" or an empty string means no service.
	 *
	 * @param string $value Human readable periods.
	 * @return array<int, array{0: int, 1: int}>
	 * @throws \InvalidArgumentException On a malformed or overlapping period.
	 */
	public static function parse_periods( string $value ): array {
		$value = trim( $value );
		if ( '' === $value || 0 === strcasecmp( $value, 'closed' ) ) {
			return array();
		}

		$periods = array();
		foreach ( preg_split( '/\s*[,;]\s*/', $value ) as $chunk ) {
			if ( ! preg_match( '/^(\d{1,2}):?(\d{2})\s*(?:-|–|—|to)\s*(\d{1,2}):?(\d{2})$/u', trim( $chunk ), $m ) ) {
				throw new \InvalidArgumentException( "Invalid opening period \"$chunk\". Use the HH:MM-HH:MM format." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain PHP class; escaped where displayed.
			}
			$periods[] = array( self::to_minutes( (int) $m[1], (int) $m[2] ), self::to_minutes( (int) $m[3], (int) $m[4] ) );
		}

		return self::normalize_periods( $periods );
	}

	/**
	 * Validates and sorts minute pairs.
	 *
	 * @param array<int, mixed> $periods Periods.
	 * @return array<int, array{0: int, 1: int}>
	 * @throws \InvalidArgumentException On an invalid or overlapping period.
	 */
	private static function normalize_periods( array $periods ): array {
		$clean = array();
		foreach ( $periods as $period ) {
			$open  = (int) ( $period[0] ?? -1 );
			$close = (int) ( $period[1] ?? -1 );
			if ( $open < 0 || $close > 1440 || $close <= $open ) {
				throw new \InvalidArgumentException( 'An opening period must close after it opens, on the same day.' );
			}
			$clean[] = array( $open, $close );
		}

		usort( $clean, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );

		for ( $i = 1, $count = count( $clean ); $i < $count; $i++ ) {
			if ( $clean[ $i ][0] < $clean[ $i - 1 ][1] ) {
				throw new \InvalidArgumentException( 'Opening periods of the same day cannot overlap.' );
			}
		}

		return $clean;
	}

	/**
	 * Hours and minutes to minutes since midnight.
	 *
	 * @param int $hours   Hours (0-24).
	 * @param int $minutes Minutes (0-59).
	 * @return int
	 * @throws \InvalidArgumentException When out of range.
	 */
	private static function to_minutes( int $hours, int $minutes ): int {
		if ( $hours > 24 || $minutes > 59 || ( 24 === $hours && 0 !== $minutes ) ) {
			throw new \InvalidArgumentException( 'Times must be between 00:00 and 24:00.' );
		}

		return $hours * 60 + $minutes;
	}

	/**
	 * Formats minutes since midnight as HH:MM.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function format_minutes( int $minutes ): string {
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Formats a day's periods back to "12:00-14:30, 19:00-22:30".
	 *
	 * @param array<int, array{0: int, 1: int}> $periods Periods.
	 * @return string
	 */
	public static function format_periods( array $periods ): string {
		return implode(
			', ',
			array_map( static fn( array $p ): string => self::format_minutes( $p[0] ) . '-' . self::format_minutes( $p[1] ), $periods )
		);
	}

	/**
	 * Whether a string is a real Y-m-d date.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}
}
