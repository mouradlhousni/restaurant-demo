<?php
/**
 * "Menu Section" block markup.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Blocks;

use OliveEmber\Reservations\Menu\MenuRepository;
use OliveEmber\Reservations\Menu\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Dishes of one section (or the Chef's picks), plus the optional filter bar.
 *
 * Every dish carries its allergens and diets in data attributes, so filtering is pure
 * client-side JavaScript: it works the same on the static export.
 */
final class MenuSectionView {

	/** Short codes displayed on diet pills. */
	private const DIET_CODES = array(
		'vegetarian'  => 'V',
		'vegan'       => 'VG',
		'gluten-free' => 'GF',
		'dairy-free'  => 'DF',
	);

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$chefs   = ! empty( $attributes['chefsPicks'] );
		$layout  = 'grid' === ( $attributes['layout'] ?? 'list' ) ? 'grid' : 'list';
		$images  = ! isset( $attributes['showImages'] ) || $attributes['showImages'];
		$filters = ! isset( $attributes['showFilters'] ) || $attributes['showFilters'];
		$title   = ! isset( $attributes['showTitle'] ) || $attributes['showTitle'];
		$section = $chefs ? null : MenuRepository::section( (string) ( $attributes['section'] ?? '' ) );

		if ( ! $chefs && ! $section ) {
			return self::placeholder( __( 'Choose a menu section in the block settings.', 'oe-reservations' ) );
		}

		$items = MenuRepository::items(
			array(
				'section'    => $section ? $section['id'] : 0,
				'chefs_pick' => $chefs,
			)
		);
		if ( ! $items ) {
			return self::placeholder( __( 'No dishes yet in this section.', 'oe-reservations' ) );
		}

		$heading_id = 'oe-section-' . ( $section ? $section['slug'] : 'chefs-picks' );
		$wrapper    = get_block_wrapper_attributes(
			array(
				'class'           => 'oe-menu oe-menu--' . $layout,
				'aria-labelledby' => $title ? $heading_id : null,
				'aria-label'      => $title ? null : ( $section ? $section['name'] : __( "Chef's picks", 'oe-reservations' ) ),
				'id'              => empty( $attributes['anchor'] ) && $section ? $section['slug'] : null,
			)
		);

		ob_start();
		if ( $filters ) {
			echo self::filters( (string) ( $attributes['align'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in filters().
		}
		?>
		<section <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
			<?php if ( $title ) : ?>
				<header class="oe-menu__head">
					<h2 class="oe-menu__title" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $section ? $section['name'] : __( "Chef's picks", 'oe-reservations' ) ); ?></h2>
					<?php if ( $section && $section['description'] ) : ?>
						<p class="oe-menu__description"><?php echo esc_html( $section['description'] ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>
			<ul class="oe-menu__items" role="list">
				<?php
				foreach ( $items as $item ) {
					echo self::item( $item, $layout, $images ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in item().
				}
				?>
			</ul>
			<p class="oe-menu__empty" hidden><?php esc_html_e( 'No dish in this section matches your filters.', 'oe-reservations' ); ?></p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * One dish.
	 *
	 * @param array<string, mixed> $item   Dish.
	 * @param string               $layout list | grid.
	 * @param bool                 $images Show photos.
	 * @return string
	 */
	private static function item( array $item, string $layout, bool $images ): string {
		$image = '';
		if ( $images && $item['image_id'] ) {
			$image = wp_get_attachment_image(
				$item['image_id'],
				'grid' === $layout ? 'medium' : 'thumbnail',
				false,
				array(
					'class'    => 'oe-dish__photo',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => 'grid' === $layout ? '10rem' : '6rem',
				)
			);
		}

		$diets = '';
		foreach ( $item['diets'] as $slug => $name ) {
			$diets .= sprintf(
				'<li class="oe-diet oe-diet--%1$s"><abbr title="%2$s">%3$s</abbr><span class="screen-reader-text">%2$s</span></li>',
				esc_attr( $slug ),
				esc_attr( $name ),
				esc_html( self::DIET_CODES[ $slug ] ?? strtoupper( substr( $name, 0, 2 ) ) )
			);
		}

		$allergens = $item['allergens']
			/* translators: %s: comma-separated allergens. */
			? '<p class="oe-dish__allergens">' . esc_html( sprintf( __( 'Contains: %s', 'oe-reservations' ), strtolower( implode( ', ', $item['allergens'] ) ) ) ) . '</p>'
			: '<p class="oe-dish__allergens">' . esc_html__( 'No listed allergens', 'oe-reservations' ) . '</p>';

		return sprintf(
			'<li class="oe-dish%1$s" data-allergens="%2$s" data-diets="%3$s">
				%4$s
				<div class="oe-dish__body">
					<p class="oe-dish__line"><span class="oe-dish__name">%5$s</span><span class="oe-dish__leader" aria-hidden="true"></span><span class="oe-dish__price">%6$s</span></p>
					%7$s
					<p class="oe-dish__description">%8$s</p>
					<div class="oe-dish__meta">%9$s%10$s</div>
				</div>
			</li>',
			$item['chefs_pick'] ? ' is-chefs-pick' : '',
			esc_attr( implode( ' ', array_keys( $item['allergens'] ) ) ),
			esc_attr( implode( ' ', array_keys( $item['diets'] ) ) ),
			$image ? '<div class="oe-dish__media">' . $image . '</div>' : '',
			esc_html( $item['name'] ),
			esc_html( $item['price_html'] ),
			$item['chefs_pick'] ? '<p class="oe-dish__badge">' . esc_html__( "Chef's pick", 'oe-reservations' ) . '</p>' : '',
			esc_html( $item['description'] ),
			$diets ? '<ul class="oe-dish__diets" role="list">' . $diets . '</ul>' : '',
			$allergens
		);
	}

	/**
	 * Filter bar, applied to every menu section on the page.
	 *
	 * @param string $align Block alignment (wide | full | ''), so the bar is as wide as the dishes.
	 * @return string
	 */
	private static function filters( string $align ): string {
		$allergens = MenuRepository::filter_terms( PostTypes::ALLERGEN );
		$diets     = MenuRepository::filter_terms( PostTypes::DIET );

		$checkboxes = static function ( array $terms, string $name ): string {
			$html = '';
			foreach ( $terms as $slug => $label ) {
				$html .= sprintf(
					'<label class="oe-chip"><input type="checkbox" name="%1$s" value="%2$s"><span>%3$s</span></label>',
					esc_attr( $name ),
					esc_attr( $slug ),
					esc_html( $label )
				);
			}

			return $html;
		};

		ob_start();
		?>
		<form class="oe-menu-filters<?php echo $align ? ' align' . esc_attr( $align ) : ''; ?>" data-oe-menu-filters data-count-format="<?php /* translators: 1: dishes shown, 2: total dishes. */ esc_attr_e( '%1$d of %2$d dishes shown', 'oe-reservations' ); ?>" aria-label="<?php esc_attr_e( 'Filter the menu', 'oe-reservations' ); ?>">
			<fieldset class="oe-menu-filters__group">
				<legend><?php esc_html_e( 'Show only', 'oe-reservations' ); ?></legend>
				<div class="oe-chips"><?php echo $checkboxes( $diets, 'diet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></div>
			</fieldset>
			<fieldset class="oe-menu-filters__group">
				<legend><?php esc_html_e( 'Hide dishes containing', 'oe-reservations' ); ?></legend>
				<div class="oe-chips"><?php echo $checkboxes( $allergens, 'allergen' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></div>
			</fieldset>
			<div class="oe-menu-filters__status">
				<p class="oe-menu-filters__count" role="status" aria-live="polite"></p>
				<button type="reset" class="oe-button oe-button--ghost oe-button--small"><?php esc_html_e( 'Clear filters', 'oe-reservations' ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Notice shown when there is nothing to display (mainly in the editor).
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function placeholder( string $message ): string {
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'oe-menu oe-menu--empty' ) ) . '><p>' . esc_html( $message ) . '</p></div>';
	}
}
