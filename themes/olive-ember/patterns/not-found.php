<?php
/**
 * Title: Page not found
 * Slug: olive-ember/not-found
 * Inserter: false
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:group {"align":"full","className":"oe-not-found","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-not-found">
	<!-- wp:columns {"verticalAlignment":"center"} -->
	<div class="wp-block-columns are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'Error 404', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"oe-page-hero__title"} -->
			<h1 class="wp-block-heading oe-page-hero__title"><?php echo wp_kses( __( 'This page has <em>gone cold</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"oe-lead"} -->
			<p class="oe-lead"><?php esc_html_e( 'The address may have changed. Start again from the menu, or book a table directly.', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( olive_ember_booking_url() ); ?>"><?php esc_html_e( 'Book a table', 'olive-ember' ); ?></a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"is-style-outline"} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( olive_ember_page_url( 'menu' ) ); ?>"><?php esc_html_e( 'See the menu', 'olive-ember' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
			<!-- wp:html -->
			<div class="oe-stage oe-stage--small">
				<img class="oe-stage__ring" src="<?php echo esc_url( olive_ember_deco( 'ring' ) ); ?>" alt="" width="200" height="200">
				<?php echo olive_ember_image( 'empty-plate', '', array( 'class' => 'oe-stage__plate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
