<?php
/**
 * Title: Booking page
 * Slug: olive-ember/booking-page
 * Categories: olive-ember
 * Description: Page header, reservation form, opening hours and practical information.
 *
 * @package OliveEmber\Theme
 */

$olive_ember_phone = olive_ember_setting( 'phone' );
?>
<!-- wp:group {"align":"full","className":"oe-page-hero oe-page-hero--compact","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-page-hero oe-page-hero--compact">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"oe-eyebrow"} -->
		<p class="oe-eyebrow"><?php esc_html_e( 'Reservations', 'olive-ember' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":1,"className":"oe-page-hero__title"} -->
		<h1 class="wp-block-heading oe-page-hero__title"><?php echo wp_kses( __( 'Book <em>a table</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h1>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"className":"oe-lead"} -->
		<p class="oe-lead"><?php esc_html_e( 'Choose a day, the number of guests and a time that is still free. It takes a minute.', 'olive-ember' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- wp:columns {"align":"wide","className":"oe-booking-layout"} -->
<div class="wp-block-columns alignwide oe-booking-layout">
	<!-- wp:column {"width":"64%"} -->
	<div class="wp-block-column" style="flex-basis:64%">
		<!-- wp:oe/reservation-form {"heading":"Reserve online"} /-->
	</div>
	<!-- /wp:column -->
	<!-- wp:column {"width":"36%","className":"oe-booking__aside"} -->
	<div class="wp-block-column oe-booking__aside" style="flex-basis:36%">
		<!-- wp:heading {"className":"oe-label-title"} -->
		<h2 class="wp-block-heading oe-label-title"><?php esc_html_e( 'Opening hours', 'olive-ember' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:oe/opening-hours {"variant":"table"} /-->
		<!-- wp:heading {"className":"oe-label-title"} -->
		<h2 class="wp-block-heading oe-label-title"><?php esc_html_e( 'Good to know', 'olive-ember' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:html -->
		<ul class="oe-practical">
			<li>
				<?php
				/* translators: %s: phone number. */
				printf( esc_html__( 'Larger groups and private dining: call us on %s.', 'olive-ember' ), '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $olive_ember_phone ) ) . '">' . esc_html( $olive_ember_phone ) . '</a>' );
				?>
			</li>
			<li><?php esc_html_e( 'Tables are kept for 15 minutes after the booked time.', 'olive-ember' ); ?></li>
			<li><?php esc_html_e( 'Plans changed? Every confirmation email has a link to cancel in one click.', 'olive-ember' ); ?></li>
		</ul>
		<!-- /wp:html -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
