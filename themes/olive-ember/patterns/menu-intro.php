<?php
/**
 * Title: Menu introduction
 * Slug: olive-ember/menu-intro
 * Categories: olive-ember
 * Description: Menu page header: title, introduction, allergen note, section shortcuts and a plate.
 *
 * @package OliveEmber\Theme
 */

$olive_ember_sections = function_exists( 'oe_menu_sections' ) ? oe_menu_sections() : array();
$olive_ember_plate    = olive_ember_image(
	'menu-plate',
	'',
	array(
		'class' => 'oe-stage__plate',
		'eager' => true,
	)
);
?>
<!-- wp:group {"align":"full","className":"oe-page-hero","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-page-hero">
	<!-- wp:columns {"verticalAlignment":"center","className":"oe-page-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center oe-page-hero__grid">
		<!-- wp:column {"verticalAlignment":"center","width":"62%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:62%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'Menu', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"oe-page-hero__title"} -->
			<h1 class="wp-block-heading oe-page-hero__title"><?php echo wp_kses( __( 'A short menu, <em>rewritten with the seasons</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"oe-lead"} -->
			<p class="oe-lead"><?php esc_html_e( 'Cooked on one wood fire. Everything is made here, from the bread to the ice cream.', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"oe-note"} -->
			<p class="oe-note"><?php esc_html_e( 'Use the filters to hide dishes containing an allergen or to show a diet only. Allergen information is indicative: please tell us about any allergy when you book.', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<?php if ( $olive_ember_sections ) : ?>
			<!-- wp:html -->
			<nav class="oe-pills" aria-label="<?php esc_attr_e( 'Menu sections', 'olive-ember' ); ?>">
				<ul role="list">
					<?php foreach ( $olive_ember_sections as $olive_ember_section ) : ?>
					<li><a href="#<?php echo esc_attr( $olive_ember_section['slug'] ); ?>"><?php echo esc_html( $olive_ember_section['name'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<!-- /wp:html -->
			<?php endif; ?>
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"38%","className":"oe-page-hero__art"} -->
		<div class="wp-block-column is-vertically-aligned-center oe-page-hero__art" style="flex-basis:38%">
			<!-- wp:html -->
			<div class="oe-stage oe-stage--small">
				<img class="oe-stage__ring" src="<?php echo esc_url( olive_ember_deco( 'ring' ) ); ?>" alt="" width="200" height="200">
				<?php echo $olive_ember_plate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
				<img class="oe-deco oe-deco--sprig" src="<?php echo esc_url( olive_ember_deco( 'sprig' ) ); ?>" alt="" width="80" height="80">
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
