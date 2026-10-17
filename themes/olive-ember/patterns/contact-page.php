<?php
/**
 * Title: Contact page
 * Slug: olive-ember/contact-page
 * Categories: olive-ember
 * Description: Page header, room photo, address, contact and opening hours (from the reservation settings) and map.
 *
 * @package OliveEmber\Theme
 */

$olive_ember_phone = olive_ember_setting( 'phone' );
$olive_ember_email = olive_ember_setting( 'contact_email' );
?>
<!-- wp:group {"align":"full","className":"oe-page-hero oe-page-hero--compact","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-page-hero oe-page-hero--compact">
	<!-- wp:paragraph {"className":"oe-eyebrow"} -->
	<p class="oe-eyebrow"><?php esc_html_e( 'Contact', 'olive-ember' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"className":"oe-page-hero__title"} -->
	<h1 class="wp-block-heading oe-page-hero__title"><?php echo wp_kses( __( 'On the quay, <em>by the boats</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h1>
	<!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:html -->
<div class="oe-wide-photo alignwide">
	<?php echo olive_ember_image( 'room', __( 'The dining room and terrace of Olive & Ember, facing the Vieux-Port', 'olive-ember' ), array( 'eager' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
</div>
<!-- /wp:html -->
<!-- wp:columns {"align":"wide","className":"oe-contact"} -->
<div class="wp-block-columns alignwide oe-contact">
	<!-- wp:column {"width":"30%"} -->
	<div class="wp-block-column" style="flex-basis:30%">
		<!-- wp:html -->
		<h2 class="oe-label-title"><?php esc_html_e( 'Location', 'olive-ember' ); ?></h2>
		<address class="oe-address">
			<strong><?php echo esc_html( olive_ember_setting( 'restaurant_name', get_bloginfo( 'name' ) ) ); ?></strong><br>
			<?php echo esc_html( olive_ember_setting( 'street_address' ) ); ?><br>
			<?php echo esc_html( trim( olive_ember_setting( 'postal_code' ) . ' ' . olive_ember_setting( 'locality' ) ) ); ?>
		</address>
		<p class="oe-note"><?php esc_html_e( 'On the north side of the Vieux-Port, two minutes from the Vieux-Port metro station (line 1). Public car park at La Criée.', 'olive-ember' ); ?></p>
		<!-- /wp:html -->
	</div>
	<!-- /wp:column -->
	<!-- wp:column {"width":"30%"} -->
	<div class="wp-block-column" style="flex-basis:30%">
		<!-- wp:html -->
		<h2 class="oe-label-title"><?php esc_html_e( 'Contact', 'olive-ember' ); ?></h2>
		<ul class="oe-contact-links">
			<li><span><?php esc_html_e( 'Phone', 'olive-ember' ); ?></span> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $olive_ember_phone ) ); ?>"><?php echo esc_html( $olive_ember_phone ); ?></a></li>
			<li><span><?php esc_html_e( 'Email', 'olive-ember' ); ?></span> <a href="mailto:<?php echo esc_attr( $olive_ember_email ); ?>"><?php echo esc_html( $olive_ember_email ); ?></a></li>
		</ul>
		<p class="oe-note"><?php esc_html_e( 'For larger groups or private dining, please call us.', 'olive-ember' ); ?></p>
		<!-- /wp:html -->
	</div>
	<!-- /wp:column -->
	<!-- wp:column {"width":"40%"} -->
	<div class="wp-block-column" style="flex-basis:40%">
		<!-- wp:html -->
		<h2 class="oe-label-title"><?php esc_html_e( 'Opening hours', 'olive-ember' ); ?></h2>
		<!-- /wp:html -->
		<!-- wp:oe/opening-hours {"variant":"table"} /-->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
<!-- wp:oe/restaurant-map {"align":"wide"} /-->
