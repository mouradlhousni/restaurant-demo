<?php
/**
 * Title: Produce and suppliers
 * Slug: olive-ember/suppliers
 * Categories: olive-ember
 * Description: One large photo and where the kitchen buys its produce.
 *
 * @package OliveEmber\Theme
 */

$olive_ember_suppliers = array(
	array( __( 'Fish', 'olive-ember' ), __( 'Morning market, Quai des Belges', 'olive-ember' ) ),
	array( __( 'Vegetables', 'olive-ember' ), __( 'Two family farms in the Luberon', 'olive-ember' ) ),
	array( __( 'Olive oil', 'olive-ember' ), __( 'A growers’ cooperative near Aix', 'olive-ember' ) ),
	array( __( 'Lamb', 'olive-ember' ), __( 'Sisteron, Alpes-de-Haute-Provence', 'olive-ember' ) ),
	array( __( 'Wine', 'olive-ember' ), __( 'Small natural producers, Bandol and Cassis', 'olive-ember' ) ),
);
?>
<!-- wp:group {"align":"full","className":"oe-suppliers","layout":{"type":"constrained","contentSize":"76rem"}} -->
<div class="wp-block-group alignfull oe-suppliers">
	<!-- wp:columns {"verticalAlignment":"center","className":"oe-suppliers__grid"} -->
	<div class="wp-block-columns are-vertically-aligned-center oe-suppliers__grid">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:html -->
			<?php echo olive_ember_image( 'market', __( 'Fishermen selling the morning catch on the Vieux-Port', 'olive-ember' ), array( 'class' => 'oe-suppliers__photo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in olive_ember_image(). ?>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"center","width":"45%","className":"oe-suppliers__text"} -->
		<div class="wp-block-column is-vertically-aligned-center oe-suppliers__text" style="flex-basis:45%">
			<!-- wp:paragraph {"className":"oe-eyebrow"} -->
			<p class="oe-eyebrow"><?php esc_html_e( 'Seasonality', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"oe-suppliers__lead"} -->
			<p class="oe-suppliers__lead"><?php esc_html_e( 'The fish comes from the morning market, the vegetables from two farms in the Luberon, the olive oil from a cooperative near Aix. The menu changes when they do.', 'olive-ember' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:html -->
			<ul class="oe-people" role="list">
				<?php foreach ( $olive_ember_suppliers as $olive_ember_supplier ) : ?>
				<li><span class="oe-people__name"><?php echo esc_html( $olive_ember_supplier[0] ); ?></span> <span class="oe-people__what"><?php echo esc_html( $olive_ember_supplier[1] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<!-- /wp:html -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
