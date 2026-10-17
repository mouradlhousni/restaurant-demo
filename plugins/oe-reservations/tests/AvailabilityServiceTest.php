<?php
/**
 * Availability engine tests.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Tests;

use OliveEmber\Reservations\Availability\AvailabilityService;
use OliveEmber\Reservations\Availability\BookingRules;
use PHPUnit\Framework\TestCase;

/**
 * Reference week: Monday closed, Tuesday to Sunday 12:00-15:00 and 19:00-23:00.
 * Slots every 30 minutes, 90-minute services, 20 covers, parties up to 8,
 * 2 hours notice, bookings open 30 days ahead. "Now" is Wednesday 7 October 2026, 10:00 in Paris.
 */
final class AvailabilityServiceTest extends TestCase {

	private const TZ = 'Europe/Paris';

	/**
	 * Service with the reference rules (overridable).
	 *
	 * @param array<string, mixed> $overrides Rule overrides.
	 * @return AvailabilityService
	 */
	private function service( array $overrides = array() ): AvailabilityService {
		$open  = '12:00-15:00, 19:00-23:00';
		$rules = array_merge(
			array(
				'hours'            => array(
					'mon' => 'Closed',
					'tue' => $open,
					'wed' => $open,
					'thu' => $open,
					'fri' => $open,
					'sat' => $open,
					'sun' => $open,
				),
				'slot_interval'    => 30,
				'service_duration' => 90,
				'capacity'         => 20,
				'max_party'        => 8,
				'closures'         => array( '2026-10-15' ),
				'min_notice'       => 120,
				'max_advance_days' => 30,
			),
			$overrides
		);

		return new AvailabilityService( BookingRules::from_settings( $rules ), new \DateTimeZone( self::TZ ) );
	}

	/**
	 * A moment in Paris.
	 *
	 * @param string $datetime Local date and time.
	 * @return \DateTimeImmutable
	 */
	private function at( string $datetime = '2026-10-07 10:00' ): \DateTimeImmutable {
		return new \DateTimeImmutable( $datetime, new \DateTimeZone( self::TZ ) );
	}

	/**
	 * Times of the available slots.
	 *
	 * @param array<string, mixed> $day Result of day().
	 * @return array<int, string>
	 */
	private function available_times( array $day ): array {
		return array_values(
			array_map(
				static fn( array $slot ): string => $slot['time'],
				array_filter( $day['slots'], static fn( array $slot ): bool => $slot['available'] )
			)
		);
	}

	public function test_slots_follow_opening_hours_and_service_duration(): void {
		$day = $this->service()->day( '2026-10-08', 2, array(), $this->at() );

		$this->assertSame( AvailabilityService::DAY_OPEN, $day['status'] );
		$this->assertSame(
			array( '12:00', '12:30', '13:00', '13:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30' ),
			$this->available_times( $day ),
			'The last slot of a period must leave room for a full 90-minute service.'
		);
		$this->assertSame( 20, $day['slots'][0]['remaining'] );
	}

	public function test_slot_interval_is_respected(): void {
		$day = $this->service( array( 'slot_interval' => 45 ) )->day( '2026-10-08', 2, array(), $this->at() );

		$this->assertSame( array( '12:00', '12:45', '13:30', '19:00', '19:45', '20:30', '21:15' ), $this->available_times( $day ) );
	}

	public function test_weekly_closing_day_has_no_slots(): void {
		$day = $this->service()->day( '2026-10-12', 2, array(), $this->at() );

		$this->assertSame( AvailabilityService::DAY_CLOSED, $day['status'] );
		$this->assertSame( array(), $day['slots'] );
	}

	public function test_exceptional_closure_has_no_slots(): void {
		$day = $this->service()->day( '2026-10-15', 2, array(), $this->at() );

		$this->assertSame( AvailabilityService::DAY_EXCEPTIONAL, $day['status'] );
		$this->assertSame( array(), $day['slots'] );
	}

	public function test_past_dates_and_dates_beyond_the_booking_window_are_refused(): void {
		$service = $this->service();

		$this->assertSame( AvailabilityService::DAY_PAST, $service->day_status( '2026-10-06', 2, $this->at() ) );
		$this->assertSame( AvailabilityService::DAY_OPEN, $service->day_status( '2026-11-06', 2, $this->at() ), '30 days ahead is still bookable.' );
		$this->assertSame( AvailabilityService::DAY_BEYOND_WINDOW, $service->day_status( '2026-11-07', 2, $this->at() ) );
		$this->assertSame( AvailabilityService::DAY_PAST, $service->day_status( '2026-02-30', 2, $this->at() ), 'Impossible dates are refused.' );
	}

	public function test_party_size_limits(): void {
		$service = $this->service();

		$this->assertSame( AvailabilityService::DAY_INVALID_PARTY, $service->day_status( '2026-10-08', 0, $this->at() ) );
		$this->assertSame( AvailabilityService::DAY_OPEN, $service->day_status( '2026-10-08', 8, $this->at() ) );
		$this->assertSame( AvailabilityService::DAY_INVALID_PARTY, $service->day_status( '2026-10-08', 9, $this->at() ) );
	}

	public function test_minimum_notice_blocks_slots_that_are_too_soon(): void {
		$day = $this->service()->day( '2026-10-07', 2, array(), $this->at( '2026-10-07 11:00' ) );

		$this->assertSame( AvailabilityService::SLOT_TOO_SOON, $day['slots'][0]['reason'], '12:00 is less than 2 hours away.' );
		$this->assertSame( AvailabilityService::SLOT_TOO_SOON, $day['slots'][1]['reason'] );
		$this->assertSame( '13:00', $this->available_times( $day )[0], '13:00 is exactly 2 hours away.' );
	}

	public function test_today_uses_the_restaurant_timezone_not_utc(): void {
		// 23:30 UTC on the 6th is already 01:30 on the 7th in Paris.
		$now = new \DateTimeImmutable( '2026-10-06 23:30', new \DateTimeZone( 'UTC' ) );

		$this->assertSame( AvailabilityService::DAY_PAST, $this->service()->day_status( '2026-10-06', 2, $now ) );
		$this->assertSame( AvailabilityService::DAY_OPEN, $this->service()->day_status( '2026-10-07', 2, $now ) );
	}

	public function test_capacity_is_shared_by_overlapping_reservations(): void {
		$reservations = array(
			array(
				'time'   => '19:00',
				'guests' => 10,
			),
			array(
				'time'   => '19:00',
				'guests' => 6,
			),
		);
		$service      = $this->service();

		$day_for_4 = $service->day( '2026-10-08', 4, $reservations, $this->at() );
		$this->assertContains( '19:00', $this->available_times( $day_for_4 ), '16 + 4 = 20 covers fits exactly.' );

		$day_for_5 = $service->day( '2026-10-08', 5, $reservations, $this->at() );
		$this->assertNotContains( '19:00', $this->available_times( $day_for_5 ) );
		$this->assertNotContains( '19:30', $this->available_times( $day_for_5 ), 'The 19:00 tables are still seated at 19:30.' );
		$this->assertNotContains( '20:00', $this->available_times( $day_for_5 ) );
		$this->assertContains( '20:30', $this->available_times( $day_for_5 ), 'The 19:00 service ends at 20:30.' );
	}

	public function test_a_later_reservation_blocks_an_earlier_slot_that_would_overlap_it(): void {
		$reservations = array(
			array(
				'time'   => '20:00',
				'guests' => 18,
			),
		);
		$day          = $this->service()->day( '2026-10-08', 4, $reservations, $this->at() );

		$this->assertNotContains( '19:00', $this->available_times( $day ), 'A 19:00 table would still be seated at 20:00.' );
		$this->assertSame( 2, $day['slots'][4]['remaining'] );
		$this->assertContains( '21:30', $this->available_times( $day ) );
		$this->assertContains( '13:30', $this->available_times( $day ), 'Lunch is not affected by dinner bookings.' );
	}

	public function test_off_grid_reservations_are_counted(): void {
		$reservations = array(
			array(
				'time'   => '19:45:00',
				'guests' => 20,
			),
		);
		$day          = $this->service()->day( '2026-10-08', 1, $reservations, $this->at() );

		$this->assertSame( array( '12:00', '12:30', '13:00', '13:30', '21:30' ), $this->available_times( $day ) );
	}

	public function test_check_validates_a_precise_slot(): void {
		$service      = $this->service();
		$reservations = array(
			array(
				'time'   => '19:00',
				'guests' => 18,
			),
		);

		$this->assertNull( $service->check( '2026-10-08', '19:00', 2, $reservations, $this->at() ) );
		$this->assertSame( AvailabilityService::SLOT_FULL, $service->check( '2026-10-08', '19:00', 3, $reservations, $this->at() ) );
		$this->assertSame( AvailabilityService::SLOT_NOT_OFFERED, $service->check( '2026-10-08', '19:15', 2, array(), $this->at() ) );
		$this->assertSame( AvailabilityService::SLOT_NOT_OFFERED, $service->check( '2026-10-08', '14:00', 2, array(), $this->at() ), '14:00 + 90 min ends after closing.' );
		$this->assertSame( AvailabilityService::DAY_CLOSED, $service->check( '2026-10-12', '19:00', 2, array(), $this->at() ) );
	}

	public function test_open_now_status(): void {
		$service = $this->service();

		$this->assertTrue( $service->is_open_at( $this->at( '2026-10-07 12:00' ) ) );
		$this->assertFalse( $service->is_open_at( $this->at( '2026-10-07 15:00' ) ), 'Closing time is exclusive.' );
		$this->assertFalse( $service->is_open_at( $this->at( '2026-10-12 20:00' ) ), 'Closed on Mondays.' );
		$this->assertFalse( $service->is_open_at( $this->at( '2026-10-15 20:00' ) ), 'Exceptional closure.' );

		$open = $service->status_at( $this->at( '2026-10-07 20:00' ) );
		$this->assertTrue( $open['open'] );
		$this->assertSame( '2026-10-07 23:00', $open['change']->format( 'Y-m-d H:i' ) );

		$between = $service->status_at( $this->at( '2026-10-07 16:00' ) );
		$this->assertFalse( $between['open'] );
		$this->assertSame( '2026-10-07 19:00', $between['change']->format( 'Y-m-d H:i' ) );

		$sunday_night = $service->status_at( $this->at( '2026-10-11 23:30' ) );
		$this->assertSame( '2026-10-13 12:00', $sunday_night['change']->format( 'Y-m-d H:i' ), 'Skips the Monday closing day.' );
	}

	public function test_slots_keep_wall_clock_times_across_daylight_saving_changes(): void {
		// Clocks go back one hour in Paris on Sunday 25 October 2026.
		$status = $this->service()->status_at( $this->at( '2026-10-25 08:00' ) );

		$this->assertSame( '12:00', $status['change']->format( 'H:i' ) );
		$this->assertSame( '+01:00', $status['change']->format( 'P' ) );
	}
}
