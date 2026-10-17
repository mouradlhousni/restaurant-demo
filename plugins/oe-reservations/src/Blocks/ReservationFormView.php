<?php
/**
 * "Reservation Form" block markup.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Blocks;

use OliveEmber\Reservations\Reservations\Cancellation;
use OliveEmber\Reservations\Rest\ReservationsController;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-step booking form: date → guests → time → details → confirmation.
 *
 * The HTML holds every step (fieldsets) so the structure is accessible from the start;
 * view.js drives the steps, asks the availability API in real time and submits.
 * Opening a cancellation link shows the cancellation panel instead; the static export
 * shows a non-interactive preview with a link to the live Playground demo.
 */
final class ReservationFormView {

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'oe-booking-form' ) );

		if ( Cancellation::is_request() ) {
			return '<div ' . $wrapper . '>' . Cancellation::render_panel() . '</div>';
		}

		$heading = trim( (string) ( $attributes['heading'] ?? '' ) );
		$heading = '' !== $heading ? $heading : __( 'Book a table', 'oe-reservations' );

		if ( oe_is_static_build() ) {
			return '<div ' . $wrapper . '>' . self::static_preview( $heading ) . '</div>';
		}
		$rules = Settings::rules();
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$phone = (string) Settings::get( 'phone' );

		$config = array(
			'api'      => esc_url_raw( rest_url( 'oe/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'demo'     => oe_is_demo_mode(),
			'maxParty' => $rules->max_party,
			'minDate'  => $today->format( 'Y-m-d' ),
			'maxDate'  => $today->modify( '+' . $rules->max_advance_days . ' days' )->format( 'Y-m-d' ),
			'schedule' => Settings::public_schedule(),
			'locale'   => str_replace( '_', '-', get_locale() ),
			'i18n'     => array(
				'today'        => __( 'Today', 'oe-reservations' ),
				'tomorrow'     => __( 'Tomorrow', 'oe-reservations' ),
				'closed'       => __( 'Closed', 'oe-reservations' ),
				'guest'        => __( '1 guest', 'oe-reservations' ),
				/* translators: %d: number of guests. */
				'guests'       => __( '%d guests', 'oe-reservations' ),
				'lunch'        => __( 'Lunch', 'oe-reservations' ),
				'dinner'       => __( 'Dinner', 'oe-reservations' ),
				'full'         => __( 'Full', 'oe-reservations' ),
				'tooSoon'      => __( 'Too soon', 'oe-reservations' ),
				'loading'      => __( 'Checking availability…', 'oe-reservations' ),
				'noSlots'      => __( 'No table left for this party size on this day. Please try another date.', 'oe-reservations' ),
				'pickDate'     => __( 'Please choose a date.', 'oe-reservations' ),
				'pickGuests'   => __( 'Please choose the number of guests.', 'oe-reservations' ),
				'pickTime'     => __( 'Please choose a time.', 'oe-reservations' ),
				'network'      => __( 'We could not reach the booking service. Check your connection and try again.', 'oe-reservations' ),
				'sending'      => __( 'Booking your table…', 'oe-reservations' ),
				'required'     => __( 'This field is required.', 'oe-reservations' ),
				'nameShort'    => __( 'Please enter your name.', 'oe-reservations' ),
				'emailInvalid' => __( 'Please enter a valid email address, e.g. name@example.com.', 'oe-reservations' ),
				'phoneInvalid' => __( 'Please enter a phone number we can reach you on, e.g. +33 6 12 34 56 78.', 'oe-reservations' ),
				/* translators: %d: number of errors. */
				'errorCount'   => __( 'Please correct %d field(s) before continuing.', 'oe-reservations' ),
				/* translators: 1: step number, 2: number of steps, 3: step name. */
				'stepOf'       => __( 'Step %1$d of %2$d: %3$s', 'oe-reservations' ),
				'doneTitle'    => __( 'You are booked!', 'oe-reservations' ),
				'donePending'  => __( 'Request received', 'oe-reservations' ),
				'doneDemo'     => __( 'Booking flow complete (demo)', 'oe-reservations' ),
				'reference'    => __( 'Reference', 'oe-reservations' ),
			),
		);

		$steps = array(
			'date'    => __( 'Date', 'oe-reservations' ),
			'guests'  => __( 'Guests', 'oe-reservations' ),
			'time'    => __( 'Time', 'oe-reservations' ),
			'details' => __( 'Details', 'oe-reservations' ),
			'confirm' => __( 'Confirm', 'oe-reservations' ),
		);

		ob_start();
		?>
		<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
			<div class="oe-booking" data-oe-booking="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>">
				<h2 class="oe-booking__title"><?php echo esc_html( $heading ); ?></h2>

				<ol class="oe-progress" aria-label="<?php esc_attr_e( 'Booking steps', 'oe-reservations' ); ?>">
					<?php foreach ( array_values( $steps ) as $index => $label ) : ?>
						<li class="oe-progress__step" <?php echo 0 === $index ? 'aria-current="step"' : ''; ?>><span class="oe-progress__num" aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span> <span class="oe-progress__label"><?php echo esc_html( $label ); ?></span></li>
					<?php endforeach; ?>
				</ol>

				<noscript>
					<p class="oe-notice oe-notice--info">
						<?php
						/* translators: %s: phone number. */
						echo esc_html( sprintf( __( 'Online booking needs JavaScript. You can also book by phone: %s.', 'oe-reservations' ), $phone ) );
						?>
					</p>
					<style>.oe-booking__form { display: none; }</style>
				</noscript>

				<form class="oe-booking__form" novalidate>
					<div class="oe-booking__alert" role="alert" tabindex="-1" hidden></div>

					<fieldset class="oe-step" data-step="date">
						<legend class="oe-step__title" tabindex="-1"><?php esc_html_e( 'When would you like to come?', 'oe-reservations' ); ?></legend>
						<div class="oe-dates" role="radiogroup" aria-label="<?php esc_attr_e( 'Next days', 'oe-reservations' ); ?>"><?php echo self::date_tiles( $today, $rules->max_advance_days ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in date_tiles(). ?></div>
						<p class="oe-field oe-field--inline">
							<label for="oe-date-other"><?php esc_html_e( 'Another date', 'oe-reservations' ); ?></label>
							<input type="date" id="oe-date-other" name="date_other" min="<?php echo esc_attr( $config['minDate'] ); ?>" max="<?php echo esc_attr( $config['maxDate'] ); ?>" aria-describedby="oe-date-help">
						</p>
						<p class="oe-help" id="oe-date-help">
							<?php
							/* translators: %d: number of days. */
							echo esc_html( sprintf( __( 'Bookings open %d days ahead.', 'oe-reservations' ), $rules->max_advance_days ) );
							?>
						</p>
						<p class="oe-field-error" id="oe-date-error" hidden></p>
					</fieldset>

					<fieldset class="oe-step" data-step="guests" hidden>
						<legend class="oe-step__title" tabindex="-1"><?php esc_html_e( 'How many guests?', 'oe-reservations' ); ?></legend>
						<div class="oe-choices oe-choices--guests">
							<?php for ( $i = 1; $i <= $rules->max_party; $i++ ) : ?>
								<label class="oe-choice"><input type="radio" name="guests" value="<?php echo esc_attr( (string) $i ); ?>" <?php checked( 2, $i ); ?>><span><?php echo esc_html( (string) $i ); ?></span></label>
							<?php endfor; ?>
						</div>
						<p class="oe-help">
							<?php
							/* translators: 1: largest party size, 2: phone number. */
							echo esc_html( sprintf( __( 'More than %1$d guests? Call us on %2$s and we will set up a table.', 'oe-reservations' ), $rules->max_party, $phone ) );
							?>
						</p>
					</fieldset>

					<fieldset class="oe-step" data-step="time" hidden>
						<legend class="oe-step__title" tabindex="-1"><?php esc_html_e( 'Choose a time', 'oe-reservations' ); ?></legend>
						<p class="oe-step__context" aria-live="polite"></p>
						<div class="oe-slots" aria-live="polite" aria-busy="false"></div>
						<p class="oe-field-error" id="oe-time-error" hidden></p>
					</fieldset>

					<fieldset class="oe-step" data-step="details" hidden>
						<legend class="oe-step__title" tabindex="-1"><?php esc_html_e( 'Your details', 'oe-reservations' ); ?></legend>
						<div class="oe-fields">
							<p class="oe-field">
								<label for="oe-name"><?php esc_html_e( 'Full name', 'oe-reservations' ); ?> <span class="oe-required" aria-hidden="true">*</span></label>
								<input type="text" id="oe-name" name="name" autocomplete="name" required maxlength="100" aria-describedby="oe-name-error">
								<span class="oe-field-error" id="oe-name-error" hidden></span>
							</p>
							<p class="oe-field">
								<label for="oe-email"><?php esc_html_e( 'Email', 'oe-reservations' ); ?> <span class="oe-required" aria-hidden="true">*</span></label>
								<input type="email" id="oe-email" name="email" autocomplete="email" required maxlength="190" aria-describedby="oe-email-help oe-email-error">
								<span class="oe-help" id="oe-email-help"><?php esc_html_e( 'Your confirmation and cancellation link are sent here.', 'oe-reservations' ); ?></span>
								<span class="oe-field-error" id="oe-email-error" hidden></span>
							</p>
							<p class="oe-field">
								<label for="oe-phone"><?php esc_html_e( 'Phone', 'oe-reservations' ); ?> <span class="oe-required" aria-hidden="true">*</span></label>
								<input type="tel" id="oe-phone" name="phone" autocomplete="tel" required maxlength="25" aria-describedby="oe-phone-error">
								<span class="oe-field-error" id="oe-phone-error" hidden></span>
							</p>
							<p class="oe-field oe-field--wide">
								<label for="oe-notes"><?php esc_html_e( 'Anything we should know?', 'oe-reservations' ); ?> <span class="oe-optional"><?php esc_html_e( '(optional)', 'oe-reservations' ); ?></span></label>
								<textarea id="oe-notes" name="notes" rows="3" maxlength="500" aria-describedby="oe-notes-help oe-notes-error"></textarea>
								<span class="oe-help" id="oe-notes-help"><?php esc_html_e( 'Allergies, an occasion, a high chair…', 'oe-reservations' ); ?></span>
								<span class="oe-field-error" id="oe-notes-error" hidden></span>
							</p>
							<p class="oe-hp" aria-hidden="true">
								<label for="oe-website"><?php esc_html_e( 'Leave this field empty', 'oe-reservations' ); ?></label>
								<input type="text" id="oe-website" name="<?php echo esc_attr( ReservationsController::HONEYPOT ); ?>" tabindex="-1" autocomplete="off">
							</p>
						</div>
						<p class="oe-help"><?php esc_html_e( 'Fields marked * are required.', 'oe-reservations' ); ?></p>
					</fieldset>

					<fieldset class="oe-step" data-step="confirm" hidden>
						<legend class="oe-step__title" tabindex="-1"><?php esc_html_e( 'Check and confirm', 'oe-reservations' ); ?></legend>
						<dl class="oe-summary"></dl>
						<p class="oe-help"><?php esc_html_e( 'By confirming, you agree that we keep these details to manage your booking. They are never shared.', 'oe-reservations' ); ?></p>
					</fieldset>

					<div class="oe-booking__nav">
						<button type="button" class="oe-button oe-button--ghost" data-action="back" hidden><?php esc_html_e( 'Back', 'oe-reservations' ); ?></button>
						<button type="submit" class="oe-button" data-action="next" data-confirm-label="<?php esc_attr_e( 'Confirm booking', 'oe-reservations' ); ?>"><?php esc_html_e( 'Continue', 'oe-reservations' ); ?></button>
					</div>
				</form>

				<section class="oe-booking__done" tabindex="-1" hidden aria-labelledby="oe-done-title">
					<p class="oe-booking__done-mark" aria-hidden="true"></p>
					<h3 id="oe-done-title" class="oe-booking__done-title"></h3>
					<p class="oe-booking__done-message"></p>
					<dl class="oe-summary"></dl>
					<button type="button" class="oe-button oe-button--ghost" data-action="restart"><?php esc_html_e( 'Make another booking', 'oe-reservations' ); ?></button>
				</section>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Next 14 days as radio tiles, closed days disabled. Rendered on the server so the
	 * first step has its final size before any script runs (no layout shift).
	 *
	 * @param \DateTimeImmutable $today       Today (restaurant timezone).
	 * @param int                $max_advance Booking window in days.
	 * @return string
	 */
	private static function date_tiles( \DateTimeImmutable $today, int $max_advance ): string {
		$engine = Settings::availability();
		$html   = '';

		$count = min( 14, $max_advance + 1 );
		for ( $i = 0; $i < $count; $i++ ) {
			$day    = $today->modify( "+$i days" );
			$date   = $day->format( 'Y-m-d' );
			$closed = ! $engine->periods_for( $date );
			$stamp  = $day->setTime( 12, 0 )->getTimestamp();

			if ( 0 === $i ) {
				$top = __( 'Today', 'oe-reservations' );
			} elseif ( 1 === $i ) {
				$top = __( 'Tomorrow', 'oe-reservations' );
			} else {
				$top = wp_date( 'D', $stamp );
			}

			$html .= sprintf(
				'<label class="oe-date%1$s"><input type="radio" name="date" value="%2$s" aria-label="%3$s"%4$s><span class="oe-date__top" aria-hidden="true">%5$s</span><span class="oe-date__day" aria-hidden="true">%6$s</span><span class="oe-date__month" aria-hidden="true">%7$s</span></label>',
				$closed ? ' is-closed' : '',
				esc_attr( $date ),
				esc_attr( wp_date( 'l j F', $stamp ) . ( $closed ? ' – ' . __( 'Closed', 'oe-reservations' ) : '' ) ),
				$closed ? ' disabled' : '',
				esc_html( $top ),
				esc_html( wp_date( 'j', $stamp ) ),
				esc_html( $closed ? __( 'Closed', 'oe-reservations' ) : wp_date( 'M', $stamp ) )
			);
		}

		return $html;
	}

	/**
	 * Non-interactive preview for the static export.
	 *
	 * @param string $heading Block heading.
	 * @return string
	 */
	private static function static_preview( string $heading ): string {
		$steps = array(
			__( 'Pick a day: closed days are greyed out.', 'oe-reservations' ),
			__( 'Choose how many guests.', 'oe-reservations' ),
			__( 'See the times still free, live from the availability API.', 'oe-reservations' ),
			__( 'Enter your name, email and phone.', 'oe-reservations' ),
			__( 'Confirm: an email with a cancellation link is sent.', 'oe-reservations' ),
		);

		$times = array(
			array( '19:00', true ),
			array( '19:30', true ),
			array( '20:00', false ),
			array( '20:30', true ),
			array( '21:00', true ),
			array( '21:30', false ),
		);

		ob_start();
		?>
		<div class="oe-booking oe-booking--static">
			<h2 class="oe-booking__title"><?php echo esc_html( $heading ); ?></h2>
			<p class="oe-notice oe-notice--info"><?php esc_html_e( 'This is a static preview of the site: the booking form needs the live WordPress demo.', 'oe-reservations' ); ?></p>
			<div class="oe-mockup" aria-hidden="true">
				<p class="oe-mockup__label"><?php esc_html_e( 'Choose a time', 'oe-reservations' ); ?> · <?php esc_html_e( 'Dinner', 'oe-reservations' ); ?></p>
				<div class="oe-slots">
					<?php foreach ( $times as $time ) : ?>
						<span class="oe-slot<?php echo $time[1] ? '' : ' is-full'; ?>"><?php echo esc_html( $time[0] ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
			<ol class="oe-static-steps">
				<?php foreach ( $steps as $step ) : ?>
					<li><?php echo esc_html( $step ); ?></li>
				<?php endforeach; ?>
			</ol>
			<p><a class="oe-button" href="<?php echo esc_url( oe_playground_url() ); ?>" rel="noopener"><?php esc_html_e( 'Try the booking flow live', 'oe-reservations' ); ?></a></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
