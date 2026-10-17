<?php
/**
 * Structured data.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Frontend;

use OliveEmber\Reservations\Availability\BookingRules;
use OliveEmber\Reservations\Menu\MenuRepository;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * schema.org `Restaurant` JSON-LD (address, geo, opening hours, menu, acceptsReservations),
 * printed on the home page, the menu page and the booking and contact pages.
 */
final class JsonLd {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', array( $this, 'print' ), 20 );
	}

	/**
	 * Prints the script tag where relevant.
	 *
	 * @return void
	 */
	public function print(): void {
		if ( ! is_front_page() && ! is_page( array( 'menu', 'book-a-table', 'contact' ) ) ) {
			return;
		}

		printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( self::build(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) );
	}

	/**
	 * The Restaurant entity.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$s = Settings::all();

		$data = array(
			'@context'                  => 'https://schema.org',
			'@type'                     => 'Restaurant',
			'@id'                       => home_url( '/#restaurant' ),
			'name'                      => $s['restaurant_name'],
			'url'                       => home_url( '/' ),
			'telephone'                 => $s['phone'],
			'email'                     => $s['contact_email'],
			'servesCuisine'             => $s['cuisine'],
			'priceRange'                => $s['price_range'],
			'address'                   => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $s['street_address'],
				'postalCode'      => $s['postal_code'],
				'addressLocality' => $s['locality'],
				'addressCountry'  => $s['country'],
			),
			'geo'                       => array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $s['latitude'],
				'longitude' => (float) $s['longitude'],
			),
			'openingHoursSpecification' => self::opening_hours(),
			'acceptsReservations'       => oe_reservation_page_url(),
			'hasMenu'                   => self::menu(),
		);

		/**
		 * Filters the Restaurant structured data.
		 *
		 * @param array<string, mixed> $data Data.
		 */
		return (array) apply_filters( 'oe_json_ld', $data );
	}

	/**
	 * One specification per weekday period, plus exceptional closures.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function opening_hours(): array {
		$names = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);
		$rules = Settings::rules();
		$specs = array();

		foreach ( $rules->hours as $day => $periods ) {
			foreach ( $periods as $period ) {
				$specs[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $names[ $day ],
					'opens'     => BookingRules::format_minutes( $period[0] ),
					'closes'    => BookingRules::format_minutes( min( 1439, $period[1] ) ),
				);
			}
		}

		$today = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		foreach ( array_keys( $rules->closures ) as $date ) {
			if ( $date >= $today ) {
				$specs[] = array(
					'@type'        => 'OpeningHoursSpecification',
					'opens'        => '00:00',
					'closes'       => '00:00',
					'validFrom'    => $date,
					'validThrough' => $date,
				);
			}
		}

		return $specs;
	}

	/**
	 * The menu: sections and dishes with prices.
	 *
	 * @return array<string, mixed>
	 */
	private static function menu(): array {
		$sections = array();

		foreach ( MenuRepository::sections() as $section ) {
			$items = array();
			foreach ( MenuRepository::items( array( 'section' => $section['id'] ) ) as $item ) {
				$entry = array(
					'@type'       => 'MenuItem',
					'name'        => $item['name'],
					'description' => $item['description'],
					'offers'      => array(
						'@type'         => 'Offer',
						'price'         => number_format( $item['price'], 2, '.', '' ),
						'priceCurrency' => 'EUR',
					),
				);
				$diets = array_intersect_key(
					array(
						'vegetarian'  => 'https://schema.org/VegetarianDiet',
						'vegan'       => 'https://schema.org/VeganDiet',
						'gluten-free' => 'https://schema.org/GlutenFreeDiet',
					),
					$item['diets']
				);
				if ( $diets ) {
					$entry['suitableForDiet'] = array_values( $diets );
				}
				$items[] = $entry;
			}

			$sections[] = array(
				'@type'       => 'MenuSection',
				'name'        => $section['name'],
				'hasMenuItem' => $items,
			);
		}

		$menu_page = get_page_by_path( 'menu' );

		return array(
			'@type'          => 'Menu',
			'url'            => $menu_page ? get_permalink( $menu_page ) : home_url( '/' ),
			'hasMenuSection' => $sections,
		);
	}
}
