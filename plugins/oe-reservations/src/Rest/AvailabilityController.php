<?php
/**
 * GET oe/v1/availability.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Rest;

use OliveEmber\Reservations\Availability\AvailabilityService;
use OliveEmber\Reservations\Reservations\BookingService;
use OliveEmber\Reservations\Reservations\ReservationRepository;
use OliveEmber\Reservations\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Bookable slots of a day for a party size. Read-only: stays available in demo mode.
 */
final class AvailabilityController {

	public const NAMESPACE = 'oe/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/availability',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'date'   => array(
						'description' => __( 'Date (YYYY-MM-DD), in the restaurant timezone.', 'oe-reservations' ),
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'required'    => true,
					),
					'guests' => array(
						'description' => __( 'Party size.', 'oe-reservations' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 50,
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Public endpoint, rate limited.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		$wait = RateLimiter::hit( 'availability', 120, 600 );

		return $wait ? ReservationsController::too_many_requests( $wait ) : true;
	}

	/**
	 * Response.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$date   = (string) $request['date'];
		$guests = (int) $request['guests'];
		$now    = new \DateTimeImmutable( 'now', wp_timezone() );
		$engine = Settings::availability();

		$status = $engine->day_status( $date, $guests, $now );
		$day    = AvailabilityService::DAY_OPEN === $status
			? $engine->day( $date, $guests, ReservationRepository::active_for_date( $date ), $now )
			: array(
				'date'   => $date,
				'status' => $status,
				'slots'  => array(),
			);

		$slots = array_map(
			static fn( array $slot ): array => array(
				'time'      => $slot['time'],
				'label'     => wp_date( get_option( 'time_format', 'H:i' ), ( new \DateTimeImmutable( $date . ' ' . $slot['time'], wp_timezone() ) )->getTimestamp() ),
				'available' => $slot['available'],
				'reason'    => $slot['reason'],
				'period'    => (int) substr( $slot['time'], 0, 2 ) < 17 ? 'lunch' : 'dinner',
			),
			$day['slots']
		);

		$open    = array_filter( $slots, static fn( array $slot ): bool => $slot['available'] );
		$message = '';
		if ( AvailabilityService::DAY_OPEN !== $status ) {
			$message = BookingService::reason_message( $status );
		} elseif ( ! $open ) {
			$message = __( 'Sorry, we are fully booked for this party size on this day. Please try another date.', 'oe-reservations' );
		}

		$response = new \WP_REST_Response(
			array(
				'date'      => $date,
				'guests'    => $guests,
				'status'    => $status,
				'available' => (bool) $open,
				'message'   => $message,
				'slots'     => $slots,
			)
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
