<?php
/**
 * POST oe/v1/reservations.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Rest;

use OliveEmber\Reservations\Reservations\BookingService;

defined( 'ABSPATH' ) || exit;

/**
 * Online booking endpoint.
 *
 * Protection layers: REST nonce, honeypot field, per-IP rate limiting, strict validation,
 * and a transactional availability re-check at insert time.
 */
final class ReservationsController {

	/** Honeypot field: hidden from humans, filled by naive bots. */
	public const HONEYPOT = 'website';

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
		$string = static fn( string $description, bool $required = true ): array => array(
			'description' => $description,
			'type'        => 'string',
			'required'    => $required,
		);

		register_rest_route(
			AvailabilityController::NAMESPACE,
			'/reservations',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'date'         => $string( __( 'Date (YYYY-MM-DD).', 'oe-reservations' ) ),
					'time'         => $string( __( 'Time (HH:MM).', 'oe-reservations' ) ),
					'guests'       => array(
						'description' => __( 'Party size.', 'oe-reservations' ),
						'type'        => 'integer',
						'required'    => true,
					),
					'name'         => $string( __( 'Guest name.', 'oe-reservations' ) ),
					'email'        => $string( __( 'Guest email.', 'oe-reservations' ) ),
					'phone'        => $string( __( 'Guest phone.', 'oe-reservations' ) ),
					'notes'        => $string( __( 'Allergies, occasion…', 'oe-reservations' ), false ),
					self::HONEYPOT => $string( __( 'Leave empty.', 'oe-reservations' ), false ),
				),
			)
		);
	}

	/**
	 * Nonce and rate limit.
	 *
	 * Core only checks X-WP-Nonce for cookie-authenticated users, so it is verified here
	 * for anonymous visitors too.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( \WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'oe_invalid_nonce', __( 'Your session has expired. Please reload the page and try again.', 'oe-reservations' ), array( 'status' => 403 ) );
		}

		$wait = RateLimiter::hit( 'booking', 5, 900 );

		return $wait ? self::too_many_requests( $wait ) : true;
	}

	/**
	 * Books a table.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		// Bots that fill the honeypot get a believable answer and nothing is done.
		if ( '' !== trim( (string) $request->get_param( self::HONEYPOT ) ) ) {
			return new \WP_REST_Response(
				array(
					'demo'    => false,
					'message' => __( 'Thank you! Your request has been received.', 'oe-reservations' ),
				),
				201
			);
		}

		$result = BookingService::book( $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = new \WP_REST_Response( $result, $result['demo'] ? 200 : 201 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * 429 error.
	 *
	 * @param int $wait Seconds before retrying.
	 * @return \WP_Error
	 */
	public static function too_many_requests( int $wait ): \WP_Error {
		$minutes = max( 1, (int) ceil( $wait / 60 ) );

		return new \WP_Error(
			'oe_rate_limited',
			/* translators: %d: minutes. */
			sprintf( _n( 'Too many attempts. Please try again in %d minute.', 'Too many attempts. Please try again in %d minutes.', $minutes, 'oe-reservations' ), $minutes ),
			array(
				'status'      => 429,
				'retry_after' => $wait,
			)
		);
	}
}
