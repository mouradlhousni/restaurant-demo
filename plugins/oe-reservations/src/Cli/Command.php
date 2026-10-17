<?php
/**
 * WP-CLI commands.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Cli;

use OliveEmber\Reservations\Menu\PostTypes;
use OliveEmber\Reservations\Reservations\Installer;
use OliveEmber\Reservations\Reservations\ReservationRepository;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Olive & Ember demo tooling.
 */
final class Command {

	/** Meta flag on everything the seed creates, so a re-seed can clean it up. */
	private const FLAG = '_oe_seed';

	/**
	 * Registers the command.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'oe', self::class );
	}

	/**
	 * Seeds a complete demo site: settings, menu (30 dishes), pages, navigation and reservations.
	 *
	 * Safe to run again: previously seeded dishes, images, terms and reservations are replaced,
	 * pages are updated in place. Writes even in demo mode (it is a setup tool, not a form).
	 *
	 * ## OPTIONS
	 *
	 * [--images=<count>]
	 * : How many dishes get a generated illustration (Chef's picks first). Use a small number in WordPress Playground.
	 * Dishes with a photo in assets/seed/{dish-slug}.webp always get that photo instead.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--reservations=<count>]
	 * : Demo reservations to create over the next 14 days.
	 * ---
	 * default: 40
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp oe seed
	 *     wp oe seed --images=8
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		$start = microtime( true );
		mt_srand( 20261003 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Reproducible demo data.

		Installer::install();
		$this->site_options();
		\WP_CLI::log( '✓ Site options and reservation settings' );

		$this->clean();
		$terms = $this->taxonomies();
		$count = $this->dishes( $terms, max( 0, (int) ( $assoc_args['images'] ?? 30 ) ) );
		\WP_CLI::log( "✓ Menu: {$count['dishes']} dishes, {$count['photos']} photos, {$count['images']} illustrations" );

		$pages = $this->pages();
		$this->navigation( $pages );
		\WP_CLI::log( '✓ Pages and navigation' );

		$booked = $this->reservations( max( 0, (int) ( $assoc_args['reservations'] ?? 40 ) ) );
		\WP_CLI::log( "✓ {$booked} reservations over the next 14 days" );

		if ( 'olive-ember' !== get_stylesheet() && wp_get_theme( 'olive-ember' )->exists() ) {
			switch_theme( 'olive-ember' );
		}
		flush_rewrite_rules();

		\WP_CLI::success( sprintf( 'Demo site ready in %.1fs: %s', microtime( true ) - $start, home_url( '/' ) ) );
	}

	/**
	 * Site-wide options and default reservation settings.
	 *
	 * @return void
	 */
	private function site_options(): void {
		$options = array(
			'blogname'               => 'Olive & Ember',
			'blogdescription'        => 'Mediterranean kitchen & wood-fired grill in Marseille',
			'timezone_string'        => 'Europe/Paris',
			'gmt_offset'             => '',
			'date_format'            => 'j F Y',
			'time_format'            => 'H:i',
			'start_of_week'          => 1,
			'default_comment_status' => 'closed',
			'default_ping_status'    => 'closed',
		);
		foreach ( $options as $name => $value ) {
			update_option( $name, $value );
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}

		$settings                       = Settings::defaults();
		$settings['notification_email'] = (string) get_option( 'admin_email' );
		update_option( Settings::OPTION, $settings );
		Settings::flush();
	}

	/**
	 * Removes previously seeded content and WordPress' sample content.
	 *
	 * @return void
	 */
	private function clean(): void {
		$seeded = get_posts(
			array(
				'post_type'      => array( PostTypes::POST_TYPE, 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $seeded as $id ) {
			'attachment' === get_post_type( $id ) ? wp_delete_attachment( $id, true ) : wp_delete_post( $id, true );
		}

		foreach ( array( PostTypes::SECTION, PostTypes::ALLERGEN, PostTypes::DIET ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}

		foreach ( array(
			'post' => 'hello-world',
			'page' => 'sample-page',
		) as $type => $slug ) {
			$sample = get_page_by_path( $slug, OBJECT, $type );
			if ( $sample ) {
				wp_delete_post( $sample->ID, true );
			}
		}

		ReservationRepository::truncate();
	}

	/**
	 * Sections, allergens and diets.
	 *
	 * @return array<string, array<string, int>> Taxonomy => slug => term ID.
	 */
	private function taxonomies(): array {
		$ids = array();

		foreach ( SeedData::sections() as $slug => $section ) {
			$term = wp_insert_term(
				$section[0],
				PostTypes::SECTION,
				array(
					'slug'        => $slug,
					'description' => $section[2],
				)
			);
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], PostTypes::META_SECTION_ORDER, $section[1] );
				$ids[ PostTypes::SECTION ][ $slug ] = (int) $term['term_id'];
			}
		}

		foreach ( array(
			PostTypes::ALLERGEN => SeedData::allergens(),
			PostTypes::DIET     => SeedData::diets(),
		) as $taxonomy => $terms ) {
			foreach ( $terms as $slug => $name ) {
				$term = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $term ) ) {
					$ids[ $taxonomy ][ $slug ] = (int) $term['term_id'];
				}
			}
		}

		return $ids;
	}

	/**
	 * Dishes and their pictures: the photo supplied in assets/seed/{dish-slug}.webp when
	 * there is one, otherwise a generated illustration.
	 *
	 * @param array<string, array<string, int>> $terms  Term IDs.
	 * @param int                               $images Number of illustrations to generate.
	 * @return array{dishes: int, photos: int, images: int}
	 */
	private function dishes( array $terms, int $images ): array {
		$dishes = SeedData::dishes();

		// Chef's picks get their pictures first (they are featured on the home page).
		$order = array_keys( $dishes );
		usort( $order, static fn( int $a, int $b ): int => array( ! $dishes[ $a ]['chefs_pick'], $a ) <=> array( ! $dishes[ $b ]['chefs_pick'], $b ) );
		$with_image = array_flip( array_slice( $order, 0, ImageGenerator::is_supported() ? $images : 0 ) );

		$generator = new ImageGenerator();
		$created   = 0;
		$photos    = 0;
		$pictures  = 0;
		$position  = array();

		foreach ( $dishes as $index => $dish ) {
			$position[ $dish['section'] ] = ( $position[ $dish['section'] ] ?? 0 ) + 1;

			$post_id = wp_insert_post(
				array(
					'post_type'    => PostTypes::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $dish['name'],
					'post_excerpt' => $dish['description'],
					'menu_order'   => $position[ $dish['section'] ] * 10,
					'meta_input'   => array(
						PostTypes::META_PRICE      => $dish['price'],
						PostTypes::META_CHEFS_PICK => $dish['chefs_pick'],
						self::FLAG                 => 1,
					),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				\WP_CLI::warning( $post_id->get_error_message() );
				continue;
			}
			++$created;

			wp_set_object_terms( $post_id, array( $terms[ PostTypes::SECTION ][ $dish['section'] ] ), PostTypes::SECTION );
			wp_set_object_terms( $post_id, array_values( array_intersect_key( $terms[ PostTypes::ALLERGEN ], array_flip( $dish['allergens'] ) ) ), PostTypes::ALLERGEN );
			wp_set_object_terms( $post_id, array_values( array_intersect_key( $terms[ PostTypes::DIET ], array_flip( $dish['diets'] ) ) ), PostTypes::DIET );

			// A photo supplied in assets/seed/ wins over the generated illustration.
			$photo = OE_RESERVATIONS_DIR . 'assets/seed/' . sanitize_title( $dish['name'] ) . '.webp';
			if ( is_readable( $photo ) ) {
				$attachment = $this->attach_image( (string) file_get_contents( $photo ), basename( $photo ), $dish['name'], $post_id, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
				if ( $attachment ) {
					set_post_thumbnail( $post_id, $attachment );
					++$photos;
				}
			} elseif ( isset( $with_image[ $index ] ) ) {
				$attachment = $this->attach_image( $generator->render( $dish['art'], 7919 * ( $index + 1 ) ), sanitize_title( $dish['name'] ) . '.' . $generator->extension(), $dish['name'], $post_id );
				if ( $attachment ) {
					set_post_thumbnail( $post_id, $attachment );
					++$pictures;
				}
			}
		}

		return array(
			'dishes' => $created,
			'photos' => $photos,
			'images' => $pictures,
		);
	}

	/**
	 * Saves image bytes as an attachment.
	 *
	 * @param string $bytes       Image data.
	 * @param string $filename    File name.
	 * @param string $alt         Dish name, used for the title and alt text.
	 * @param int    $post_parent Parent post.
	 * @param bool   $photo       A real photo (true) or a generated illustration.
	 * @return int Attachment ID, 0 on failure.
	 */
	private function attach_image( string $bytes, string $filename, string $alt, int $post_parent, bool $photo = false ): int {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			\WP_CLI::warning( $upload['error'] );
			return 0;
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) wp_check_filetype( $filename )['type'],
				'post_title'     => $alt,
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_parent
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		/* translators: %s: dish name. */
		$format = $photo ? __( 'Photo of the dish: %s', 'oe-reservations' ) : __( 'Illustration of the dish: %s', 'oe-reservations' );
		update_post_meta( $id, '_wp_attachment_image_alt', sprintf( $format, $alt ) );
		update_post_meta( $id, self::FLAG, 1 );

		return (int) $id;
	}

	/**
	 * Pages, built from the theme's patterns and the plugin's blocks.
	 *
	 * @return array<string, int> Slug => page ID.
	 */
	private function pages(): array {
		$pattern = static fn( string $slug ): string => '<!-- wp:pattern {"slug":"olive-ember/' . $slug . '"} /-->';

		$menu  = $pattern( 'menu-intro' ) . "\n\n";
		$first = true;
		foreach ( array_keys( SeedData::sections() ) as $slug ) {
			$attributes = array(
				'section' => $slug,
				'align'   => 'wide',
			);
			if ( ! $first ) {
				$attributes['showFilters'] = false;
			}
			$menu .= '<!-- wp:oe/menu-section ' . wp_json_encode( $attributes ) . " /-->\n\n";
			$first = false;
		}
		$menu .= $pattern( 'booking-cta' );

		$pages = array(
			'home'         => array( 'Home', implode( "\n\n", array_map( $pattern, array( 'hero', 'sections-strip', 'story', 'signature-dishes', 'suppliers', 'reviews', 'booking-cta' ) ) ), 'Mediterranean restaurant on the Vieux-Port in Marseille: fish and lamb cooked over vine embers, natural wines from Provence. Book a table online.' ),
			'menu'         => array( 'Menu', $menu, 'The Olive & Ember menu: starters, wood-fired mains, desserts and Provençal wines, with allergen and diet filters.' ),
			'book-a-table' => array( 'Book a table', $pattern( 'booking-page' ), 'Book a table at Olive & Ember in Marseille: choose a day and a time still free, and get your confirmation by email.' ),
			'contact'      => array( 'Contact', $pattern( 'contact-page' ), 'Address, phone, opening hours and map of Olive & Ember, Vieux-Port, Marseille.' ),
		);

		$ids   = array();
		$order = 0;
		foreach ( $pages as $slug => $page ) {
			$existing = get_page_by_path( $slug );
			$data     = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page[0],
				'post_name'    => $slug,
				'post_content' => $page[1],
				'post_excerpt' => $page[2],
				'menu_order'   => $order++,
			);
			if ( 'home' !== $slug ) {
				// Inner pages print their own header (eyebrow + title) from their pattern.
				$data['page_template'] = 'page-wide';
			}
			if ( $existing ) {
				$data['ID'] = $existing->ID;
			}
			$ids[ $slug ] = (int) wp_insert_post( $data );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		update_option( 'oe_reservation_page', $ids['book-a-table'], false );

		return $ids;
	}

	/**
	 * Main navigation menu (the header's Navigation block falls back to the latest menu).
	 *
	 * @param array<string, int> $pages Page IDs.
	 * @return void
	 */
	private function navigation( array $pages ): void {
		// "Book a table" is the header button, so it is not repeated here.
		$links  = '<!-- wp:navigation-link ' . wp_json_encode(
			array(
				'label' => 'Menu',
				'type'  => 'page',
				'id'    => $pages['menu'],
				'url'   => get_permalink( $pages['menu'] ),
				'kind'  => 'post-type',
			)
		) . ' /-->';
		$links .= '<!-- wp:navigation-link ' . wp_json_encode(
			array(
				'label' => 'Our story',
				'url'   => home_url( '/#story' ),
				'kind'  => 'custom',
			)
		) . ' /-->';
		$links .= '<!-- wp:navigation-link ' . wp_json_encode(
			array(
				'label' => 'Contact',
				'type'  => 'page',
				'id'    => $pages['contact'],
				'url'   => get_permalink( $pages['contact'] ),
				'kind'  => 'post-type',
			)
		) . ' /-->';

		$existing = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $existing as $id ) {
			wp_delete_post( $id, true );
		}

		wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Main menu',
				'post_content' => $links,
			)
		);
	}

	/**
	 * Demo reservations over the next 14 days, booked through the availability engine.
	 *
	 * @param int $target How many.
	 * @return int Created.
	 */
	private function reservations( int $target ): int {
		$engine   = Settings::availability();
		$tz       = wp_timezone();
		$today    = new \DateTimeImmutable( 'today', $tz );
		$now      = new \DateTimeImmutable( 'now', $tz );
		$first    = SeedData::first_names();
		$last     = SeedData::last_names();
		$notes    = SeedData::notes();
		$parties  = array( 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 4, 4, 4, 4, 5, 6, 1, 1, 8 );
		$created  = 0;
		$attempts = 0;

		// Open days in the window; today always gets a few bookings for the "Today's service" screen.
		$days = array();
		for ( $i = 0; $i < 14; $i++ ) {
			$date = $today->modify( "+$i days" )->format( 'Y-m-d' );
			if ( $engine->slot_starts( $date ) ) {
				$days[] = $date;
			}
		}
		if ( ! $days ) {
			return 0;
		}

		while ( $created < $target && $attempts < $target * 10 ) {
			++$attempts;
			$date   = $created < 6 && $days[0] === $today->format( 'Y-m-d' ) ? $days[0] : $days[ mt_rand( 0, count( $days ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$guests = $parties[ mt_rand( 0, count( $parties ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand

			// Midnight as "now": today's earlier slots are allowed (they are past services).
			$day   = $engine->day( $date, $guests, ReservationRepository::active_for_date( $date ), $today );
			$slots = array_values( array_filter( $day['slots'], static fn( array $slot ): bool => $slot['available'] ) );
			if ( ! $slots ) {
				continue;
			}
			$time = $slots[ mt_rand( 0, count( $slots ) - 1 ) ]['time']; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand

			$start = new \DateTimeImmutable( "$date $time", $tz );
			$roll  = mt_rand( 1, 100 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			if ( $start < $now ) {
				$status = $roll <= 85 ? ReservationRepository::STATUS_CONFIRMED : ReservationRepository::STATUS_NO_SHOW;
			} elseif ( $roll <= 72 ) {
				$status = ReservationRepository::STATUS_CONFIRMED;
			} elseif ( $roll <= 92 ) {
				$status = ReservationRepository::STATUS_PENDING;
			} else {
				$status = ReservationRepository::STATUS_CANCELLED;
			}

			$name   = $first[ mt_rand( 0, count( $first ) - 1 ) ] . ' ' . $last[ mt_rand( 0, count( $last ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$booked = $now->modify( '-' . mt_rand( 1, 240 ) . ' hours' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$email  = sanitize_user( remove_accents( strtolower( str_replace( ' ', '.', $name ) ) ), true ) . '@example.com';
			$result = ReservationRepository::create(
				array(
					'date'       => $date,
					'time'       => $time,
					'guests'     => $guests,
					'name'       => $name,
					'email'      => $email,
					'phone'      => sprintf( '+33 6 %02d %02d %02d %02d', mt_rand( 10, 99 ), mt_rand( 10, 99 ), mt_rand( 10, 99 ), mt_rand( 10, 99 ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					'notes'      => $notes[ mt_rand( 0, count( $notes ) - 1 ) ], // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					'status'     => $status,
					'source'     => mt_rand( 1, 4 ) === 1 ? 'phone' : 'web', // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					'created_at' => ( $booked < $start ? $booked : $start->modify( '-1 day' ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				),
				static fn( array $existing ): ?string => $engine->check( $date, $time, $guests, $existing, $today )
			);

			if ( ! is_wp_error( $result ) ) {
				++$created;
			}
		}

		return $created;
	}
}
