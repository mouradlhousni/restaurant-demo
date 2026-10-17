<?php
/**
 * "Demo site" banner and admin notice.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Discreet banner at the top of every page in demo mode and in the static export,
 * plus a notice on every admin screen in demo mode.
 */
final class DemoBanner {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_body_open', array( $this, 'render' ), 5 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Front-end banner.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( oe_is_static_build() ) {
			printf(
				'<p class="oe-demo-banner" role="note">%s <a href="%s" rel="noopener">%s</a></p>',
				esc_html__( 'Static preview of a portfolio project.', 'oe-reservations' ),
				esc_url( oe_playground_url() ),
				esc_html__( 'Try the live demo, booking included', 'oe-reservations' )
			);
			return;
		}

		if ( oe_is_demo_mode() ) {
			printf(
				'<p class="oe-demo-banner" role="note"><strong>%s</strong> %s</p>',
				esc_html__( 'Demo site.', 'oe-reservations' ),
				esc_html__( 'Bookings run end to end, but nothing is stored and no email is sent.', 'oe-reservations' )
			);
		}
	}

	/**
	 * Admin notice.
	 *
	 * @return void
	 */
	public function admin_notice(): void {
		if ( ! oe_is_demo_mode() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Demo mode is on.', 'oe-reservations' ),
			esc_html__( 'Forms validate normally, but reservations, settings and status changes are not saved and no email is sent (OE_DEMO_MODE in wp-config.php).', 'oe-reservations' )
		);
	}
}
