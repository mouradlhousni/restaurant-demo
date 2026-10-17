<?php
/**
 * Transactional emails.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Email;

use OliveEmber\Reservations\Reservations\BookingService;
use OliveEmber\Reservations\Reservations\Cancellation;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * HTML emails to the guest and to the restaurant.
 *
 * Templates use inline styles and tables only, which is what email clients render reliably.
 * Never sends anything in demo mode.
 */
final class Mailer {

	/**
	 * Email to the guest.
	 *
	 * @param array<string, mixed> $reservation Reservation.
	 * @param string               $type        received | confirmed | cancelled.
	 * @param string|null          $token       Raw cancellation token (only known right after booking).
	 * @return bool
	 */
	public static function customer( array $reservation, string $type, ?string $token = null ): bool {
		$name = (string) Settings::get( 'restaurant_name' );

		switch ( $type ) {
			case 'confirmed':
				/* translators: %s: restaurant name. */
				$subject = sprintf( __( 'Your table at %s is confirmed', 'oe-reservations' ), $name );
				$title   = __( 'See you soon!', 'oe-reservations' );
				$intro   = __( 'Your table is booked. Here are the details of your reservation:', 'oe-reservations' );
				break;
			case 'cancelled':
				/* translators: %s: restaurant name. */
				$subject = sprintf( __( 'Your reservation at %s has been cancelled', 'oe-reservations' ), $name );
				$title   = __( 'Reservation cancelled', 'oe-reservations' );
				$intro   = __( 'Your reservation has been cancelled. We hope to welcome you another time.', 'oe-reservations' );
				break;
			default:
				/* translators: %s: restaurant name. */
				$subject = sprintf( __( 'We have received your reservation request at %s', 'oe-reservations' ), $name );
				$title   = __( 'Request received', 'oe-reservations' );
				$intro   = __( 'Thank you! We will confirm your table by email shortly. Here is your request:', 'oe-reservations' );
		}

		$button = null;
		$after  = '';
		if ( $token && 'cancelled' !== $type ) {
			$button = array(
				'label' => __( 'Cancel my reservation', 'oe-reservations' ),
				'url'   => Cancellation::url( (int) $reservation['id'], $token ),
			);
			$after  = __( 'Plans changed? Use the button above to cancel, so we can offer your table to someone else.', 'oe-reservations' );
		}

		/* translators: %s: guest name. */
		$greeting = sprintf( __( 'Dear %s,', 'oe-reservations' ), $reservation['name'] );
		$html     = self::layout( $title, $greeting, $intro, self::summary_rows( $reservation ), $button, $after );

		return self::send( (string) $reservation['email'], $subject, $html, (string) Settings::get( 'contact_email' ) );
	}

	/**
	 * Email to the restaurant.
	 *
	 * @param array<string, mixed> $reservation Reservation.
	 * @param string               $type        new | cancelled.
	 * @return bool
	 */
	public static function restaurant( array $reservation, string $type ): bool {
		$when = BookingService::format_datetime( $reservation['date'], $reservation['time'] );

		if ( 'cancelled' === $type ) {
			/* translators: 1: guests, 2: date and time. */
			$subject = sprintf( __( 'Cancelled: %1$d guests, %2$s', 'oe-reservations' ), $reservation['guests'], $when );
			$title   = __( 'A guest cancelled', 'oe-reservations' );
		} else {
			/* translators: 1: guests, 2: date and time. */
			$subject = sprintf( __( 'New reservation: %1$d guests, %2$s', 'oe-reservations' ), $reservation['guests'], $when );
			$title   = __( 'New reservation', 'oe-reservations' );
		}

		$rows = self::summary_rows( $reservation ) + array(
			__( 'Email', 'oe-reservations' ) => $reservation['email'],
			__( 'Phone', 'oe-reservations' ) => $reservation['phone'],
		);
		if ( '' !== (string) $reservation['notes'] ) {
			$rows[ __( 'Notes', 'oe-reservations' ) ] = $reservation['notes'];
		}

		$button = array(
			'label' => __( 'Open in the dashboard', 'oe-reservations' ),
			'url'   => add_query_arg(
				array(
					'page'   => 'oe-reservations',
					'from'   => $reservation['date'],
					'to'     => $reservation['date'],
					'status' => '',
				),
				admin_url( 'admin.php' )
			),
		);

		$html = self::layout( $title, '', '', $rows, $button, '' );

		return self::send( (string) Settings::get( 'notification_email' ), $subject, $html, (string) $reservation['email'] );
	}

	/**
	 * Summary table rows.
	 *
	 * @param array<string, mixed> $reservation Reservation.
	 * @return array<string, string>
	 */
	private static function summary_rows( array $reservation ): array {
		$statuses = \OliveEmber\Reservations\Reservations\ReservationRepository::statuses();

		return array(
			__( 'Reference', 'oe-reservations' ) => BookingService::reference( (int) $reservation['id'] ),
			__( 'Date', 'oe-reservations' )      => BookingService::format_datetime( $reservation['date'], $reservation['time'] ),
			__( 'Guests', 'oe-reservations' )    => (string) $reservation['guests'],
			__( 'Name', 'oe-reservations' )      => $reservation['name'],
			__( 'Status', 'oe-reservations' )    => $statuses[ $reservation['status'] ] ?? $reservation['status'],
		);
	}

	/**
	 * Email layout.
	 *
	 * @param string                                 $title    Heading.
	 * @param string                                 $greeting Greeting line.
	 * @param string                                 $intro    Intro paragraph.
	 * @param array<string, string>                  $rows     Label => value.
	 * @param array{label: string, url: string}|null $button   Call to action.
	 * @param string                                 $after    Paragraph after the button.
	 * @return string
	 */
	private static function layout( string $title, string $greeting, string $intro, array $rows, ?array $button, string $after ): string {
		$name    = (string) Settings::get( 'restaurant_name' );
		$address = trim( Settings::get( 'street_address' ) . ', ' . Settings::get( 'postal_code' ) . ' ' . Settings::get( 'locality' ), ', ' );
		$phone   = (string) Settings::get( 'phone' );

		$table = '';
		foreach ( $rows as $label => $value ) {
			$table .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eadfcf;color:#7a6a58;font-size:14px;width:38%;vertical-align:top">' . esc_html( $label ) . '</td>'
				. '<td style="padding:10px 0;border-bottom:1px solid #eadfcf;color:#2b2420;font-size:15px;font-weight:600">' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
		}

		$cta = '';
		if ( $button ) {
			$cta = '<p style="margin:28px 0 8px"><a href="' . esc_url( $button['url'] ) . '" style="display:inline-block;background:#b5532f;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:999px">' . esc_html( $button['label'] ) . '</a></p>';
		}

		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?php echo esc_html( $title ); ?></title></head>
<body style="margin:0;padding:0;background:#f6efe4;font-family:Georgia,'Times New Roman',serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6efe4;padding:32px 12px">
	<tr><td align="center">
		<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fffaf2;border-radius:16px;overflow:hidden">
			<tr><td style="background:#2f3a2c;padding:24px 32px;color:#f6efe4;font-size:22px;letter-spacing:.04em"><?php echo esc_html( $name ); ?></td></tr>
			<tr><td style="padding:32px;font-family:Helvetica,Arial,sans-serif;color:#2b2420;line-height:1.55">
				<h1 style="margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;font-weight:normal;font-size:28px;color:#2f3a2c"><?php echo esc_html( $title ); ?></h1>
				<?php if ( $greeting ) : ?>
					<p style="margin:0 0 8px"><?php echo esc_html( $greeting ); ?></p>
				<?php endif; ?>
				<?php if ( $intro ) : ?>
					<p style="margin:0 0 16px"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><?php echo $table; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></table>
				<?php echo $cta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>
				<?php if ( $after ) : ?>
					<p style="margin:8px 0 0;color:#7a6a58;font-size:14px"><?php echo esc_html( $after ); ?></p>
				<?php endif; ?>
			</td></tr>
			<tr><td style="padding:20px 32px;background:#efe4d3;font-family:Helvetica,Arial,sans-serif;color:#7a6a58;font-size:13px">
				<?php echo esc_html( $name ); ?> · <?php echo esc_html( $address ); ?> · <?php echo esc_html( $phone ); ?>
			</td></tr>
		</table>
	</td></tr>
</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Sends an HTML email (never in demo mode).
	 *
	 * @param string $to       Recipient.
	 * @param string $subject  Subject.
	 * @param string $html     Body.
	 * @param string $reply_to Reply-To address.
	 * @return bool
	 */
	private static function send( string $to, string $subject, string $html, string $reply_to ): bool {
		if ( oe_is_demo_mode() || ! is_email( $to ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return wp_mail( $to, wp_specialchars_decode( $subject, ENT_QUOTES ), $html, $headers );
	}
}
