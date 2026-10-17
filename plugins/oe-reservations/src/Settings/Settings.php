<?php
/**
 * Plugin settings.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Settings;

use OliveEmber\Reservations\Availability\AvailabilityService;
use OliveEmber\Reservations\Availability\BookingRules;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to the `oe_settings` option, merged with defaults.
 */
final class Settings {

	public const OPTION = 'oe_settings';

	/**
	 * Request-level cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$week = '12:00-14:30, 19:00-22:30';

		return array(
			// Restaurant details (contact page, emails, structured data).
			'restaurant_name'    => 'Olive & Ember',
			'street_address'     => '14 Rue des Oliviers',
			'postal_code'        => '13001',
			'locality'           => 'Marseille',
			'country'            => 'FR',
			'phone'              => '+33 4 91 00 00 00',
			'contact_email'      => 'hello@olive-ember.test',
			'notification_email' => (string) get_option( 'admin_email' ),
			'latitude'           => 43.2965,
			'longitude'          => 5.3698,
			'price_range'        => '€€€',
			'cuisine'            => 'Mediterranean',

			// Opening hours, "HH:MM-HH:MM" periods per weekday.
			'hours'              => array(
				'mon' => '',
				'tue' => $week,
				'wed' => $week,
				'thu' => $week,
				'fri' => '12:00-14:30, 19:00-23:00',
				'sat' => '12:00-15:00, 19:00-23:00',
				'sun' => '12:00-15:30',
			),

			// Booking rules.
			'slot_interval'      => 30,
			'service_duration'   => 90,
			'capacity'           => 40,
			'max_party'          => 8,
			'closures'           => array(),
			'min_notice'         => 120,
			'max_advance_days'   => 60,
			'auto_confirm'       => true,
		);
	}

	/**
	 * All settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored          = get_option( self::OPTION, array() );
			$defaults        = self::defaults();
			$merged          = array_merge( $defaults, is_array( $stored ) ? $stored : array() );
			$merged['hours'] = array_merge( $defaults['hours'], (array) $merged['hours'] );
			self::$cache     = $merged;
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Value when missing.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		return self::all()[ $key ] ?? $fallback;
	}

	/**
	 * Clears the request cache (after an update).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Booking rules built from the settings; falls back to defaults if the stored values are invalid.
	 *
	 * @return BookingRules
	 */
	public static function rules(): BookingRules {
		try {
			return BookingRules::from_settings( self::all() );
		} catch ( \InvalidArgumentException $e ) {
			return BookingRules::from_settings( self::defaults() );
		}
	}

	/**
	 * Availability engine configured for this site.
	 *
	 * @return AvailabilityService
	 */
	public static function availability(): AvailabilityService {
		return new AvailabilityService( self::rules(), wp_timezone() );
	}

	/**
	 * Opening hours for display: one row per weekday, in site locale.
	 *
	 * @return array<int, array{key: string, label: string, short: string, periods: array<int, array{0: string, 1: string}>}>
	 */
	public static function opening_hours(): array {
		global $wp_locale;

		$rules = self::rules();
		$rows  = array();

		foreach ( BookingRules::DAYS as $index => $day ) {
			$weekday = ( $index + 1 ) % 7; // WP_Locale: 0 = Sunday.
			$rows[]  = array(
				'key'     => $day,
				'label'   => $wp_locale->get_weekday( $weekday ),
				'short'   => $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $weekday ) ),
				'periods' => array_map(
					static fn( array $p ): array => array( BookingRules::format_minutes( $p[0] ), BookingRules::format_minutes( $p[1] ) ),
					$rules->hours[ $day ]
				),
			);
		}

		return $rows;
	}

	/**
	 * Data needed by the front-end to compute "Open now" on its own (also in the static export).
	 *
	 * @return array<string, mixed>
	 */
	public static function public_schedule(): array {
		$rules = self::rules();

		return array(
			'timezone' => wp_timezone_string(),
			'hours'    => $rules->hours,
			'closures' => array_keys( $rules->closures ),
		);
	}
}
