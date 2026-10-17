<?php
/**
 * Rate limiting.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window counter per client and bucket, stored in transients.
 *
 * The client IP is only kept as a salted hash.
 */
final class RateLimiter {

	/**
	 * Counts a hit and tells whether the client is over the limit.
	 *
	 * @param string $bucket  Bucket name.
	 * @param int    $limit   Allowed hits per window.
	 * @param int    $window  Window length in seconds.
	 * @return int Seconds to wait (0 when allowed).
	 */
	public static function hit( string $bucket, int $limit, int $window ): int {
		/**
		 * Filters the limit of a rate-limit bucket (0 disables it).
		 *
		 * @param int    $limit  Hits per window.
		 * @param string $bucket Bucket.
		 */
		$limit = (int) apply_filters( 'oe_rate_limit', $limit, $bucket );
		if ( $limit <= 0 ) {
			return 0;
		}

		$key   = 'oe_rl_' . $bucket . '_' . substr( hash_hmac( 'sha256', self::client_ip(), wp_salt( 'nonce' ) ), 0, 24 );
		$now   = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) || $state['reset'] <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + $window,
			);
		}

		++$state['count'];
		set_transient( $key, $state, max( 1, $state['reset'] - $now ) );

		return $state['count'] > $limit ? max( 1, $state['reset'] - $now ) : 0;
	}

	/**
	 * Client IP. REMOTE_ADDR only: forwarded headers are trivially spoofed.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the client IP used for rate limiting (e.g. behind a trusted proxy).
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'oe_client_ip', $ip );
	}
}
