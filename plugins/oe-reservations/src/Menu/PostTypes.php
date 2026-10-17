<?php
/**
 * Menu content model.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `menu_item` post type, its taxonomies and typed meta.
 *
 * - Name        → post title
 * - Description → post excerpt
 * - Photo       → featured image
 * - Price, "Chef's pick" → post meta
 * - Section, allergens, diets → taxonomies
 *
 * Dishes have no single page: the menu is only displayed through the "Menu Section" block.
 */
final class PostTypes {

	public const POST_TYPE = 'menu_item';
	public const SECTION   = 'menu_section';
	public const ALLERGEN  = 'menu_allergen';
	public const DIET      = 'menu_diet';

	public const META_PRICE      = 'oe_price';
	public const META_CHEFS_PICK = 'oe_chefs_pick';

	/** Term meta holding the display order of a section. */
	public const META_SECTION_ORDER = 'oe_order';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Registers post type, taxonomies and meta.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Menu', 'oe-reservations' ),
					'singular_name'      => __( 'Dish', 'oe-reservations' ),
					'menu_name'          => __( 'Menu', 'oe-reservations' ),
					'all_items'          => __( 'All dishes', 'oe-reservations' ),
					'add_new'            => __( 'Add dish', 'oe-reservations' ),
					'add_new_item'       => __( 'Add new dish', 'oe-reservations' ),
					'edit_item'          => __( 'Edit dish', 'oe-reservations' ),
					'new_item'           => __( 'New dish', 'oe-reservations' ),
					'search_items'       => __( 'Search dishes', 'oe-reservations' ),
					'not_found'          => __( 'No dishes found.', 'oe-reservations' ),
					'not_found_in_trash' => __( 'No dishes found in Trash.', 'oe-reservations' ),
					'featured_image'     => __( 'Dish photo', 'oe-reservations' ),
					'set_featured_image' => __( 'Set dish photo', 'oe-reservations' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-carrot',
				'menu_position'       => 25,
				'supports'            => array( 'title', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'template'            => array(),
			)
		);

		$taxonomies = array(
			self::SECTION  => array(
				'labels'       => array(
					'name'          => __( 'Menu sections', 'oe-reservations' ),
					'singular_name' => __( 'Menu section', 'oe-reservations' ),
					'menu_name'     => __( 'Sections', 'oe-reservations' ),
					'all_items'     => __( 'All sections', 'oe-reservations' ),
					'edit_item'     => __( 'Edit section', 'oe-reservations' ),
					'add_new_item'  => __( 'Add new section', 'oe-reservations' ),
					'not_found'     => __( 'No sections found.', 'oe-reservations' ),
				),
				'hierarchical' => true,
			),
			self::ALLERGEN => array(
				'labels'       => array(
					'name'          => __( 'Allergens', 'oe-reservations' ),
					'singular_name' => __( 'Allergen', 'oe-reservations' ),
					'all_items'     => __( 'All allergens', 'oe-reservations' ),
					'edit_item'     => __( 'Edit allergen', 'oe-reservations' ),
					'add_new_item'  => __( 'Add new allergen', 'oe-reservations' ),
					'not_found'     => __( 'No allergens found.', 'oe-reservations' ),
				),
				'hierarchical' => true,
			),
			self::DIET     => array(
				'labels'       => array(
					'name'          => __( 'Diets', 'oe-reservations' ),
					'singular_name' => __( 'Diet', 'oe-reservations' ),
					'all_items'     => __( 'All diets', 'oe-reservations' ),
					'edit_item'     => __( 'Edit diet', 'oe-reservations' ),
					'add_new_item'  => __( 'Add new diet', 'oe-reservations' ),
					'not_found'     => __( 'No diets found.', 'oe-reservations' ),
				),
				'hierarchical' => true,
			),
		);

		foreach ( $taxonomies as $taxonomy => $args ) {
			register_taxonomy(
				$taxonomy,
				self::POST_TYPE,
				$args + array(
					'public'            => false,
					'show_ui'           => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => false,
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			self::META_PRICE,
			array(
				'type'              => 'number',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize_price' ),
				'auth_callback'     => array( self::class, 'can_edit' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_CHEFS_PICK,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( self::class, 'can_edit' ),
			)
		);

		register_term_meta(
			self::SECTION,
			self::META_SECTION_ORDER,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);
	}

	/**
	 * Sanitizes a price: positive, two decimals.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_price( $value ): float {
		$value = is_string( $value ) ? str_replace( ',', '.', $value ) : $value;

		return round( max( 0.0, (float) $value ), 2 );
	}

	/**
	 * Meta auth callback.
	 *
	 * @param bool   $allowed  Whether allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ): bool {
		return current_user_can( 'edit_post', (int) $post_id );
	}
}
