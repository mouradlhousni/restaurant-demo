<?php
/**
 * Menu admin screens.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * "Dish details" meta box, list table columns and filters, section ordering.
 */
final class Admin {

	private const NONCE_ACTION = 'oe_save_dish';
	private const NONCE_FIELD  = 'oe_dish_nonce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . PostTypes::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'save' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );

		// Dishes list.
		add_filter( 'manage_' . PostTypes::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostTypes::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . PostTypes::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_and_filter' ) );
		add_action( 'restrict_manage_posts', array( $this, 'section_filter' ) );

		// Section order.
		add_action( PostTypes::SECTION . '_add_form_fields', array( $this, 'order_field_add' ) );
		add_action( PostTypes::SECTION . '_edit_form_fields', array( $this, 'order_field_edit' ) );
		add_action( 'created_' . PostTypes::SECTION, array( $this, 'save_order' ) );
		add_action( 'edited_' . PostTypes::SECTION, array( $this, 'save_order' ) );
		add_filter( 'manage_edit-' . PostTypes::SECTION . '_columns', array( $this, 'section_columns' ) );
		add_filter( 'manage_' . PostTypes::SECTION . '_custom_column', array( $this, 'section_column' ), 10, 3 );
	}

	/**
	 * Registers the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box( 'oe-dish-details', __( 'Dish details', 'oe-reservations' ), array( $this, 'render_meta_box' ), PostTypes::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Meta box markup.
	 *
	 * @param \WP_Post $post Dish.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$price      = (float) get_post_meta( $post->ID, PostTypes::META_PRICE, true );
		$chefs_pick = (bool) get_post_meta( $post->ID, PostTypes::META_CHEFS_PICK, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label for="oe-price"><strong><?php esc_html_e( 'Price', 'oe-reservations' ); ?></strong> (<?php echo esc_html( oe_currency_symbol() ); ?>)</label><br>
			<input type="number" id="oe-price" name="oe_price" value="<?php echo esc_attr( $price > 0 ? $price : '' ); ?>" min="0" step="0.5" inputmode="decimal" class="widefat" required>
		</p>
		<p>
			<label>
				<input type="checkbox" name="oe_chefs_pick" value="1" <?php checked( $chefs_pick ); ?>>
				<?php esc_html_e( "Chef's pick (badge, featured on the home page)", 'oe-reservations' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Description: use the excerpt. Photo: use the featured image.', 'oe-reservations' ); ?></p>
		<?php
	}

	/**
	 * Saves the meta box.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['oe_price'] ) ? PostTypes::sanitize_price( sanitize_text_field( wp_unslash( $_POST['oe_price'] ) ) ) : 0.0;

		// Demo mode: the fields are validated above, but nothing is written.
		if ( oe_is_demo_mode() ) {
			return;
		}

		update_post_meta( $post_id, PostTypes::META_PRICE, $price );
		update_post_meta( $post_id, PostTypes::META_CHEFS_PICK, ! empty( $_POST['oe_chefs_pick'] ) );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function title_placeholder( $text, $post ): string {
		return PostTypes::POST_TYPE === $post->post_type ? __( 'Dish name', 'oe-reservations' ) : $text;
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$result['oe_photo'] = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'oe-reservations' ) . '</span>';
			}
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result['oe_price']      = __( 'Price', 'oe-reservations' );
				$result['oe_chefs_pick'] = __( "Chef's pick", 'oe-reservations' );
			}
		}
		unset( $result['date'] );

		return $result;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'oe_photo':
				echo get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'style' => 'width:48px;height:48px;object-fit:cover;border-radius:4px' ) );
				break;
			case 'oe_price':
				echo esc_html( oe_format_price( (float) get_post_meta( $post_id, PostTypes::META_PRICE, true ) ) );
				break;
			case 'oe_chefs_pick':
				if ( get_post_meta( $post_id, PostTypes::META_CHEFS_PICK, true ) ) {
					echo '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'oe-reservations' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['oe_price'] = 'oe_price';

		return $columns;
	}

	/**
	 * Price sorting and default ordering of the dishes list.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function sort_and_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || PostTypes::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'oe_price' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', PostTypes::META_PRICE ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( ! $query->get( 'orderby' ) ) {
			$query->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				)
			);
		}
	}

	/**
	 * Section dropdown above the dishes list.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function section_filter( string $post_type ): void {
		if ( PostTypes::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$current = isset( $_GET[ PostTypes::SECTION ] ) ? sanitize_title( wp_unslash( $_GET[ PostTypes::SECTION ] ) ) : '';

		echo '<label class="screen-reader-text" for="oe-section-filter">' . esc_html__( 'Filter by section', 'oe-reservations' ) . '</label>';
		echo '<select name="' . esc_attr( PostTypes::SECTION ) . '" id="oe-section-filter">';
		echo '<option value="">' . esc_html__( 'All sections', 'oe-reservations' ) . '</option>';
		foreach ( MenuRepository::sections( false ) as $section ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $section['slug'] ), selected( $current, $section['slug'], false ), esc_html( $section['name'] ) );
		}
		echo '</select>';
	}

	/**
	 * "Display order" field on the "Add section" form.
	 *
	 * @return void
	 */
	public function order_field_add(): void {
		wp_nonce_field( 'oe_section_order', 'oe_section_order_nonce' );
		?>
		<div class="form-field">
			<label for="oe-order"><?php esc_html_e( 'Display order', 'oe-reservations' ); ?></label>
			<input type="number" name="oe_order" id="oe-order" value="0" min="0" step="1">
			<p><?php esc_html_e( 'Sections are displayed in ascending order (Starters 10, Mains 20…).', 'oe-reservations' ); ?></p>
		</div>
		<?php
	}

	/**
	 * "Display order" field on the "Edit section" form.
	 *
	 * @param \WP_Term $term Term.
	 * @return void
	 */
	public function order_field_edit( \WP_Term $term ): void {
		$order = (int) get_term_meta( $term->term_id, PostTypes::META_SECTION_ORDER, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="oe-order"><?php esc_html_e( 'Display order', 'oe-reservations' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'oe_section_order', 'oe_section_order_nonce' ); ?>
				<input type="number" name="oe_order" id="oe-order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" step="1">
				<p class="description"><?php esc_html_e( 'Sections are displayed in ascending order.', 'oe-reservations' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the section order.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_order( int $term_id ): void {
		if ( ! isset( $_POST['oe_section_order_nonce'], $_POST['oe_order'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oe_section_order_nonce'] ) ), 'oe_section_order' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( oe_is_demo_mode() ) {
			return;
		}

		update_term_meta( $term_id, PostTypes::META_SECTION_ORDER, absint( wp_unslash( $_POST['oe_order'] ) ) );
	}

	/**
	 * Sections list columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function section_columns( array $columns ): array {
		$columns['oe_order'] = __( 'Order', 'oe-reservations' );

		return $columns;
	}

	/**
	 * Sections list column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function section_column( $content, $column, $term_id ): string {
		if ( 'oe_order' === $column ) {
			return (string) (int) get_term_meta( (int) $term_id, PostTypes::META_SECTION_ORDER, true );
		}

		return (string) $content;
	}
}
