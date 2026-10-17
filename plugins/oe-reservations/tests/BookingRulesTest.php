<?php
/**
 * Booking rules tests.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Tests;

use OliveEmber\Reservations\Availability\BookingRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parsing and validation of opening hours and limits.
 */
final class BookingRulesTest extends TestCase {

	public function test_parses_and_sorts_opening_periods(): void {
		$this->assertSame(
			array( array( 720, 870 ), array( 1140, 1350 ) ),
			BookingRules::parse_periods( '19:00 – 22:30, 12:00-14:30' )
		);
		$this->assertSame( array( array( 1080, 1440 ) ), BookingRules::parse_periods( '18:00 to 24:00' ) );
	}

	public function test_closed_or_empty_means_no_service(): void {
		$this->assertSame( array(), BookingRules::parse_periods( 'Closed' ) );
		$this->assertSame( array(), BookingRules::parse_periods( '  ' ) );
	}

	/**
	 * Invalid inputs.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_periods(): array {
		return array(
			'garbage'           => array( 'lunch time' ),
			'hour out of range' => array( '25:00-26:00' ),
			'closes too early'  => array( '14:00-12:00' ),
			'overlap'           => array( '12:00-15:00, 14:00-18:00' ),
			'after midnight'    => array( '24:30-01:00' ),
		);
	}

	#[DataProvider( 'invalid_periods' )]
	public function test_rejects_invalid_periods( string $value ): void {
		$this->expectException( \InvalidArgumentException::class );
		BookingRules::parse_periods( $value );
	}

	public function test_formats_periods_back(): void {
		$this->assertSame( '12:00-14:30, 19:00-22:30', BookingRules::format_periods( BookingRules::parse_periods( '12:00-14:30,19:00-22:30' ) ) );
	}

	public function test_rejects_non_positive_limits(): void {
		$this->expectException( \InvalidArgumentException::class );
		new BookingRules( array(), 30, 90, 0 );
	}

	public function test_ignores_invalid_closure_dates(): void {
		$rules = new BookingRules( array(), closures: array( '2026-12-25', '2026-13-01', 'tomorrow' ) );

		$this->assertSame( array( '2026-12-25' => true ), $rules->closures );
	}
}
