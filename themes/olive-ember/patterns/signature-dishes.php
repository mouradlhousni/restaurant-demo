<?php
/**
 * Title: Signature dishes
 * Slug: olive-ember/signature-dishes
 * Categories: olive-ember
 * Description: The Chef's picks, from the menu.
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:group {"align":"full","className":"oe-signature","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-signature">
	<!-- wp:group {"className":"oe-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group oe-section-head">
		<!-- wp:group {"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( "Chef's picks", 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php echo wp_kses( __( 'From the fire, <em>this season</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:group -->
		<!-- wp:paragraph {"className":"oe-link-arrow"} -->
		<p class="oe-link-arrow"><a href="<?php echo esc_url( olive_ember_page_url( 'menu' ) ); ?>"><?php esc_html_e( 'See the full menu', 'olive-ember' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:oe/menu-section {"chefsPicks":true,"showFilters":false,"layout":"grid","showTitle":false,"align":"wide"} /-->
</div>
<!-- /wp:group -->
