<?php
/**
 * Title: Home hero
 * Slug: olive-ember/hero
 * Categories: olive-ember
 * Description: Headline, booking buttons, live opening badge and a cut-out plate in a dashed ring.
 *
 * @package OliveEmber\Theme
 */

// Floating card: the priciest Chef's pick, as a taste of the menu.
$olive_ember_dish = null;
if ( function_exists( 'oe_menu_items' ) ) {
	$olive_ember_picks = oe_menu_items( array( 'chefs_pick' => true ) );
	usort( $olive_ember_picks, static fn( array $a, array $b ): int => $b['price'] <=> $a['price'] );
	$olive_ember_dish = $olive_ember_picks[0] ?? null;
}
$olive_ember_plate = olive_ember_image(
	'hero-plate',
	__( 'A signature dish from the wood-fired grill, seen from above', 'olive-ember' ),
	array(
		'class' => 'oe-stage__plate',
		'eager' => true,
	)
);
?>
<!-- wp:group {"align":"full","className":"oe-hero","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-hero">
	<!-- wp:columns {"verticalAlignment":"center","className":"oe-hero__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center oe-hero__grid">
		<!-- wp:column {"verticalAlignment":"center","width":"52%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:52%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'Vieux-Port · Marseille', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":1,"className":"oe-hero__title"} -->
			<h1 class="wp-block-heading oe-hero__title"><?php echo wp_kses( __( 'Cooked over <em>vine embers</em>, served by the sea.', 'olive-ember' ), array( 'em' => array() ) ); ?></h1>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"oe-hero__lead"} -->
			<p class="oe-hero__lead"><?php esc_html_e( 'One wood fire, lit every morning at ten. Fish from the boats on the quay, lamb that cooks for twelve hours, bread from the oven and wines from the hills behind the city.', 'olive-ember' ); ?></p>
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
			<!-- wp:oe/opening-hours {"variant":"badge","className":"oe-hero__status"} /-->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"48%","className":"oe-hero__art"} -->
		<div class="wp-block-column is-vertically-aligned-center oe-hero__art" style="flex-basis:48%">
			<!-- wp:html -->
			<div class="oe-stage">
				<img class="oe-stage__ring" src="<?php echo esc_url( olive_ember_deco( 'ring' ) ); ?>" alt="" width="200" height="200">
				<?php echo $olive_ember_plate; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
				<img class="oe-deco oe-deco--sprig" src="<?php echo esc_url( olive_ember_deco( 'sprig' ) ); ?>" alt="" width="80" height="80">
				<img class="oe-deco oe-deco--lemon" src="<?php echo esc_url( olive_ember_deco( 'lemon' ) ); ?>" alt="" width="80" height="80">
				<img class="oe-deco oe-deco--chilli" src="<?php echo esc_url( olive_ember_deco( 'chilli' ) ); ?>" alt="" width="80" height="80">
				<p class="oe-stage__tag"><?php esc_html_e( 'From the fire', 'olive-ember' ); ?></p>
				<?php if ( $olive_ember_dish ) : ?>
				<a class="oe-float-card" href="<?php echo esc_url( olive_ember_page_url( 'menu' ) ); ?>">
					<?php
					echo $olive_ember_dish['image_id']
						? wp_get_attachment_image(
							$olive_ember_dish['image_id'],
							'thumbnail',
							false,
							array(
								'class' => 'oe-float-card__img',
								'alt'   => '',
							)
						)
						: '<span class="oe-float-card__img oe-placeholder" aria-hidden="true"></span>';
					?>
					<span class="oe-float-card__body">
						<span class="oe-float-card__name"><?php echo esc_html( $olive_ember_dish['name'] ); ?></span>
						<span class="oe-float-card__desc"><?php echo esc_html( strtok( $olive_ember_dish['description'], ',.' ) ); ?></span>
						<span class="oe-float-card__price"><?php echo esc_html( $olive_ember_dish['price_html'] ); ?></span>
					</span>
				</a>
				<?php endif; ?>
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
