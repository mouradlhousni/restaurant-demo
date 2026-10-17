<?php
/**
 * "Restaurant Map" block markup.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Blocks;

use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * OpenStreetMap of the restaurant. Leaflet (self-hosted) is only downloaded when the map
 * scrolls into view; until then, and without JavaScript, a plain link is shown.
 */
final class RestaurantMapView {

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$lat  = (float) Settings::get( 'latitude' );
		$lng  = (float) Settings::get( 'longitude' );
		$zoom = min( 19, max( 10, (int) ( $attributes['zoom'] ?? 16 ) ) );
		$name = (string) Settings::get( 'restaurant_name' );

		$osm        = sprintf( 'https://www.openstreetmap.org/?mlat=%1$.6F&mlon=%2$.6F#map=%3$d/%1$.6F/%2$.6F', $lat, $lng, $zoom );
		$directions = sprintf( 'https://www.openstreetmap.org/directions?route=%%3B%1$.6F%%2C%2$.6F', $lat, $lng );

		$config = array(
			'lat'     => $lat,
			'lng'     => $lng,
			'zoom'    => $zoom,
			'title'   => $name,
			'address' => oe_address(),
			'leaflet' => array(
				'js'     => OE_RESERVATIONS_URL . 'assets/vendor/leaflet/leaflet.js',
				'css'    => OE_RESERVATIONS_URL . 'assets/vendor/leaflet/leaflet.css',
				'images' => OE_RESERVATIONS_URL . 'assets/vendor/leaflet/images/',
			),
		);

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'oe-map' ) );

		return sprintf(
			'<figure %1$s>
				<div class="oe-map__canvas" data-oe-map="%2$s" role="region" aria-label="%3$s">
					<p class="oe-map__fallback">%4$s</p>
				</div>
				<figcaption class="oe-map__caption"><span>%5$s</span> <a href="%6$s" rel="noopener">%7$s</a> · <a href="%8$s" rel="noopener">%9$s</a></figcaption>
			</figure>',
			$wrapper, // Escaped by get_block_wrapper_attributes().
			esc_attr( (string) wp_json_encode( $config ) ),
			/* translators: %s: restaurant name. */
			esc_attr( sprintf( __( 'Map showing the location of %s', 'oe-reservations' ), $name ) ),
			esc_html__( 'Loading the map…', 'oe-reservations' ),
			esc_html( oe_address() ),
			esc_url( $osm ),
			esc_html__( 'Open in OpenStreetMap', 'oe-reservations' ),
			esc_url( $directions ),
			esc_html__( 'Get directions', 'oe-reservations' )
		);
	}
}
