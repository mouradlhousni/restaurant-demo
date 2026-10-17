<?php
/**
 * Olive & Ember theme.
 *
 * Block theme: layout lives in templates/, parts/ and patterns/; this file only loads
 * assets and registers the pattern category. Business features (menu, bookings, opening
 * hours) come from the Olive & Ember Reservations plugin, through its `oe_*` functions.
 *
 * @package OliveEmber\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'OLIVE_EMBER_VERSION', '1.1.0' );

/**
 * Theme supports and editor styles.
 *
 * @return void
 */
function olive_ember_setup(): void {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/main.css' );
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'olive_ember_setup' );

/**
 * Asset URL, minified unless SCRIPT_DEBUG.
 *
 * @param string $path Path relative to assets/, without extension (e.g. "css/main").
 * @param string $ext  css | js.
 * @return string
 */
function olive_ember_asset( string $path, string $ext ): string {
	$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( get_theme_file_path( "assets/$path.min.$ext" ) ) ? '' : '.min';

	return get_theme_file_uri( "assets/$path$min.$ext" );
}

/**
 * Front-end styles and script.
 *
 * @return void
 */
function olive_ember_enqueue(): void {
	wp_enqueue_style( 'olive-ember', olive_ember_asset( 'css/main', 'css' ), array(), OLIVE_EMBER_VERSION );
	wp_enqueue_script(
		'olive-ember',
		olive_ember_asset( 'js/theme', 'js' ),
		array(),
		OLIVE_EMBER_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'olive_ember_enqueue' );

/**
 * Preloads the display font used by the first heading (LCP).
 *
 * @return void
 */
function olive_ember_preload_fonts(): void {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_theme_file_uri( 'assets/fonts/outfit-latin.woff2' ) )
	);
}
add_action( 'wp_head', 'olive_ember_preload_fonts', 1 );

/**
 * Pattern category.
 *
 * @return void
 */
function olive_ember_patterns(): void {
	register_block_pattern_category( 'olive-ember', array( 'label' => __( 'Olive & Ember', 'olive-ember' ) ) );
}
add_action( 'init', 'olive_ember_patterns' );

/**
 * A plugin setting, or a fallback when the plugin is inactive.
 *
 * @param string $key      Setting key.
 * @param string $fallback Fallback.
 * @return string
 */
function olive_ember_setting( string $key, string $fallback = '' ): string {
	return function_exists( 'oe_setting' ) ? (string) oe_setting( $key, $fallback ) : $fallback;
}

/**
 * URL of a page by slug (patterns link to the seeded pages), home as fallback.
 *
 * @param string $slug Page slug.
 * @return string
 */
function olive_ember_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );

	return $page ? (string) get_permalink( $page ) : home_url( '/' );
}

/**
 * Booking page URL.
 *
 * @return string
 */
function olive_ember_booking_url(): string {
	return function_exists( 'oe_reservation_page_url' ) ? oe_reservation_page_url() : olive_ember_page_url( 'book-a-table' );
}

/**
 * A photo slot: the image dropped into assets/images/photos/{slot}.(webp|png|jpg), or a placeholder.
 *
 * Slots and expected sizes are listed in assets/images/photos/README.md. Until a file exists the
 * page shows a neutral placeholder, so layouts never break and no request 404s.
 *
 * @param string               $slot Slot name, e.g. "hero-plate".
 * @param string               $alt  Alternative text ('' for decorative images).
 * @param array<string, mixed> $args class, eager (bool: above the fold), sizes.
 * @return string HTML.
 */
function olive_ember_image( string $slot, string $alt = '', array $args = array() ): string {
	$args  = wp_parse_args(
		$args,
		array(
			'class' => '',
			'eager' => false,
			'sizes' => '',
		)
	);
	$class = trim( 'oe-photo ' . $args['class'] );

	foreach ( array( 'webp', 'png', 'jpg' ) as $ext ) {
		$path = get_theme_file_path( "assets/images/photos/$slot.$ext" );
		if ( ! file_exists( $path ) ) {
			continue;
		}
		$size = wp_getimagesize( $path );

		return sprintf(
			'<img class="%1$s" src="%2$s" alt="%3$s"%4$s %5$s decoding="async"%6$s>',
			esc_attr( $class ),
			esc_url( get_theme_file_uri( "assets/images/photos/$slot.$ext" ) ),
			esc_attr( $alt ),
			$size ? sprintf( ' width="%d" height="%d"', (int) $size[0], (int) $size[1] ) : '',
			$args['eager'] ? 'fetchpriority="high"' : 'loading="lazy"',
			$args['sizes'] ? ' sizes="' . esc_attr( $args['sizes'] ) . '"' : ''
		);
	}

	return sprintf(
		'<span class="%1$s oe-placeholder" data-slot="%2$s" aria-hidden="true"><span>%2$s</span></span>',
		esc_attr( $class ),
		esc_attr( $slot )
	);
}

/**
 * URL of a decorative SVG in assets/images/deco/.
 *
 * @param string $name File name without extension.
 * @return string
 */
function olive_ember_deco( string $name ): string {
	return get_theme_file_uri( "assets/images/deco/$name.svg" );
}

/**
 * SVG favicon, unless a Site Icon is set in the Customizer.
 *
 * @return void
 */
function olive_ember_favicon(): void {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( get_theme_file_uri( 'assets/images/favicon.svg' ) ) );
}
add_action( 'wp_head', 'olive_ember_favicon', 2 );

/**
 * Pages get an excerpt field, used as their meta description.
 *
 * @return void
 */
function olive_ember_page_excerpts(): void {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'olive_ember_page_excerpts' );

/**
 * Meta description: the page excerpt, or the site tagline.
 *
 * Skipped when an SEO plugin is active, so descriptions are never printed twice.
 *
 * @return void
 */
function olive_ember_meta_description(): void {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}

	$description = is_singular() ? get_the_excerpt() : '';
	if ( '' === trim( (string) $description ) ) {
		$description = get_bloginfo( 'description' );
	}

	if ( $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $description ) ) );
	}
}
add_action( 'wp_head', 'olive_ember_meta_description', 1 );
