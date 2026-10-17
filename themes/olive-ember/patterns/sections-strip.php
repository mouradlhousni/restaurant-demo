<?php
/**
 * Title: Menu sections strip
 * Slug: olive-ember/sections-strip
 * Categories: olive-ember
 * Description: One round plate per menu section, linking to that section of the menu page.
 *
 * @package OliveEmber\Theme
 */

$olive_ember_sections = function_exists( 'oe_menu_sections' ) ? oe_menu_sections() : array();
$olive_ember_menu_url = olive_ember_page_url( 'menu' );
?>
<!-- wp:group {"align":"full","className":"oe-strip-sections","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-strip-sections">
	<!-- wp:html -->
	<nav class="oe-sections" aria-label="<?php esc_attr_e( 'Menu sections', 'olive-ember' ); ?>">
		<ul role="list">
			<?php foreach ( $olive_ember_sections as $olive_ember_section ) : ?>
			<li>
				<a href="<?php echo esc_url( $olive_ember_menu_url . '#' . $olive_ember_section['slug'] ); ?>">
					<?php echo olive_ember_image( 'section-' . $olive_ember_section['slug'], '', array( 'class' => 'oe-sections__plate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
					<span class="oe-sections__name"><?php echo esc_html( $olive_ember_section['name'] ); ?></span>
					<?php if ( $olive_ember_section['description'] ) : ?>
					<span class="oe-sections__note"><?php echo esc_html( $olive_ember_section['description'] ); ?></span>
					<?php endif; ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<!-- /wp:html -->
</div>
<!-- /wp:group -->
