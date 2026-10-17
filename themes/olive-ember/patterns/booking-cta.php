<?php
/**
 * Title: Booking call to action
 * Slug: olive-ember/booking-cta
 * Categories: olive-ember
 * Description: Rounded cream panel framed by two plates, booking button and today's service timeline.
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:group {"align":"full","className":"oe-cta-wrap","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-cta-wrap">
	<!-- wp:group {"className":"oe-cta","backgroundColor":"cream","layout":{"type":"constrained","contentSize":"40rem"}} -->
	<div class="wp-block-group oe-cta has-cream-background-color has-background">
		<!-- wp:html -->
		<?php echo olive_ember_image( 'cta-left', '', array( 'class' => 'oe-cta__plate oe-cta__plate--left' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
		<?php echo olive_ember_image( 'cta-right', '', array( 'class' => 'oe-cta__plate oe-cta__plate--right' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
		<!-- /wp:html -->
		<!-- wp:paragraph {"className":"oe-eyebrow"} -->
		<p class="oe-eyebrow"><?php esc_html_e( 'Reservations', 'olive-ember' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php echo wp_kses( __( 'Your table, <em>in four steps</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Pick a day, tell us how many you are, choose a time that is still free, and you are booked. The confirmation arrives by email straight away, with a link to cancel if plans change.', 'olive-ember' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( olive_ember_booking_url() ); ?>"><?php esc_html_e( 'Book a table', 'olive-ember' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
		<!-- wp:oe/opening-hours {"variant":"strip","className":"oe-cta__hours"} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
