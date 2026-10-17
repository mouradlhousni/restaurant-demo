<?php
/**
 * Title: Footer
 * Slug: olive-ember/footer
 * Inserter: false
 *
 * @package OliveEmber\Theme
 */

$olive_ember_phone = olive_ember_setting( 'phone' );
$olive_ember_email = olive_ember_setting( 'contact_email' );
?>
<!-- wp:group {"align":"full","className":"oe-footer","backgroundColor":"tapenade","textColor":"limestone","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-footer has-limestone-color has-tapenade-background-color has-text-color has-background">
	<!-- wp:group {"className":"oe-footer__top","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group oe-footer__top">
		<!-- wp:pattern {"slug":"olive-ember/wordmark"} /-->
		<!-- wp:paragraph {"className":"oe-footer__tagline"} -->
		<p class="oe-footer__tagline"><?php esc_html_e( 'Mediterranean kitchen and wood-fired grill on the Vieux-Port.', 'olive-ember' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:columns {"className":"oe-footer__cols"} -->
	<div class="wp-block-columns oe-footer__cols">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:html -->
			<h2 class="oe-footer__label"><?php esc_html_e( 'Visit', 'olive-ember' ); ?></h2>
			<p><?php echo esc_html( olive_ember_setting( 'street_address' ) ); ?><br><?php echo esc_html( trim( olive_ember_setting( 'postal_code' ) . ' ' . olive_ember_setting( 'locality' ) ) ); ?></p>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"width":"34%"} -->
		<div class="wp-block-column" style="flex-basis:34%">
			<!-- wp:html -->
			<h2 class="oe-footer__label"><?php esc_html_e( 'Hours', 'olive-ember' ); ?></h2>
			<!-- /wp:html -->
			<!-- wp:oe/opening-hours {"variant":"compact","showBadge":false} /-->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:html -->
			<h2 class="oe-footer__label"><?php esc_html_e( 'Contact', 'olive-ember' ); ?></h2>
			<p>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $olive_ember_phone ) ); ?>"><?php echo esc_html( $olive_ember_phone ); ?></a>
				<?php if ( $olive_ember_email ) : ?>
					<br><a href="mailto:<?php echo esc_attr( $olive_ember_email ); ?>"><?php echo esc_html( $olive_ember_email ); ?></a>
				<?php endif; ?>
			</p>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:html -->
			<h2 class="oe-footer__label"><?php esc_html_e( 'Explore', 'olive-ember' ); ?></h2>
			<ul class="oe-footer__links" role="list">
				<li><a href="<?php echo esc_url( olive_ember_page_url( 'menu' ) ); ?>"><?php esc_html_e( 'Menu', 'olive-ember' ); ?></a></li>
				<li><a href="<?php echo esc_url( olive_ember_booking_url() ); ?>"><?php esc_html_e( 'Book a table', 'olive-ember' ); ?></a></li>
				<li><a href="<?php echo esc_url( olive_ember_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact', 'olive-ember' ); ?></a></li>
			</ul>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- wp:paragraph {"className":"oe-footer__small"} -->
	<p class="oe-footer__small">
		<?php
		/* translators: %s: year. */
		echo esc_html( sprintf( __( '© %s Olive & Ember. A fictional restaurant: this site is a portfolio project.', 'olive-ember' ), gmdate( 'Y' ) ) );
		?>
	</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
