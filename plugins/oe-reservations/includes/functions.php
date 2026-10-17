<?php
/**
 * Public template API of the plugin.
 *
 * These global, `oe_`-prefixed functions are the only contract the theme relies on,
 * so the theme never touches plugin internals and degrades gracefully without the plugin.
 *
 * @package OliveEmber\Reservations
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the site runs in portfolio demo mode (forms validate but never store or send anything).
 *
 * Single source of truth for the OE_DEMO_MODE constant.
 *
 * @return bool
 */
function oe_is_demo_mode(): bool {
	$enabled = defined( 'OE_DEMO_MODE' ) && OE_DEMO_MODE;

	/**
	 * Filters whether demo mode is active.
	 *
	 * @param bool $enabled Demo mode state.
	 */
	return (bool) apply_filters( 'oe_is_demo_mode', $enabled );
}

/**
 * Whether the site is being rendered for the static HTML export.
 *
 * @return bool
 */
function oe_is_static_build(): bool {
	return defined( 'OE_STATIC_BUILD' ) && OE_STATIC_BUILD;
}

/**
 * URL of the WordPress Playground live demo.
 *
 * @return string
 */
function oe_playground_url(): string {
	$url = 'https://playground.wordpress.net/?blueprint-url=' . rawurlencode( 'https://raw.githubusercontent.com/mouradlhousni/restaurant-demo/master/blueprint.json' );

	/**
	 * Filters the Playground demo URL.
	 *
	 * @param string $url URL.
	 */
	return (string) apply_filters( 'oe_playground_url', $url );
}

/**
 * Currency symbol used for menu prices.
 *
 * @return string
 */
function oe_currency_symbol(): string {
	/**
	 * Filters the currency symbol.
	 *
	 * @param string $symbol Symbol.
	 */
	return (string) apply_filters( 'oe_currency_symbol', '€' );
}

/**
 * Formats a menu price: "€18", "€9.50".
 *
 * @param float $price Price.
 * @return string
 */
function oe_format_price( float $price ): string {
	$decimals = floor( $price ) === $price ? 0 : 2;

	return oe_currency_symbol() . number_format_i18n( $price, $decimals );
}

/**
 * Capability required to manage reservations and settings.
 *
 * @return string
 */
function oe_capability(): string {
	/**
	 * Filters the capability needed for the Reservations admin screens.
	 *
	 * @param string $capability Capability.
	 */
	return (string) apply_filters( 'oe_capability', 'manage_options' );
}

/**
 * URL of the page holding the "Reservation Form" block (cancellation links point there).
 *
 * The page ID is stored in `oe_reservation_page` by the seed command; otherwise the first
 * published page containing the block is looked up once and remembered.
 *
 * @return string
 */
function oe_reservation_page_url(): string {
	$page_id = (int) get_option( 'oe_reservation_page', 0 );

	if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
		$pages   = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				's'              => '<!-- wp:oe/reservation-form',
				'search_columns' => array( 'post_content' ),
			)
		);
		$page_id = $pages ? (int) $pages[0] : 0;
		if ( $page_id ) {
			update_option( 'oe_reservation_page', $page_id, false );
		}
	}

	return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
}

/**
 * One plugin setting (restaurant name, address, phone…).
 *
 * @param string $key      Setting key, see Settings::defaults().
 * @param mixed  $fallback Value when missing.
 * @return mixed
 */
function oe_setting( string $key, $fallback = '' ) {
	return \OliveEmber\Reservations\Settings\Settings::get( $key, $fallback );
}

/**
 * Restaurant postal address on one line.
 *
 * @return string
 */
function oe_address(): string {
	return trim( oe_setting( 'street_address' ) . ', ' . oe_setting( 'postal_code' ) . ' ' . oe_setting( 'locality' ), ', ' );
}

/**
 * Menu sections in display order (for themes).
 *
 * @return array<int, array{id: int, slug: string, name: string, description: string, order: int, count: int}>
 */
function oe_menu_sections(): array {
	return \OliveEmber\Reservations\Menu\MenuRepository::sections();
}

/**
 * Published dishes (for themes).
 *
 * @param array{section?: int, chefs_pick?: bool, limit?: int} $args Filters, see MenuRepository::items().
 * @return array<int, array<string, mixed>>
 */
function oe_menu_items( array $args = array() ): array {
	return \OliveEmber\Reservations\Menu\MenuRepository::items( $args );
}
