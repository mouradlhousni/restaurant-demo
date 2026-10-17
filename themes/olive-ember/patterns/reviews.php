<?php
/**
 * Title: Guest book
 * Slug: olive-ember/reviews
 * Categories: olive-ember
 * Description: The chef in a saffron circle, and guest reviews one at a time (scroll-snap carousel).
 *
 * @package OliveEmber\Theme
 */

$olive_ember_reviews = array(
	array( __( 'The sea bass came off the grill with skin like glass. We booked again for the next evening before we had even paid the bill.', 'olive-ember' ), __( 'Claire & Tom, Lyon', 'olive-ember' ) ),
	array( __( 'A proper Marseille bouillabaisse without the tourist theatre. The rouille alone is worth the walk down the quay.', 'olive-ember' ), __( 'Karim B., Marseille', 'olive-ember' ) ),
	array( __( 'I mentioned my daughter’s nut allergy when I booked, and the waiter already knew when we sat down. That is rare.', 'olive-ember' ), __( 'Hannah S., London', 'olive-ember' ) ),
	array( __( 'Twelve-hour lamb, a glass of Bandol and the boats coming in. We picked our time online in a minute and the table was ready.', 'olive-ember' ), __( 'Marco & Giulia, Turin', 'olive-ember' ) ),
);
?>
<!-- wp:group {"align":"full","className":"oe-reviews","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-reviews">
	<!-- wp:columns {"verticalAlignment":"center","className":"oe-reviews__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center oe-reviews__grid">
		<!-- wp:column {"verticalAlignment":"center","width":"42%","className":"oe-reviews__art"} -->
		<div class="wp-block-column is-vertically-aligned-center oe-reviews__art" style="flex-basis:42%">
			<!-- wp:html -->
			<div class="oe-portrait">
				<?php echo olive_ember_image( 'chef-cutout', __( 'Chef Inès Haddad holding a plate', 'olive-ember' ), array( 'class' => 'oe-portrait__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
				<img class="oe-deco oe-deco--lemon" src="<?php echo esc_url( olive_ember_deco( 'lemon' ) ); ?>" alt="" width="80" height="80" loading="lazy">
				<p class="oe-portrait__note"><strong>4.9</strong> <span aria-hidden="true">★★★★★</span><span class="screen-reader-text"><?php esc_html_e( 'out of 5', 'olive-ember' ); ?></span><br><?php esc_html_e( 'from our guests', 'olive-ember' ); ?></p>
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"58%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'Guest book', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php echo wp_kses( __( 'What our guests <em>tell us</em>', 'olive-ember' ), array( 'em' => array() ) ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:html -->
			<div class="oe-carousel" data-oe-carousel>
				<ul class="oe-carousel__track" role="list" tabindex="0" aria-label="<?php esc_attr_e( 'Guest reviews', 'olive-ember' ); ?>">
					<?php foreach ( $olive_ember_reviews as $olive_ember_review ) : ?>
					<li class="oe-carousel__slide">
						<figure class="oe-review">
							<blockquote><p><?php echo esc_html( $olive_ember_review[0] ); ?></p></blockquote>
							<figcaption><span class="oe-review__stars" aria-hidden="true">★★★★★</span> <?php echo esc_html( $olive_ember_review[1] ); ?></figcaption>
						</figure>
					</li>
					<?php endforeach; ?>
				</ul>
				<div class="oe-carousel__nav" hidden>
					<button type="button" class="oe-round-button" data-dir="-1" aria-label="<?php esc_attr_e( 'Previous review', 'olive-ember' ); ?>"><span aria-hidden="true">←</span></button>
					<button type="button" class="oe-round-button is-primary" data-dir="1" aria-label="<?php esc_attr_e( 'Next review', 'olive-ember' ); ?>"><span aria-hidden="true">→</span></button>
				</div>
			</div>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
