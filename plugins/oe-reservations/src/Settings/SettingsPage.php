<?php
/**
 * Settings screen.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Settings;

use OliveEmber\Reservations\Admin\AdminMenu;
use OliveEmber\Reservations\Availability\BookingRules;

defined( 'ABSPATH' ) || exit;

/**
 * "Reservations → Settings", built with the Settings API.
 */
final class SettingsPage {

	public const SLUG   = 'oe-reservations-settings';
	private const GROUP = 'oe_settings_group';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'update_option_' . Settings::OPTION, array( Settings::class, 'flush' ) );
		add_filter( 'option_page_capability_' . self::GROUP, 'oe_capability' );
	}

	/**
	 * Submenu page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page( AdminMenu::SLUG, __( 'Reservation settings', 'oe-reservations' ), __( 'Settings', 'oe-reservations' ), oe_capability(), self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Registers the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'oe_hours', __( 'Opening hours', 'oe-reservations' ), array( $this, 'hours_intro' ), self::SLUG );
		add_settings_field( 'hours', __( 'Weekly schedule', 'oe-reservations' ), array( $this, 'field_hours' ), self::SLUG, 'oe_hours' );
		add_settings_field(
			'closures',
			__( 'Exceptional closures', 'oe-reservations' ),
			array( $this, 'field_closures' ),
			self::SLUG,
			'oe_hours',
			array( 'label_for' => 'oe-closures' )
		);

		add_settings_section( 'oe_rules', __( 'Booking rules', 'oe-reservations' ), '__return_null', self::SLUG );
		$numbers = array(
			'slot_interval'    => array( __( 'Slot interval', 'oe-reservations' ), __( 'minutes between two bookable times', 'oe-reservations' ), 5, 240 ),
			'service_duration' => array( __( 'Service duration', 'oe-reservations' ), __( 'minutes a table is occupied', 'oe-reservations' ), 15, 480 ),
			'capacity'         => array( __( 'Capacity', 'oe-reservations' ), __( 'covers seated at the same time', 'oe-reservations' ), 1, 1000 ),
			'max_party'        => array( __( 'Largest party', 'oe-reservations' ), __( 'guests per online booking', 'oe-reservations' ), 1, 50 ),
			'min_notice'       => array( __( 'Minimum notice', 'oe-reservations' ), __( 'minutes between booking and arrival', 'oe-reservations' ), 0, 10080 ),
			'max_advance_days' => array( __( 'Booking window', 'oe-reservations' ), __( 'days ahead bookings are open', 'oe-reservations' ), 1, 365 ),
		);
		foreach ( $numbers as $key => $field ) {
			add_settings_field(
				$key,
				$field[0],
				array( $this, 'field_number' ),
				self::SLUG,
				'oe_rules',
				array(
					'label_for' => 'oe-' . $key,
					'key'       => $key,
					'unit'      => $field[1],
					'min'       => $field[2],
					'max'       => $field[3],
				)
			);
		}
		add_settings_field( 'auto_confirm', __( 'Confirmation', 'oe-reservations' ), array( $this, 'field_auto_confirm' ), self::SLUG, 'oe_rules' );

		add_settings_section( 'oe_restaurant', __( 'Restaurant details', 'oe-reservations' ), array( $this, 'restaurant_intro' ), self::SLUG );
		$texts = array(
			'restaurant_name'    => array( __( 'Name', 'oe-reservations' ), 'text' ),
			'street_address'     => array( __( 'Street address', 'oe-reservations' ), 'text' ),
			'postal_code'        => array( __( 'Postal code', 'oe-reservations' ), 'text' ),
			'locality'           => array( __( 'City', 'oe-reservations' ), 'text' ),
			'country'            => array( __( 'Country code', 'oe-reservations' ), 'text' ),
			'phone'              => array( __( 'Phone', 'oe-reservations' ), 'tel' ),
			'contact_email'      => array( __( 'Public email', 'oe-reservations' ), 'email' ),
			'notification_email' => array( __( 'Notification email', 'oe-reservations' ), 'email' ),
			'latitude'           => array( __( 'Latitude', 'oe-reservations' ), 'text' ),
			'longitude'          => array( __( 'Longitude', 'oe-reservations' ), 'text' ),
			'price_range'        => array( __( 'Price range', 'oe-reservations' ), 'text' ),
			'cuisine'            => array( __( 'Cuisine', 'oe-reservations' ), 'text' ),
		);
		foreach ( $texts as $key => $field ) {
			add_settings_field(
				$key,
				$field[0],
				array( $this, 'field_text' ),
				self::SLUG,
				'oe_restaurant',
				array(
					'label_for' => 'oe-' . $key,
					'key'       => $key,
					'type'      => $field[1],
				)
			);
		}
	}

	/**
	 * Page markup.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( oe_capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: site timezone. */
					esc_html__( 'All times use the site timezone: %s.', 'oe-reservations' ),
					'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php#timezone_string' ) ); ?>"><?php esc_html_e( 'Change', 'oe-reservations' ); ?></a>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Opening hours intro.
	 *
	 * @return void
	 */
	public function hours_intro(): void {
		echo '<p>' . esc_html__( 'One or more periods per day, e.g. "12:00-14:30, 19:00-22:30". Leave empty when closed. A time slot is offered only when a full service fits before closing time.', 'oe-reservations' ) . '</p>';
	}

	/**
	 * Restaurant intro.
	 *
	 * @return void
	 */
	public function restaurant_intro(): void {
		echo '<p>' . esc_html__( 'Used on the contact page, in emails and in the structured data read by search engines.', 'oe-reservations' ) . '</p>';
	}

	/**
	 * Weekly schedule field.
	 *
	 * @return void
	 */
	public function field_hours(): void {
		$hours = (array) Settings::get( 'hours', array() );
		echo '<table class="oe-hours" role="presentation">';
		foreach ( Settings::opening_hours() as $day ) {
			$id = 'oe-hours-' . $day['key'];
			printf(
				'<tr><th scope="row" style="padding:4px 12px 4px 0;font-weight:normal"><label for="%1$s">%2$s</label></th><td style="padding:4px 0"><input type="text" class="regular-text" id="%1$s" name="%3$s[hours][%4$s]" value="%5$s" placeholder="%6$s"></td></tr>',
				esc_attr( $id ),
				esc_html( $day['label'] ),
				esc_attr( Settings::OPTION ),
				esc_attr( $day['key'] ),
				esc_attr( (string) ( $hours[ $day['key'] ] ?? '' ) ),
				esc_attr__( 'Closed', 'oe-reservations' )
			);
		}
		echo '</table>';
	}

	/**
	 * Closures field.
	 *
	 * @return void
	 */
	public function field_closures(): void {
		$closures = (array) Settings::get( 'closures', array() );
		printf(
			'<textarea id="oe-closures" name="%s[closures]" rows="5" cols="30" class="code" aria-describedby="oe-closures-help">%s</textarea>',
			esc_attr( Settings::OPTION ),
			esc_textarea( implode( "\n", $closures ) )
		);
		echo '<p class="description" id="oe-closures-help">' . esc_html__( 'One date per line (YYYY-MM-DD), or a range: "2026-12-24 to 2026-12-26".', 'oe-reservations' ) . '</p>';
	}

	/**
	 * Number field.
	 *
	 * @param array<string, mixed> $args Field args.
	 * @return void
	 */
	public function field_number( array $args ): void {
		printf(
			'<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$d" min="%5$d" max="%6$d" step="1" class="small-text"> <span class="description">%7$s</span>',
			esc_attr( $args['label_for'] ),
			esc_attr( Settings::OPTION ),
			esc_attr( $args['key'] ),
			(int) Settings::get( $args['key'] ),
			(int) $args['min'],
			(int) $args['max'],
			esc_html( $args['unit'] )
		);
	}

	/**
	 * Text field.
	 *
	 * @param array<string, mixed> $args Field args.
	 * @return void
	 */
	public function field_text( array $args ): void {
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s[%4$s]" value="%5$s" class="regular-text">',
			esc_attr( $args['type'] ),
			esc_attr( $args['label_for'] ),
			esc_attr( Settings::OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( (string) Settings::get( $args['key'], '' ) )
		);
	}

	/**
	 * Auto-confirm field.
	 *
	 * @return void
	 */
	public function field_auto_confirm(): void {
		printf(
			'<label><input type="checkbox" name="%s[auto_confirm]" value="1" %s> %s</label>',
			esc_attr( Settings::OPTION ),
			checked( (bool) Settings::get( 'auto_confirm' ), true, false ),
			esc_html__( 'Confirm online bookings automatically (otherwise they stay "pending" until a staff member confirms them).', 'oe-reservations' )
		);
	}

	/**
	 * Sanitizes the whole option. Invalid values keep their previous value and raise a notice.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$previous = Settings::all();

		if ( oe_is_demo_mode() ) {
			add_settings_error( Settings::OPTION, 'oe_demo', __( 'Demo mode: the form works, but no data is stored or sent.', 'oe-reservations' ), 'info' );

			return $previous;
		}

		$input  = is_array( $input ) ? $input : array();
		$output = $previous;

		// Hours.
		foreach ( BookingRules::DAYS as $day ) {
			if ( ! isset( $input['hours'][ $day ] ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $input['hours'][ $day ] );
			try {
				$output['hours'][ $day ] = BookingRules::format_periods( BookingRules::parse_periods( $value ) );
			} catch ( \InvalidArgumentException $e ) {
				/* translators: 1: weekday key, 2: error message. */
				add_settings_error( Settings::OPTION, 'oe_hours_' . $day, sprintf( __( 'Opening hours (%1$s) not saved: %2$s', 'oe-reservations' ), $day, $e->getMessage() ) );
			}
		}

		// Closures: dates and ranges, expanded and sorted.
		// The callback can run twice on the first save (add_option), hence the array case.
		if ( isset( $input['closures'] ) ) {
			$closures           = is_array( $input['closures'] ) ? implode( "\n", $input['closures'] ) : (string) $input['closures'];
			$output['closures'] = self::parse_closures( $closures );
		}

		// Numbers, clamped.
		$limits = array(
			'slot_interval'    => array( 5, 240 ),
			'service_duration' => array( 15, 480 ),
			'capacity'         => array( 1, 1000 ),
			'max_party'        => array( 1, 50 ),
			'min_notice'       => array( 0, 10080 ),
			'max_advance_days' => array( 1, 365 ),
		);
		foreach ( $limits as $key => $range ) {
			if ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$output[ $key ] = min( $range[1], max( $range[0], (int) $input[ $key ] ) );
			}
		}
		$output['max_party']    = min( $output['max_party'], $output['capacity'] );
		$output['auto_confirm'] = ! empty( $input['auto_confirm'] );

		// Restaurant details.
		foreach ( array( 'restaurant_name', 'street_address', 'postal_code', 'locality', 'phone', 'price_range', 'cuisine' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}
		$output['country'] = strtoupper( substr( sanitize_key( (string) ( $input['country'] ?? $previous['country'] ) ), 0, 2 ) );
		foreach ( array( 'contact_email', 'notification_email' ) as $key ) {
			$email = sanitize_email( (string) ( $input[ $key ] ?? '' ) );
			if ( $email && is_email( $email ) ) {
				$output[ $key ] = $email;
			} else {
				add_settings_error( Settings::OPTION, 'oe_' . $key, __( 'Please enter a valid email address.', 'oe-reservations' ) );
			}
		}
		foreach ( array(
			'latitude'  => 90,
			'longitude' => 180,
		) as $key => $bound ) {
			if ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) && abs( (float) $input[ $key ] ) <= $bound ) {
				$output[ $key ] = round( (float) $input[ $key ], 6 );
			}
		}

		return $output;
	}

	/**
	 * Parses the closures textarea.
	 *
	 * @param string $value One date or "date to date" range per line.
	 * @return array<int, string>
	 */
	public static function parse_closures( string $value ): array {
		$dates = array();

		foreach ( preg_split( '/[\r\n,]+/', $value ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\s*(?:to|-|–|\.\.)\s*(\d{4}-\d{2}-\d{2})$/u', $line, $m ) ) {
				if ( BookingRules::is_valid_date( $m[1] ) && BookingRules::is_valid_date( $m[2] ) && $m[1] <= $m[2] ) {
					// Ranges are capped at 92 days.
					$day = new \DateTimeImmutable( $m[1] );
					$end = min( $m[2], $day->modify( '+91 days' )->format( 'Y-m-d' ) );
					while ( $day->format( 'Y-m-d' ) <= $end ) {
						$dates[] = $day->format( 'Y-m-d' );
						$day     = $day->modify( '+1 day' );
					}
				}
			} elseif ( BookingRules::is_valid_date( $line ) ) {
				$dates[] = $line;
			}
		}

		$dates = array_values( array_unique( $dates ) );
		sort( $dates );

		return $dates;
	}
}
