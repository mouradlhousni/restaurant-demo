<?php
/**
 * Self-service cancellation.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Reservations;

use OliveEmber\Reservations\Email\Mailer;

defined( 'ABSPATH' ) || exit;

/**
 * Cancellation through the secure link sent by email.
 *
 * - The link carries the reservation ID and a random 256-bit token; only an HMAC of the
 *   token is stored, and it is compared in constant time.
 * - Opening the link (GET) never changes anything: it shows the reservation and a button.
 *   The POST that cancels is nonce-protected, so link scanners and prefetchers are harmless.
 * - Links are disabled in demo mode.
 */
final class Cancellation {

	private const ACTION = 'oe_cancel_reservation';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Cancellation link.
	 *
	 * @param int    $id    Reservation ID.
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function url( int $id, string $token ): string {
		return add_query_arg(
			array(
				'oe_cancel' => $id,
				'token'     => $token,
			),
			oe_reservation_page_url()
		);
	}

	/**
	 * Whether the current request is a cancellation link or its result.
	 *
	 * @return bool
	 */
	public static function is_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the link parameters.
		return isset( $_GET['oe_cancel'] ) || isset( $_GET['oe_cancelled'] );
	}

	/**
	 * Panel shown instead of the booking form when a cancellation link is opened.
	 *
	 * @return string HTML.
	 */
	public static function render_panel(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET only displays; the POST is nonce-checked.
		if ( isset( $_GET['oe_cancelled'] ) ) {
			$result = sanitize_key( wp_unslash( $_GET['oe_cancelled'] ) );

			return '1' === $result
				? self::notice( __( 'Your reservation has been cancelled', 'oe-reservations' ), __( 'A confirmation has been sent by email. We hope to welcome you another time.', 'oe-reservations' ), 'success' )
				: self::notice( __( 'This reservation could not be cancelled', 'oe-reservations' ), self::error_message( $result ), 'error' );
		}

		if ( oe_is_demo_mode() ) {
			return self::notice( __( 'Cancellation links are disabled', 'oe-reservations' ), __( 'Demo mode: the form works, but no data is stored or sent.', 'oe-reservations' ), 'info' );
		}

		$id          = isset( $_GET['oe_cancel'] ) ? absint( $_GET['oe_cancel'] ) : 0;
		$token       = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
		$reservation = ReservationRepository::find_by_token( $id, $token );
		// phpcs:enable

		$error = self::cannot_cancel( $reservation );
		if ( $error ) {
			return self::notice( __( 'This reservation could not be cancelled', 'oe-reservations' ), self::error_message( $error ), 'error' );
		}

		ob_start();
		?>
		<section class="oe-cancel" aria-labelledby="oe-cancel-title">
			<h2 id="oe-cancel-title" class="oe-cancel__title"><?php esc_html_e( 'Cancel your reservation?', 'oe-reservations' ); ?></h2>
			<dl class="oe-summary">
				<div><dt><?php esc_html_e( 'Reference', 'oe-reservations' ); ?></dt><dd><?php echo esc_html( BookingService::reference( $reservation['id'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Date', 'oe-reservations' ); ?></dt><dd><?php echo esc_html( BookingService::format_datetime( $reservation['date'], $reservation['time'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Guests', 'oe-reservations' ); ?></dt><dd><?php echo esc_html( (string) $reservation['guests'] ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Name', 'oe-reservations' ); ?></dt><dd><?php echo esc_html( $reservation['name'] ); ?></dd></div>
			</dl>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $reservation['id'] ); ?>">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( self::ACTION . '_' . $reservation['id'] ); ?>
				<button type="submit" class="oe-button oe-button--danger"><?php esc_html_e( 'Yes, cancel my reservation', 'oe-reservations' ); ?></button>
				<a class="oe-button oe-button--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Keep it', 'oe-reservations' ); ?></a>
			</form>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handles the cancellation POST.
	 *
	 * @return void
	 */
	public function handle(): void {
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$back  = oe_reservation_page_url();

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::ACTION . '_' . $id ) ) {
			$this->redirect( $back, 'expired' );
		}
		if ( oe_is_demo_mode() ) {
			$this->redirect( $back, 'demo' );
		}

		$reservation = ReservationRepository::find_by_token( $id, $token );
		$error       = self::cannot_cancel( $reservation );
		if ( $error ) {
			$this->redirect( $back, $error );
		}

		ReservationRepository::update_status( array( $id ), ReservationRepository::STATUS_CANCELLED );
		$reservation['status'] = ReservationRepository::STATUS_CANCELLED;

		Mailer::customer( $reservation, 'cancelled' );
		Mailer::restaurant( $reservation, 'cancelled' );

		/**
		 * Fires after a guest cancelled a reservation from the email link.
		 *
		 * @param array<string, mixed> $reservation Reservation.
		 */
		do_action( 'oe_reservation_cancelled', $reservation );

		$this->redirect( $back, '1' );
	}

	/**
	 * Why a reservation cannot be cancelled, if it cannot.
	 *
	 * @param array<string, mixed>|null $reservation Reservation (null: bad link).
	 * @return string Error code, empty when it can be cancelled.
	 */
	private static function cannot_cancel( ?array $reservation ): string {
		if ( ! $reservation ) {
			return 'invalid';
		}
		if ( ! in_array( $reservation['status'], ReservationRepository::ACTIVE_STATUSES, true ) ) {
			return 'already';
		}

		$start = new \DateTimeImmutable( $reservation['date'] . ' ' . $reservation['time'], wp_timezone() );
		if ( $start <= new \DateTimeImmutable( 'now', wp_timezone() ) ) {
			return 'past';
		}

		return '';
	}

	/**
	 * Message for an error code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function error_message( string $code ): string {
		$messages = array(
			'invalid' => __( 'This cancellation link is invalid. Please use the link from your confirmation email, or call us.', 'oe-reservations' ),
			'already' => __( 'This reservation has already been cancelled.', 'oe-reservations' ),
			'past'    => __( 'This reservation is in the past.', 'oe-reservations' ),
			'expired' => __( 'This page has expired. Please open the link from your email again.', 'oe-reservations' ),
			'demo'    => __( 'Demo mode: the form works, but no data is stored or sent.', 'oe-reservations' ),
		);

		return $messages[ $code ] ?? $messages['invalid'];
	}

	/**
	 * Status message markup.
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @param string $type  success | error | info.
	 * @return string
	 */
	private static function notice( string $title, string $text, string $type ): string {
		return sprintf(
			'<div class="oe-notice oe-notice--%1$s" role="%2$s"><p class="oe-notice__title">%3$s</p><p>%4$s</p><p><a class="oe-button oe-button--ghost" href="%5$s">%6$s</a></p></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			esc_html( $title ),
			esc_html( $text ),
			esc_url( remove_query_arg( array( 'oe_cancel', 'token', 'oe_cancelled' ), oe_reservation_page_url() ) ),
			esc_html__( 'Book a table', 'oe-reservations' )
		);
	}

	/**
	 * Redirects back to the reservation page with a result code, then exits.
	 *
	 * @param string $url  Page URL.
	 * @param string $code Result.
	 * @return never
	 */
	private function redirect( string $url, string $code ) {
		wp_safe_redirect( add_query_arg( 'oe_cancelled', $code, $url ) );
		exit;
	}
}
