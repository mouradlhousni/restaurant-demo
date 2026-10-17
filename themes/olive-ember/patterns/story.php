<?php
/**
 * Title: The kitchen (chef's story)
 * Slug: olive-ember/story
 * Categories: olive-ember
 * Description: Editorial block: the chef's story and quote, two offset photos, three key figures.
 *
 * @package OliveEmber\Theme
 */

?>
<!-- wp:group {"align":"full","className":"oe-story","backgroundColor":"surface","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-story has-surface-background-color has-background" id="story">
	<!-- wp:columns {"className":"oe-story__grid"} -->
	<div class="wp-block-columns oe-story__grid">
		<!-- wp:column {"width":"50%"} -->
		<div class="wp-block-column" style="flex-basis:50%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'The kitchen', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php echo wp_kses( __( 'One fire, from lunch <em>to last orders</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Chef Inès Haddad grew up between Marseille and Tunis, cooking on the family barbecue long before she cooked in restaurants. When she opened Olive & Ember on the old port, she built the kitchen around a single wood-fired grill and oven, and kept the menu short enough to cook everything on it.', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:quote {"className":"oe-pullquote"} -->
			<blockquote class="wp-block-quote oe-pullquote">
				<!-- wp:paragraph -->
				<p><?php esc_html_e( 'Vine cuttings burn hot and fast. They give fish a smoke you simply cannot get from gas.', 'olive-ember' ); ?></p>
				<!-- /wp:paragraph -->
				<cite><?php esc_html_e( 'Inès Haddad, chef', 'olive-ember' ); ?></cite>
			</blockquote>
			<!-- /wp:quote -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"width":"50%","className":"oe-story__photos"} -->
		<div class="wp-block-column oe-story__photos" style="flex-basis:50%">
			<!-- wp:html -->
			<div class="oe-offset-photos">
				<?php echo olive_ember_image( 'chef', __( 'Chef Inès Haddad at the wood-fired grill', 'olive-ember' ), array( 'class' => 'oe-offset-photos__a' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
				<?php echo olive_ember_image( 'fire', __( 'Vine cuttings burning in the grill', 'olive-ember' ), array( 'class' => 'oe-offset-photos__b' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<!-- wp:list {"className":"oe-facts"} -->
	<ul class="wp-block-list oe-facts">
		<!-- wp:list-item -->
		<li><strong>10:00</strong> <?php esc_html_e( 'the fire is lit', 'olive-ember' ); ?></li>
		<!-- /wp:list-item -->
		<!-- wp:list-item -->
		<li><strong>100 m</strong> <?php esc_html_e( 'to the fish market', 'olive-ember' ); ?></li>
		<!-- /wp:list-item -->
		<!-- wp:list-item -->
		<li><strong><?php echo esc_html( olive_ember_setting( 'capacity', '40' ) ); ?></strong> <?php esc_html_e( 'seats, terrace included', 'olive-ember' ); ?></li>
		<!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->
</div>
<!-- /wp:group -->
