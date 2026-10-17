<?php
/**
 * Reservations list table.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Admin;

use OliveEmber\Reservations\Reservations\BookingService;
use OliveEmber\Reservations\Reservations\ReservationRepository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table over the custom reservations table.
 */
final class ReservationsListTable extends \WP_List_Table {

	/**
	 * Active filters.
	 *
	 * @var array<string, string>
	 */
	private array $filters;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $filters from, to, status, search, range.
	 */
	public function __construct( array $filters ) {
		parent::__construct(
			array(
				'singular' => 'reservation',
				'plural'   => 'reservations',
				'ajax'     => false,
			)
		);
		$this->filters = $filters;
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox">',
			'when'       => __( 'Date', 'oe-reservations' ),
			'name'       => __( 'Guest', 'oe-reservations' ),
			'guests'     => __( 'Covers', 'oe-reservations' ),
			'notes'      => __( 'Notes', 'oe-reservations' ),
			'status'     => __( 'Status', 'oe-reservations' ),
			'created_at' => __( 'Booked', 'oe-reservations' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'when'       => array( 'reservation_date', false ),
			'name'       => array( 'name', false ),
			'guests'     => array( 'guests', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'confirm' => __( 'Confirm', 'oe-reservations' ),
			'no_show' => __( 'Mark as no-show', 'oe-reservations' ),
			'cancel'  => __( 'Cancel', 'oe-reservations' ),
		);
	}

	/**
	 * Loads the page of items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'oe_reservations_per_page', 20 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'reservation_date';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : ( 'past' === $this->filters['range'] ? 'desc' : 'asc' );
		// phpcs:enable

		$result = ReservationRepository::query(
			array(
				'from'     => $this->filters['from'],
				'to'       => $this->filters['to'],
				'status'   => $this->filters['status'],
				'search'   => $this->filters['search'],
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'when' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);
	}

	/**
	 * Status views with counts.
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$counts  = ReservationRepository::count_by_status();
		$current = $this->filters['status'];
		$base    = remove_query_arg( array( 'status', 'paged', 'oe_done', 'oe_count' ) );

		$views = array(
			'all' => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $base ),
				'' === $current ? ' class="current" aria-current="page"' : '',
				esc_html__( 'All', 'oe-reservations' ),
				array_sum( $counts )
			),
		);

		foreach ( ReservationRepository::statuses() as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$counts[ $status ]
			);
		}

		return $views;
	}

	/**
	 * Date filters and CSV export above the table.
	 *
	 * @param string $which top | bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$ranges = array(
			'upcoming' => __( 'Upcoming', 'oe-reservations' ),
			'today'    => __( 'Today', 'oe-reservations' ),
			'tomorrow' => __( 'Tomorrow', 'oe-reservations' ),
			'week'     => __( 'Next 7 days', 'oe-reservations' ),
			'past'     => __( 'Past', 'oe-reservations' ),
			'all'      => __( 'All dates', 'oe-reservations' ),
			'custom'   => __( 'Custom dates', 'oe-reservations' ),
		);
		?>
		<div class="alignleft actions oe-filters">
			<label class="screen-reader-text" for="oe-range"><?php esc_html_e( 'Date range', 'oe-reservations' ); ?></label>
			<select name="range" id="oe-range">
				<?php foreach ( $ranges as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->filters['range'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="oe-from"><?php esc_html_e( 'From', 'oe-reservations' ); ?></label>
			<input type="date" id="oe-from" name="from" value="<?php echo esc_attr( $this->filters['from'] ); ?>">
			<label for="oe-to"><?php esc_html_e( 'To', 'oe-reservations' ); ?></label>
			<input type="date" id="oe-to" name="to" value="<?php echo esc_attr( $this->filters['to'] ); ?>">
			<?php submit_button( __( 'Filter', 'oe-reservations' ), '', 'filter_action', false ); ?>
			<a class="button" href="<?php echo esc_url( ReservationsPage::export_url( $this->filters ) ); ?>"><?php esc_html_e( 'Export CSV', 'oe-reservations' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<label class="screen-reader-text" for="oe-cb-%1$d">%2$s</label><input type="checkbox" id="oe-cb-%1$d" name="ids[]" value="%1$d">',
			(int) $item['id'],
			/* translators: %s: guest name. */
			esc_html( sprintf( __( 'Select reservation of %s', 'oe-reservations' ), $item['name'] ) )
		);
	}

	/**
	 * Date column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_when( array $item ): string {
		$timestamp = ( new \DateTimeImmutable( $item['date'] . ' ' . $item['time'], wp_timezone() ) )->getTimestamp();

		return sprintf(
			'<strong>%s</strong><br><span class="oe-time">%s</span> <span class="oe-ref">%s</span>',
			esc_html( wp_date( 'D j M Y', $timestamp ) ),
			esc_html( wp_date( (string) get_option( 'time_format', 'H:i' ), $timestamp ) ),
			esc_html( BookingService::reference( (int) $item['id'] ) )
		);
	}

	/**
	 * Guest column, with row actions.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_name( array $item ): string {
		$out = sprintf(
			'<strong>%s</strong><br><a href="mailto:%2$s">%2$s</a><br><a href="tel:%3$s">%4$s</a>',
			esc_html( $item['name'] ),
			esc_attr( $item['email'] ),
			esc_attr( preg_replace( '/[^0-9+]/', '', $item['phone'] ) ),
			esc_html( $item['phone'] )
		);

		return $out . $this->row_actions( ReservationsPage::row_actions( $item ) );
	}

	/**
	 * Covers column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_guests( array $item ): string {
		return '<span class="oe-covers">' . (int) $item['guests'] . '</span>';
	}

	/**
	 * Notes column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_notes( array $item ): string {
		return '' === $item['notes'] ? '<span aria-hidden="true">—</span>' : esc_html( wp_trim_words( $item['notes'], 18 ) );
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_status( array $item ): string {
		return ReservationsPage::status_badge( (string) $item['status'] );
	}

	/**
	 * Created column.
	 *
	 * @param array<string, mixed> $item Reservation.
	 * @return string
	 */
	protected function column_created_at( array $item ): string {
		$timestamp = strtotime( $item['created_at'] . ' UTC' );

		return sprintf(
			'%s<br><span class="oe-muted">%s</span>',
			esc_html( wp_date( 'j M, H:i', $timestamp ) ),
			esc_html( 'web' === $item['source'] ? __( 'Online', 'oe-reservations' ) : ucfirst( $item['source'] ) )
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No reservations match these filters.', 'oe-reservations' );
	}
}
