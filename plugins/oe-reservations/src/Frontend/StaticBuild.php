<?php
/**
 * Static export mode.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * While OE_STATIC_BUILD is on (only during bin/export-static.sh):
 *
 * - `<body>` gets `data-static-build="1"` (block themes print `<body>` from core's
 *   template canvas, so it is added with an output buffer that only runs in this mode);
 * - REST, oEmbed and RSD discovery links are not printed;
 * - the front-end scripts read the flag and never call the REST API: the hours and the
 *   menu filters are pure JavaScript, and the booking form renders a static preview.
 */
final class StaticBuild {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! oe_is_static_build() ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'buffer' ), 0 );
		add_filter( 'body_class', array( $this, 'body_class' ) );

		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		add_filter( 'show_admin_bar', '__return_false' );
	}

	/**
	 * Body class, for CSS.
	 *
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public function body_class( array $classes ): array {
		$classes[] = 'is-static-build';

		return $classes;
	}

	/**
	 * Starts the output buffer that adds the data attribute to `<body>`.
	 *
	 * @return void
	 */
	public function buffer(): void {
		ob_start( array( self::class, 'add_body_attribute' ) );
	}

	/**
	 * Adds data-static-build="1" to the first `<body>` tag.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public static function add_body_attribute( string $html ): string {
		return (string) preg_replace( '/<body(\s|>)/i', '<body data-static-build="1"$1', $html, 1 );
	}
}
