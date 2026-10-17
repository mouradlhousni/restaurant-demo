<?php
/**
 * "Opening Hours" block markup.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Blocks;

use OliveEmber\Reservations\Availability\BookingRules;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side render of the hours in four variants (table, compact, strip, badge alone).
 * The markup is complete without JavaScript; view.js then keeps the "Open now" badge
 * and the timeline marker live, computing everything from the embedded schedule (which also works in the static export).
 */
final class OpeningHoursView {

	/** Timeline range of the "strip" variant (minutes). */
	private const STRIP_FROM = 600;  // 10:00, when the fire is lit.
	private const STRIP_TO   = 1440; // Midnight.

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$variant = in_array( $attributes['variant'] ?? 'table', array( 'table', 'compact', 'strip', 'badge' ), true ) ? $attributes['variant'] : 'table';
		$badge   = ! isset( $attributes['showBadge'] ) || $attributes['showBadge'];
		$now     = new \DateTimeImmutable( 'now', wp_timezone() );
		$engine  = Settings::availability();
		$status  = $engine->status_at( $now );
		$days    = Settings::opening_hours();
		$today   = BookingRules::DAYS[ (int) $now->format( 'N' ) - 1 ];

		$config = array(
			'schedule' => Settings::public_schedule(),
			'from'     => self::STRIP_FROM,
			'to'       => self::STRIP_TO,
			'days'     => array_column( $days, 'label', 'key' ),
			'i18n'     => array(
				'open'      => __( 'Open now', 'oe-reservations' ),
				'closed'    => __( 'Closed now', 'oe-reservations' ),
				/* translators: %s: closing time. */
				'until'     => __( 'until %s', 'oe-reservations' ),
				/* translators: %s: opening time. */
				'opensAt'   => __( 'opens at %s', 'oe-reservations' ),
				/* translators: 1: weekday, 2: opening time. */
				'opensDay'  => __( 'opens %1$s at %2$s', 'oe-reservations' ),
				'tomorrow'  => __( 'tomorrow', 'oe-reservations' ),
				'closedDay' => __( 'Closed today', 'oe-reservations' ),
			),
		);

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'         => 'oe-hours oe-hours--' . $variant,
				'data-oe-hours' => (string) wp_json_encode( $config ),
			)
		);

		ob_start();
		echo '<div ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.

		if ( $badge || 'badge' === $variant ) {
			echo self::badge( $status, $now ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
		}

		if ( 'strip' === $variant ) {
			echo self::strip( $days, $today, $now ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in strip().
		} elseif ( 'compact' === $variant ) {
			echo self::compact( $days ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in compact().
		} elseif ( 'table' === $variant ) {
			echo self::table( $days, $today ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in table().
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * "Open now · until 22:30" badge.
	 *
	 * @param array{open: bool, change: \DateTimeImmutable|null} $status Current status.
	 * @param \DateTimeImmutable                                 $now    Now.
	 * @return string
	 */
	private static function badge( array $status, \DateTimeImmutable $now ): string {
		$detail = '';
		if ( $status['change'] ) {
			$time = $status['change']->format( 'H:i' );
			if ( $status['open'] ) {
				/* translators: %s: closing time. */
				$detail = sprintf( __( 'until %s', 'oe-reservations' ), $time );
			} elseif ( $status['change']->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
				/* translators: %s: opening time. */
				$detail = sprintf( __( 'opens at %s', 'oe-reservations' ), $time );
			} else {
				/* translators: 1: weekday, 2: opening time. */
				$detail = sprintf( __( 'opens %1$s at %2$s', 'oe-reservations' ), wp_date( 'l', $status['change']->getTimestamp() ), $time );
			}
		}

		return sprintf(
			'<p class="oe-open-badge" data-state="%1$s"><span class="oe-open-badge__dot" aria-hidden="true"></span><strong class="oe-open-badge__state">%2$s</strong> <span class="oe-open-badge__detail">%3$s</span></p>',
			$status['open'] ? 'open' : 'closed',
			esc_html( $status['open'] ? __( 'Open now', 'oe-reservations' ) : __( 'Closed now', 'oe-reservations' ) ),
			esc_html( $detail ? '· ' . $detail : '' )
		);
	}

	/**
	 * Full week table.
	 *
	 * @param array<int, array<string, mixed>> $days  Days.
	 * @param string                           $today Today's key.
	 * @return string
	 */
	private static function table( array $days, string $today ): string {
		$rows = '';
		foreach ( $days as $day ) {
			$is_today = $day['key'] === $today;
			$rows    .= sprintf(
				'<tr data-day="%1$s"%2$s><th scope="row">%3$s%4$s</th><td>%5$s</td></tr>',
				esc_attr( $day['key'] ),
				$is_today ? ' class="is-today"' : '',
				esc_html( $day['label'] ),
				$is_today ? ' <span class="oe-hours__today">' . esc_html__( 'Today', 'oe-reservations' ) . '</span>' : '',
				self::periods_html( $day['periods'] )
			);
		}

		$closures = self::upcoming_closures();
		$note     = '';
		if ( $closures ) {
			$note = '<p class="oe-hours__closures">' . esc_html(
				/* translators: %s: list of dates. */
				sprintf( __( 'Exceptionally closed: %s.', 'oe-reservations' ), implode( ', ', $closures ) )
			) . '</p>';
		}

		return '<table class="oe-hours__table"><caption class="screen-reader-text">' . esc_html__( 'Opening hours', 'oe-reservations' ) . '</caption><tbody>' . $rows . '</tbody></table>' . $note;
	}

	/**
	 * Consecutive days with the same hours grouped: "Tue – Thu".
	 *
	 * @param array<int, array<string, mixed>> $days Days.
	 * @return string
	 */
	private static function compact( array $days ): string {
		$groups = array();
		foreach ( $days as $day ) {
			$key  = wp_json_encode( $day['periods'] );
			$last = count( $groups ) - 1;
			if ( $last >= 0 && $groups[ $last ]['key'] === $key ) {
				$groups[ $last ]['to'] = $day;
			} else {
				$groups[] = array(
					'key'  => $key,
					'from' => $day,
					'to'   => $day,
				);
			}
		}

		$items = '';
		foreach ( $groups as $group ) {
			$label  = $group['from']['key'] === $group['to']['key']
				? $group['from']['label']
				: $group['from']['short'] . ' – ' . $group['to']['short'];
			$items .= sprintf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html( $label ), self::periods_html( $group['from']['periods'] ) );
		}

		return '<dl class="oe-hours__list">' . $items . '</dl>';
	}

	/**
	 * Today's services on a timeline, with an ember marking the current time.
	 *
	 * @param array<int, array<string, mixed>> $days  Days.
	 * @param string                           $today Today's key.
	 * @param \DateTimeImmutable               $now   Now.
	 * @return string
	 */
	private static function strip( array $days, string $today, \DateTimeImmutable $now ): string {
		$span    = self::STRIP_TO - self::STRIP_FROM;
		$periods = Settings::availability()->periods_for( $now->format( 'Y-m-d' ) );
		$label   = array_column( $days, 'label', 'key' )[ $today ];

		$segments = '';
		foreach ( $periods as $period ) {
			$from      = max( self::STRIP_FROM, $period[0] );
			$to        = min( self::STRIP_TO, $period[1] );
			$name      = $period[0] < 1020 ? __( 'Lunch', 'oe-reservations' ) : __( 'Dinner', 'oe-reservations' );
			$segments .= sprintf(
				'<li class="oe-strip__service" style="left:%1$.3f%%;width:%2$.3f%%"><span class="oe-strip__name">%3$s</span> <span class="oe-strip__time">%4$s–%5$s</span></li>',
				( $from - self::STRIP_FROM ) / $span * 100,
				max( 0, $to - $from ) / $span * 100,
				esc_html( $name ),
				esc_html( BookingRules::format_minutes( $period[0] ) ),
				esc_html( BookingRules::format_minutes( $period[1] ) )
			);
		}

		$ticks = '';
		for ( $hour = 12; $hour <= 24; $hour += 3 ) {
			$ticks .= sprintf( '<span style="left:%.3f%%">%s</span>', ( $hour * 60 - self::STRIP_FROM ) / $span * 100, esc_html( sprintf( '%02d:00', $hour % 24 ) ) );
		}

		$minutes = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
		$ember   = min( 100, max( 0, ( $minutes - self::STRIP_FROM ) / $span * 100 ) );

		return sprintf(
			'<div class="oe-strip">
				<p class="oe-strip__day"><span>%1$s</span> %2$s</p>
				<div class="oe-strip__track">
					<ol class="oe-strip__services" aria-label="%3$s">%4$s</ol>
					<span class="oe-strip__ember" style="left:%5$.3f%%" aria-hidden="true"></span>
					<div class="oe-strip__ticks" aria-hidden="true">%6$s</div>
				</div>
				%7$s
			</div>',
			esc_html__( 'Today', 'oe-reservations' ),
			esc_html( $label ),
			esc_attr__( "Today's services", 'oe-reservations' ),
			$segments,
			$ember,
			$ticks,
			$periods ? '' : '<p class="oe-strip__closed">' . esc_html__( 'Closed today', 'oe-reservations' ) . '</p>'
		);
	}

	/**
	 * "12:00–14:30 · 19:00–22:30" or "Closed".
	 *
	 * @param array<int, array{0: string, 1: string}> $periods Periods.
	 * @return string HTML.
	 */
	private static function periods_html( array $periods ): string {
		if ( ! $periods ) {
			return '<span class="oe-hours__closed">' . esc_html__( 'Closed', 'oe-reservations' ) . '</span>';
		}

		return implode(
			'<span class="oe-hours__sep" aria-hidden="true"> · </span><span class="screen-reader-text">, </span>',
			array_map(
				static fn( array $p ): string => '<time>' . esc_html( $p[0] ) . '</time>–<time>' . esc_html( $p[1] ) . '</time>',
				$periods
			)
		);
	}

	/**
	 * Exceptional closures within the next 30 days, formatted.
	 *
	 * @return array<int, string>
	 */
	private static function upcoming_closures(): array {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$limit = $today->modify( '+30 days' )->format( 'Y-m-d' );
		$list  = array();

		foreach ( array_keys( Settings::rules()->closures ) as $date ) {
			if ( $date >= $today->format( 'Y-m-d' ) && $date <= $limit ) {
				$list[] = wp_date( 'D j M', ( new \DateTimeImmutable( $date, wp_timezone() ) )->getTimestamp() );
			}
		}

		return $list;
	}
}
