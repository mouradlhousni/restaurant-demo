<?php
/**
 * Block registration.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic blocks built into build/blocks/ (sources in blocks/).
 *
 * WordPress only enqueues a block's front-end style and view script on pages that
 * actually contain the block, so assets stay conditional without extra code.
 */
final class Blocks {

	public const NAMES = array( 'menu-section', 'reservation-form', 'opening-hours', 'restaurant-map' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers each block from its built block.json.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( self::NAMES as $name ) {
			$dir = OE_RESERVATIONS_DIR . 'build/blocks/' . $name;
			if ( file_exists( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}
	}

	/**
	 * Whether the block is rendered for the block editor preview.
	 *
	 * @return bool
	 */
	public static function is_editor_preview(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
