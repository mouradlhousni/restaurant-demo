<?php
/**
 * Menu queries.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Read model of the menu: ordered sections and normalized dishes.
 *
 * Results are kept in the object cache, keyed on the posts/terms "last changed" stamps,
 * so any edit to a dish or a term invalidates them automatically.
 */
final class MenuRepository {

	private const CACHE_GROUP = 'oe_menu';

	/**
	 * Sections ordered by their "display order" term meta, then name.
	 *
	 * @param bool $hide_empty Hide sections without published dishes.
	 * @return array<int, array{id: int, slug: string, name: string, description: string, order: int, count: int}>
	 */
	public static function sections( bool $hide_empty = true ): array {
		$key    = 'sections:' . (int) $hide_empty . ':' . wp_cache_get_last_changed( 'terms' );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => PostTypes::SECTION,
				'hide_empty' => $hide_empty,
			)
		);

		$sections = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$sections[] = array(
				'id'          => (int) $term->term_id,
				'slug'        => $term->slug,
				'name'        => $term->name,
				'description' => $term->description,
				'order'       => (int) get_term_meta( $term->term_id, PostTypes::META_SECTION_ORDER, true ),
				'count'       => (int) $term->count,
			);
		}

		usort(
			$sections,
			static fn( array $a, array $b ): int => array( $a['order'], $a['name'] ) <=> array( $b['order'], $b['name'] )
		);

		wp_cache_set( $key, $sections, self::CACHE_GROUP );

		return $sections;
	}

	/**
	 * A section by ID or slug.
	 *
	 * @param int|string $section Term ID or slug.
	 * @return array<string, mixed>|null
	 */
	public static function section( $section ): ?array {
		foreach ( self::sections( false ) as $candidate ) {
			if ( ( is_numeric( $section ) && (int) $section === $candidate['id'] ) || $section === $candidate['slug'] ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Published dishes, ordered by menu order then title.
	 *
	 * @param array{section?: int, chefs_pick?: bool, limit?: int} $args Filters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function items( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'section'    => 0,
				'chefs_pick' => false,
				'limit'      => -1,
			)
		);

		$key    = 'items:' . md5( (string) wp_json_encode( $args ) ) . ':' . wp_cache_get_last_changed( 'posts' ) . ':' . wp_cache_get_last_changed( 'terms' );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = array(
			'post_type'              => PostTypes::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $args['limit'],
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
		);

		if ( $args['section'] ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Small, cached query.
			$query['tax_query'] = array(
				array(
					'taxonomy' => PostTypes::SECTION,
					'terms'    => (int) $args['section'],
				),
			);
		}

		if ( $args['chefs_pick'] ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, cached query.
			$query['meta_query'] = array(
				array(
					'key'   => PostTypes::META_CHEFS_PICK,
					'value' => '1',
				),
			);
		}

		$items = array_map( array( self::class, 'to_array' ), get_posts( $query ) );

		wp_cache_set( $key, $items, self::CACHE_GROUP );

		return $items;
	}

	/**
	 * Normalized dish.
	 *
	 * @param \WP_Post $post Dish.
	 * @return array<string, mixed>
	 */
	public static function to_array( \WP_Post $post ): array {
		$price = (float) get_post_meta( $post->ID, PostTypes::META_PRICE, true );

		return array(
			'id'          => $post->ID,
			'name'        => get_the_title( $post ),
			'description' => wp_strip_all_tags( $post->post_excerpt ),
			'price'       => $price,
			'price_html'  => oe_format_price( $price ),
			'chefs_pick'  => (bool) get_post_meta( $post->ID, PostTypes::META_CHEFS_PICK, true ),
			'image_id'    => (int) get_post_thumbnail_id( $post ),
			'section'     => self::term_names( $post->ID, PostTypes::SECTION ),
			'allergens'   => self::term_names( $post->ID, PostTypes::ALLERGEN ),
			'diets'       => self::term_names( $post->ID, PostTypes::DIET ),
		);
	}

	/**
	 * All terms of a filter taxonomy (allergens or diets), slug => name.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, string>
	 */
	public static function filter_terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);

		return is_array( $terms ) ? wp_list_pluck( $terms, 'name', 'slug' ) : array();
	}

	/**
	 * Terms of a dish, slug => name.
	 *
	 * @param int    $post_id  Dish ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, string>
	 */
	private static function term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );

		return is_array( $terms ) ? wp_list_pluck( $terms, 'name', 'slug' ) : array();
	}
}
