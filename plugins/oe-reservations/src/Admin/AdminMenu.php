<?php
/**
 * Admin menu.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level "Reservations" menu. Screens add themselves as submenus.
 */
final class AdminMenu {

	public const SLUG = 'oe-reservations';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
	}

	/**
	 * Registers the top-level menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Reservations', 'oe-reservations' ),
			__( 'Reservations', 'oe-reservations' ),
			oe_capability(),
			self::SLUG,
			'__return_null',
			'dashicons-calendar-alt',
			26
		);
	}
}
