<?php
/**
 * Title: Header booking button
 * Slug: olive-ember/header-cta
 * Inserter: false
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:buttons {"className":"oe-header__cta"} -->
<div class="wp-block-buttons oe-header__cta">
	<!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( olive_ember_booking_url() ); ?>"><?php esc_html_e( 'Book a table', 'olive-ember' ); ?></a></div>
	<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
