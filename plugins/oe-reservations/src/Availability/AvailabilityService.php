<?php
/**
 * Availability engine.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Availability;

defined( 'ABSPATH' ) || exit;

/**
 * Computes bookable time slots.
 *
 * Model:
 * - Each opening period yields slot starts every `slot_interval` minutes, as long as a whole
 *   service (`service_duration`) fits before the period closes.
 * - A reservation occupies its covers from its start time for `service_duration` minutes.
 * - A party of N can book slot T if, at every moment of [T, T + duration), the covers already
 *   seated plus N stay within `capacity`. Occupancy only changes when a reservation starts,
 *   so it is enough to check T itself and every reservation start inside the window.
 * - Days that are in the past, closed, exceptionally closed or beyond the booking window have
 *   no slots; slots closer than `min_notice` to "now" are unavailable.
 *
 * Pure PHP: "now", the timezone and existing reservations are injected, which keeps the class
 * deterministic and unit-testable without WordPress or a database.
 */
final class AvailabilityService {

	public const DAY_OPEN          = 'open';
	public const DAY_CLOSED        = 'closed';
	public const DAY_EXCEPTIONAL   = 'exceptional_closure';
	public const DAY_PAST          = 'past';
	public const DAY_BEYOND_WINDOW = 'beyond_window';
	public const DAY_INVALID_PARTY = 'invalid_party_size';

	public const SLOT_OK          = 'available';
	public const SLOT_FULL        = 'full';
	public const SLOT_TOO_SOON    = 'too_soon';
	public const SLOT_NOT_OFFERED = 'not_offered';

	/**
	 * Constructor.
	 *
	 * @param BookingRules  $rules    Rules.
	 * @param \DateTimeZone $timezone Restaurant timezone (the site timezone).
	 */
	public function __construct(
		private readonly BookingRules $rules,
		private readonly \DateTimeZone $timezone
	) {}

	/**
	 * Rules in use.
	 *
	 * @return BookingRules
	 */
	public function rules(): BookingRules {
		return $this->rules;
	}

	/**
	 * Availability of a whole day.
	 *
	 * @param string                                       $date         Date (Y-m-d).
	 * @param int                                          $guests       Party size.
	 * @param array<int, array{time: string, guests: int}> $reservations Active reservations of that day.
	 * @param \DateTimeImmutable                           $now          Current moment.
	 * @return array{date: string, status: string, slots: array<int, array{time: string, available: bool, remaining: int, reason: string}>}
	 */
	public function day( string $date, int $guests, array $reservations, \DateTimeImmutable $now ): array {
		$status = $this->day_status( $date, $guests, $now );
		$result = array(
			'date'   => $date,
			'status' => $status,
			'slots'  => array(),
		);

		if ( self::DAY_OPEN !== $status ) {
			return $result;
		}

		$bookings = $this->normalize_reservations( $reservations );
		$earliest = $now->setTimezone( $this->timezone )->modify( '+' . $this->rules->min_notice . ' minutes' );

		foreach ( $this->slot_starts( $date ) as $start ) {
			$remaining = $this->rules->capacity - $this->peak_occupancy( $start, $bookings );
			$moment    = $this->moment( $date, $start );

			if ( $moment < $earliest ) {
				$reason = self::SLOT_TOO_SOON;
			} elseif ( $remaining < $guests ) {
				$reason = self::SLOT_FULL;
			} else {
				$reason = self::SLOT_OK;
			}

			$result['slots'][] = array(
				'time'      => BookingRules::format_minutes( $start ),
				'available' => self::SLOT_OK === $reason,
				'remaining' => max( 0, $remaining ),
				'reason'    => $reason,
			);
		}

		return $result;
	}

	/**
	 * Whether a party can book a precise slot. Used again inside the insert transaction.
	 *
	 * @param string                                       $date         Date (Y-m-d).
	 * @param string                                       $time         Time (HH:MM).
	 * @param int                                          $guests       Party size.
	 * @param array<int, array{time: string, guests: int}> $reservations Active reservations of that day.
	 * @param \DateTimeImmutable                           $now          Current moment.
	 * @return string|null Null when bookable, otherwise a DAY_* or SLOT_* reason code.
	 */
	public function check( string $date, string $time, int $guests, array $reservations, \DateTimeImmutable $now ): ?string {
		$day = $this->day( $date, $guests, $reservations, $now );

		if ( self::DAY_OPEN !== $day['status'] ) {
			return $day['status'];
		}

		foreach ( $day['slots'] as $slot ) {
			if ( $slot['time'] === $time ) {
				return $slot['available'] ? null : $slot['reason'];
			}
		}

		return self::SLOT_NOT_OFFERED;
	}

	/**
	 * Status of a day before looking at slots.
	 *
	 * @param string             $date   Date (Y-m-d).
	 * @param int                $guests Party size.
	 * @param \DateTimeImmutable $now    Current moment.
	 * @return string DAY_* constant.
	 */
	public function day_status( string $date, int $guests, \DateTimeImmutable $now ): string {
		if ( $guests < 1 || $guests > $this->rules->max_party ) {
			return self::DAY_INVALID_PARTY;
		}

		$today = $now->setTimezone( $this->timezone )->format( 'Y-m-d' );
		if ( ! BookingRules::is_valid_date( $date ) || $date < $today ) {
			return self::DAY_PAST;
		}

		$last = $now->setTimezone( $this->timezone )->modify( '+' . $this->rules->max_advance_days . ' days' )->format( 'Y-m-d' );
		if ( $date > $last ) {
			return self::DAY_BEYOND_WINDOW;
		}

		if ( isset( $this->rules->closures[ $date ] ) ) {
			return self::DAY_EXCEPTIONAL;
		}

		if ( ! $this->periods_for( $date ) ) {
			return self::DAY_CLOSED;
		}

		return self::DAY_OPEN;
	}

	/**
	 * Opening periods of a date (empty when closed or exceptionally closed).
	 *
	 * @param string $date Date (Y-m-d).
	 * @return array<int, array{0: int, 1: int}>
	 */
	public function periods_for( string $date ): array {
		if ( ! BookingRules::is_valid_date( $date ) || isset( $this->rules->closures[ $date ] ) ) {
			return array();
		}

		$weekday = (int) ( new \DateTimeImmutable( $date, $this->timezone ) )->format( 'N' );

		return $this->rules->hours[ BookingRules::DAYS[ $weekday - 1 ] ];
	}

	/**
	 * Whether the restaurant is open at a given moment (for the "Open now" badge).
	 *
	 * @param \DateTimeImmutable $moment Moment.
	 * @return bool
	 */
	public function is_open_at( \DateTimeImmutable $moment ): bool {
		$local   = $moment->setTimezone( $this->timezone );
		$minutes = (int) $local->format( 'G' ) * 60 + (int) $local->format( 'i' );

		foreach ( $this->periods_for( $local->format( 'Y-m-d' ) ) as $period ) {
			if ( $minutes >= $period[0] && $minutes < $period[1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Next opening (or closing, when open) after a moment, within a week.
	 *
	 * @param \DateTimeImmutable $moment Moment.
	 * @return array{open: bool, change: \DateTimeImmutable|null}
	 */
	public function status_at( \DateTimeImmutable $moment ): array {
		$local   = $moment->setTimezone( $this->timezone );
		$minutes = (int) $local->format( 'G' ) * 60 + (int) $local->format( 'i' );

		for ( $offset = 0; $offset <= 14; $offset++ ) {
			$day  = $local->modify( "+$offset days" )->format( 'Y-m-d' );
			$from = 0 === $offset ? $minutes : -1;

			foreach ( $this->periods_for( $day ) as $period ) {
				if ( 0 === $offset && $minutes >= $period[0] && $minutes < $period[1] ) {
					return array(
						'open'   => true,
						'change' => $this->moment( $day, $period[1] ),
					);
				}
				if ( $period[0] > $from ) {
					return array(
						'open'   => false,
						'change' => $this->moment( $day, $period[0] ),
					);
				}
			}
		}

		return array(
			'open'   => false,
			'change' => null,
		);
	}

	/**
	 * Slot start times (minutes since midnight) of a date.
	 *
	 * @param string $date Date (Y-m-d).
	 * @return array<int, int>
	 */
	public function slot_starts( string $date ): array {
		$starts = array();

		foreach ( $this->periods_for( $date ) as $period ) {
			for ( $t = $period[0]; $t + $this->rules->service_duration <= $period[1]; $t += $this->rules->slot_interval ) {
				$starts[] = $t;
			}
		}

		return $starts;
	}

	/**
	 * Highest number of covers seated at any moment of [start, start + duration).
	 *
	 * @param int                                        $start    Slot start (minutes).
	 * @param array<int, array{start: int, guests: int}> $bookings Reservations.
	 * @return int
	 */
	private function peak_occupancy( int $start, array $bookings ): int {
		$end    = $start + $this->rules->service_duration;
		$points = array( $start );

		foreach ( $bookings as $booking ) {
			if ( $booking['start'] > $start && $booking['start'] < $end ) {
				$points[] = $booking['start'];
			}
		}

		$peak = 0;
		foreach ( $points as $point ) {
			$seated = 0;
			foreach ( $bookings as $booking ) {
				if ( $booking['start'] <= $point && $point < $booking['start'] + $this->rules->service_duration ) {
					$seated += $booking['guests'];
				}
			}
			$peak = max( $peak, $seated );
		}

		return $peak;
	}

	/**
	 * Reservation rows to start minutes + guests, ignoring malformed rows.
	 *
	 * @param array<int, array{time: string, guests: int}> $reservations Reservations.
	 * @return array<int, array{start: int, guests: int}>
	 */
	private function normalize_reservations( array $reservations ): array {
		$bookings = array();

		foreach ( $reservations as $reservation ) {
			if ( ! preg_match( '/^(\d{2}):(\d{2})/', (string) ( $reservation['time'] ?? '' ), $m ) ) {
				continue;
			}
			$bookings[] = array(
				'start'  => (int) $m[1] * 60 + (int) $m[2],
				'guests' => max( 0, (int) ( $reservation['guests'] ?? 0 ) ),
			);
		}

		return $bookings;
	}

	/**
	 * Local moment of a date + minutes since midnight.
	 *
	 * @param string $date    Date (Y-m-d).
	 * @param int    $minutes Minutes.
	 * @return \DateTimeImmutable
	 */
	private function moment( string $date, int $minutes ): \DateTimeImmutable {
		// Wall-clock time (setTime), so DST changes never shift a slot.
		return ( new \DateTimeImmutable( $date, $this->timezone ) )->setTime( intdiv( $minutes, 60 ), $minutes % 60 );
	}
}
