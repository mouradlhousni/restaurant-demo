<?php
/**
 * Reservations admin screens.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Admin;

use OliveEmber\Reservations\Availability\BookingRules;
use OliveEmber\Reservations\Email\Mailer;
use OliveEmber\Reservations\Reservations\BookingService;
use OliveEmber\Reservations\Reservations\ReservationRepository;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * "All reservations" (list table, filters, actions, CSV export) and "Today's service".
 */
final class ReservationsPage {

	public const TODAY_SLUG = 'oe-reservations-today';
	private const EXPORT    = 'oe_export_reservations';

	/**
	 * Status transitions allowed by each action.
	 */
	private const ACTIONS = array(
		'confirm' => array(
			'to'   => ReservationRepository::STATUS_CONFIRMED,
			'from' => array( ReservationRepository::STATUS_PENDING ),
		),
		'no_show' => array(
			'to'   => ReservationRepository::STATUS_NO_SHOW,
			'from' => array( ReservationRepository::STATUS_PENDING, ReservationRepository::STATUS_CONFIRMED ),
		),
		'cancel'  => array(
			'to'   => ReservationRepository::STATUS_CANCELLED,
			'from' => array( ReservationRepository::STATUS_PENDING, ReservationRepository::STATUS_CONFIRMED ),
		),
	);

	/**
	 * Hook suffixes of the screens.
	 *
	 * @var array<int, string>
	 */
	private array $hooks = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Submenu pages.
	 *
	 * @return void
	 */
	public function add_pages(): void {
		$list  = add_submenu_page( AdminMenu::SLUG, __( 'Reservations', 'oe-reservations' ), __( 'All reservations', 'oe-reservations' ), oe_capability(), AdminMenu::SLUG, array( $this, 'render_list' ) );
		$today = add_submenu_page( AdminMenu::SLUG, __( "Today's service", 'oe-reservations' ), __( "Today's service", 'oe-reservations' ), oe_capability(), self::TODAY_SLUG, array( $this, 'render_today' ) );

		$this->hooks = array_filter( array( $list, $today ) );

		foreach ( $this->hooks as $hook ) {
			add_action( 'load-' . $hook, array( $this, 'process_actions' ) );
		}
		if ( $list ) {
			add_action( 'load-' . $list, array( $this, 'screen_options' ) );
		}
	}

	/**
	 * Admin styles, on these screens only.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( in_array( $hook, $this->hooks, true ) ) {
			wp_enqueue_style( 'oe-admin', OE_RESERVATIONS_URL . 'assets/css/admin.css', array(), OE_RESERVATIONS_VERSION );
		}
	}

	/**
	 * "Items per page" screen option.
	 *
	 * @return void
	 */
	public function screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Reservations per page', 'oe-reservations' ),
				'default' => 20,
				'option'  => 'oe_reservations_per_page',
			)
		);
	}

	/**
	 * Persists the screen option.
	 *
	 * @param mixed  $keep   Default.
	 * @param string $option Option.
	 * @param mixed  $value  Value.
	 * @return mixed
	 */
	public function save_screen_option( $keep, $option, $value ) {
		return 'oe_reservations_per_page' === $option ? min( 200, max( 1, (int) $value ) ) : $keep;
	}

	/**
	 * Row actions for a reservation.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return array<string, string>
	 */
	public static function row_actions( array $item ): array {
		$labels  = array(
			'confirm' => __( 'Confirm', 'oe-reservations' ),
			'no_show' => __( 'No-show', 'oe-reservations' ),
			'cancel'  => __( 'Cancel', 'oe-reservations' ),
		);
		$actions = array();

		foreach ( self::ACTIONS as $action => $rule ) {
			if ( in_array( $item['status'], $rule['from'], true ) ) {
				$actions[ $action ] = sprintf(
					'<a href="%s" class="oe-action-%s" aria-label="%s">%s</a>',
					esc_url( self::action_url( $action, (int) $item['id'] ) ),
					esc_attr( $action ),
					/* translators: 1: action, 2: guest name. */
					esc_attr( sprintf( __( '%1$s the reservation of %2$s', 'oe-reservations' ), $labels[ $action ], $item['name'] ) ),
					esc_html( $labels[ $action ] )
				);
			}
		}

		return $actions;
	}

	/**
	 * Nonced URL of a single-row action, returning to the current screen.
	 *
	 * @param string $action Action.
	 * @param int    $id     Reservation ID.
	 * @return string
	 */
	private static function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'oe_action' => $action,
					'id'        => $id,
				),
				remove_query_arg( array( 'oe_done', 'oe_count', 'oe_action', 'id', '_wpnonce' ) )
			),
			'oe_reservation_' . $action . '_' . $id
		);
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_badge( string $status ): string {
		$labels = ReservationRepository::statuses();

		return sprintf( '<span class="oe-badge oe-badge--%s">%s</span>', esc_attr( $status ), esc_html( $labels[ $status ] ?? $status ) );
	}

	/**
	 * Applies row and bulk actions, then redirects (PRG).
	 *
	 * @return void
	 */
	public function process_actions(): void {
		if ( ! current_user_can( oe_capability() ) ) {
			return;
		}

		$ids    = array();
		$action = '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonces are verified right below, per action type.
		if ( isset( $_GET['oe_action'], $_GET['id'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['oe_action'] ) );
			$ids    = array( absint( $_GET['id'] ) );
			check_admin_referer( 'oe_reservation_' . $action . '_' . $ids[0] );
		} else {
			foreach ( array( 'action', 'action2' ) as $field ) {
				$candidate = isset( $_GET[ $field ] ) ? sanitize_key( wp_unslash( $_GET[ $field ] ) ) : '';
				if ( isset( self::ACTIONS[ $candidate ] ) ) {
					$action = $candidate;
					break;
				}
			}
			if ( $action ) {
				check_admin_referer( 'bulk-reservations' );
				$ids = isset( $_GET['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['ids'] ) ) : array();
			}
		}
		// phpcs:enable

		if ( ! $action || ! isset( self::ACTIONS[ $action ] ) ) {
			return;
		}

		$redirect = remove_query_arg( array( 'oe_action', 'id', '_wpnonce', '_wp_http_referer', 'action', 'action2', 'ids', 'filter_action' ) );

		if ( oe_is_demo_mode() ) {
			wp_safe_redirect( add_query_arg( 'oe_done', 'demo', $redirect ) );
			exit;
		}

		$rule    = self::ACTIONS[ $action ];
		$changed = 0;

		foreach ( $ids as $id ) {
			$reservation = ReservationRepository::find( $id );
			if ( ! $reservation || ! in_array( $reservation['status'], $rule['from'], true ) ) {
				continue;
			}

			$changed              += ReservationRepository::update_status( array( $id ), $rule['to'] );
			$reservation['status'] = $rule['to'];

			if ( 'confirm' === $action ) {
				// The raw token is never stored: issue a new one for the link in this email.
				Mailer::customer( $reservation, 'confirmed', ReservationRepository::rotate_token( $id ) );
			} elseif ( 'cancel' === $action ) {
				Mailer::customer( $reservation, 'cancelled' );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'oe_done'  => $action,
					'oe_count' => $changed,
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Result notice after an action.
	 *
	 * @return void
	 */
	private function action_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! isset( $_GET['oe_done'] ) ) {
			return;
		}
		$done  = sanitize_key( wp_unslash( $_GET['oe_done'] ) );
		$count = isset( $_GET['oe_count'] ) ? absint( $_GET['oe_count'] ) : 0;
		// phpcs:enable

		if ( 'demo' === $done ) {
			$message = __( 'Demo mode: the form works, but no data is stored or sent.', 'oe-reservations' );
			$type    = 'info';
		} elseif ( 0 === $count ) {
			$message = __( 'No reservation was changed: the selected reservations do not allow this action.', 'oe-reservations' );
			$type    = 'warning';
		} else {
			$messages = array(
				/* translators: %d: number of reservations. */
				'confirm' => _n( '%d reservation confirmed. The guest has been emailed.', '%d reservations confirmed. The guests have been emailed.', $count, 'oe-reservations' ),
				/* translators: %d: number of reservations. */
				'no_show' => _n( '%d reservation marked as no-show.', '%d reservations marked as no-show.', $count, 'oe-reservations' ),
				/* translators: %d: number of reservations. */
				'cancel'  => _n( '%d reservation cancelled. The guest has been emailed.', '%d reservations cancelled. The guests have been emailed.', $count, 'oe-reservations' ),
			);
			$message  = sprintf( $messages[ $done ] ?? '%d', $count );
			$type     = 'success';
		}

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	/**
	 * Active list filters, from the query string.
	 *
	 * @return array<string, string>
	 */
	public static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$get = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		// phpcs:enable

		$from   = BookingRules::is_valid_date( $get( 'from' ) ) ? $get( 'from' ) : '';
		$to     = BookingRules::is_valid_date( $get( 'to' ) ) ? $get( 'to' ) : '';
		$range  = $get( 'range' );
		$status = isset( ReservationRepository::statuses()[ $get( 'status' ) ] ) ? $get( 'status' ) : '';
		$search = $get( 's' );

		if ( '' === $range ) {
			$range = ( $from || $to ) ? 'custom' : 'upcoming';
		}

		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		switch ( $range ) {
			case 'today':
				$from = $today->format( 'Y-m-d' );
				$to   = $from;
				break;
			case 'tomorrow':
				$from = $today->modify( '+1 day' )->format( 'Y-m-d' );
				$to   = $from;
				break;
			case 'week':
				$from = $today->format( 'Y-m-d' );
				$to   = $today->modify( '+6 days' )->format( 'Y-m-d' );
				break;
			case 'past':
				$from = '';
				$to   = $today->modify( '-1 day' )->format( 'Y-m-d' );
				break;
			case 'all':
				$from = '';
				$to   = '';
				break;
			case 'custom':
				break;
			default:
				$range = 'upcoming';
				$from  = $today->format( 'Y-m-d' );
				$to    = '';
		}

		return compact( 'from', 'to', 'range', 'status', 'search' );
	}

	/**
	 * CSV export URL for the current filters.
	 *
	 * @param array<string, string> $filters Filters.
	 * @return string
	 */
	public static function export_url( array $filters ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::EXPORT,
					'from'   => $filters['from'],
					'to'     => $filters['to'],
					'range'  => 'custom',
					'status' => $filters['status'],
					's'      => $filters['search'],
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT
		);
	}

	/**
	 * "All reservations" screen.
	 *
	 * @return void
	 */
	public function render_list(): void {
		$filters = self::filters();
		$table   = new ReservationsListTable( $filters );
		$table->prepare_items();
		?>
		<div class="wrap oe-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Reservations', 'oe-reservations' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::TODAY_SLUG ) ); ?>"><?php esc_html_e( "Today's service", 'oe-reservations' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->action_notice(); ?>
			<?php $table->views(); ?>
			<form method="get" id="oe-reservations-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( AdminMenu::SLUG ); ?>">
				<?php if ( $filters['status'] ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>">
				<?php endif; ?>
				<?php $table->search_box( __( 'Search guests', 'oe-reservations' ), 'oe-search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<script>
			// Editing a date switches the range selector to "Custom dates".
			document.querySelectorAll( '#oe-from, #oe-to' ).forEach( ( input ) => input.addEventListener( 'change', () => {
				document.getElementById( 'oe-range' ).value = 'custom';
			} ) );
		</script>
		<?php
	}

	/**
	 * "Today's service" screen: the day's bookings by slot, with occupancy.
	 *
	 * @return void
	 */
	public function render_today(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only date parameter.
		$requested = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		$today     = ( new \DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );
		$date      = BookingRules::is_valid_date( $requested ) ? $requested : $today;
		$day       = new \DateTimeImmutable( $date, wp_timezone() );
		$rules     = Settings::rules();
		$engine    = Settings::availability();

		$all = ReservationRepository::query(
			array(
				'from'     => $date,
				'to'       => $date,
				'per_page' => -1,
				'orderby'  => 'reservation_date',
			)
		)['items'];

		$active    = array_values( array_filter( $all, static fn( array $r ): bool => in_array( $r['status'], ReservationRepository::ACTIVE_STATUSES, true ) ) );
		$covers    = array_sum( array_column( $active, 'guests' ) );
		$pending   = count( array_filter( $active, static fn( array $r ): bool => ReservationRepository::STATUS_PENDING === $r['status'] ) );
		$cancelled = count( array_filter( $all, static fn( array $r ): bool => ReservationRepository::STATUS_CANCELLED === $r['status'] ) );
		$periods   = $engine->periods_for( $date );

		// Slot grid + any off-grid booking time.
		$times = array_map( array( BookingRules::class, 'format_minutes' ), $engine->slot_starts( $date ) );
		$times = array_values( array_unique( array_merge( $times, array_column( $all, 'time' ) ) ) );
		sort( $times );

		$nav = static fn( string $target ): string => esc_url( add_query_arg( 'date', $target, admin_url( 'admin.php?page=' . self::TODAY_SLUG ) ) );
		?>
		<div class="wrap oe-admin oe-service">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Service', 'oe-reservations' ); ?> · <?php echo esc_html( wp_date( 'l j F Y', $day->getTimestamp() ) ); ?></h1>
			<hr class="wp-header-end">
			<?php $this->action_notice(); ?>

			<form class="oe-service__nav" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::TODAY_SLUG ); ?>">
				<a class="button" href="<?php echo $nav( $day->modify( '-1 day' )->format( 'Y-m-d' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $nav. ?>"><span aria-hidden="true">‹</span> <?php esc_html_e( 'Previous day', 'oe-reservations' ); ?></a>
				<a class="button" href="<?php echo $nav( $today ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $nav. ?>"><?php esc_html_e( 'Today', 'oe-reservations' ); ?></a>
				<a class="button" href="<?php echo $nav( $day->modify( '+1 day' )->format( 'Y-m-d' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $nav. ?>"><?php esc_html_e( 'Next day', 'oe-reservations' ); ?> <span aria-hidden="true">›</span></a>
				<label class="screen-reader-text" for="oe-service-date"><?php esc_html_e( 'Go to date', 'oe-reservations' ); ?></label>
				<input type="date" id="oe-service-date" name="date" value="<?php echo esc_attr( $date ); ?>">
				<?php submit_button( __( 'Go', 'oe-reservations' ), 'secondary', '', false ); ?>
				<button type="button" class="button oe-print" onclick="window.print()"><?php esc_html_e( 'Print', 'oe-reservations' ); ?></button>
			</form>

			<ul class="oe-stats">
				<li><strong><?php echo esc_html( (string) count( $active ) ); ?></strong> <?php esc_html_e( 'reservations', 'oe-reservations' ); ?></li>
				<li><strong><?php echo esc_html( (string) $covers ); ?></strong> <?php esc_html_e( 'covers', 'oe-reservations' ); ?></li>
				<li><strong><?php echo esc_html( (string) $pending ); ?></strong> <?php esc_html_e( 'to confirm', 'oe-reservations' ); ?></li>
				<li><strong><?php echo esc_html( (string) $cancelled ); ?></strong> <?php esc_html_e( 'cancelled', 'oe-reservations' ); ?></li>
				<li><?php echo $periods ? esc_html( BookingRules::format_periods( $periods ) ) : esc_html__( 'Closed', 'oe-reservations' ); ?></li>
			</ul>

			<?php if ( ! $times ) : ?>
				<p class="oe-empty"><?php esc_html_e( 'The restaurant is closed on this day and there are no reservations.', 'oe-reservations' ); ?></p>
			<?php endif; ?>

			<ol class="oe-timeline">
				<?php
				foreach ( $times as $time ) :
					$start  = (int) substr( $time, 0, 2 ) * 60 + (int) substr( $time, 3, 2 );
					$seated = 0;
					foreach ( $active as $reservation ) {
						$begin = (int) substr( $reservation['time'], 0, 2 ) * 60 + (int) substr( $reservation['time'], 3, 2 );
						if ( $begin <= $start && $start < $begin + $rules->service_duration ) {
							$seated += $reservation['guests'];
						}
					}
					$arrivals = array_filter( $all, static fn( array $r ): bool => $r['time'] === $time );
					$percent  = min( 100, (int) round( $seated / $rules->capacity * 100 ) );
					?>
					<li class="oe-slot<?php echo $arrivals ? '' : ' oe-slot--empty'; ?>">
						<div class="oe-slot__head">
							<span class="oe-slot__time"><?php echo esc_html( $time ); ?></span>
							<span class="oe-meter" role="img" aria-label="<?php /* translators: 1: seated covers, 2: capacity. */ echo esc_attr( sprintf( __( '%1$d of %2$d covers seated', 'oe-reservations' ), $seated, $rules->capacity ) ); ?>">
								<span class="oe-meter__bar<?php echo $percent >= 90 ? ' is-full' : ''; ?>" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></span>
							</span>
							<span class="oe-slot__count"><?php echo esc_html( $seated . ' / ' . $rules->capacity ); ?></span>
						</div>
						<?php if ( $arrivals ) : ?>
							<ul class="oe-arrivals">
								<?php foreach ( $arrivals as $reservation ) : ?>
									<li class="oe-arrival oe-arrival--<?php echo esc_attr( $reservation['status'] ); ?>">
										<span class="oe-arrival__covers" title="<?php esc_attr_e( 'Covers', 'oe-reservations' ); ?>"><?php echo esc_html( (string) $reservation['guests'] ); ?></span>
										<span class="oe-arrival__who">
											<strong><?php echo esc_html( $reservation['name'] ); ?></strong>
											<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $reservation['phone'] ) ); ?>"><?php echo esc_html( $reservation['phone'] ); ?></a>
											<?php if ( '' !== $reservation['notes'] ) : ?>
												<em class="oe-arrival__notes"><?php echo esc_html( $reservation['notes'] ); ?></em>
											<?php endif; ?>
										</span>
										<?php echo self::status_badge( $reservation['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?>
										<span class="oe-arrival__actions">
											<?php echo implode( ' ', self::row_actions( $reservation ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in row_actions(). ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * Streams the filtered reservations as CSV.
	 *
	 * @return void
	 */
	public function export(): void {
		if ( ! current_user_can( oe_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export reservations.', 'oe-reservations' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::EXPORT );

		$filters = self::filters();
		$rows    = ReservationRepository::query(
			array(
				'from'     => $filters['from'],
				'to'       => $filters['to'],
				'status'   => $filters['status'],
				'search'   => $filters['search'],
				'per_page' => -1,
			)
		)['items'];

		$statuses = ReservationRepository::statuses();
		$filename = 'reservations-' . wp_date( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:disable WordPress.WP.AlternativeFunctions -- Streaming to php://output.
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for spreadsheet apps.
		fputcsv( $out, array( 'Reference', 'Date', 'Time', 'Covers', 'Name', 'Email', 'Phone', 'Notes', 'Status', 'Source', 'Booked at' ) );

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array_map(
					array( self::class, 'csv_cell' ),
					array(
						BookingService::reference( $row['id'] ),
						$row['date'],
						$row['time'],
						$row['guests'],
						$row['name'],
						$row['email'],
						$row['phone'],
						$row['notes'],
						$statuses[ $row['status'] ] ?? $row['status'],
						$row['source'],
						wp_date( 'Y-m-d H:i', (int) strtotime( $row['created_at'] . ' UTC' ) ),
					)
				)
			);
		}

		fclose( $out );
		// phpcs:enable
		exit;
	}

	/**
	 * Neutralizes spreadsheet formulas (CSV injection).
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function csv_cell( $value ): string {
		$value = (string) $value;

		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}
}
