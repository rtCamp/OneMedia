<?php
/**
 * Utils class that holds shared functionalities used in Rest Controllers and elsewhere.
 *
 * @package OneMedia;
 */

namespace OneMedia\Modules\Rest;

use OneMedia\Modules\MediaSharing\Attachment;
use OneMedia\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Utils {

	/**
	 * Brand sites synced media option.
	 *
	 * @var string
	 */
	public const BRAND_SITES_SYNCED_MEDIA_OPTION = 'onemedia_brand_sites_synced_media';

	/**
	 * Health check request timeout.
	 *
	 * @var int
	 */
	private const HEALTH_CHECK_REQUEST_TIMEOUT = 15;

	/**
	 * Perform health check on brand sites where a given attachment is shared.
	 *
	 * @param int|null $attachment_id The attachment ID.
	 *
	 * @return array Array with 'success' boolean and 'failed_sites' array.
	 */
	public static function health_check_attachment_brand_sites( int|null $attachment_id ): array {
		if ( ! $attachment_id ) {
			return [
				'success'      => false,
				'failed_sites' => [],
				'message'      => __( 'Invalid attachment ID.', 'onemedia' ),
			];
		}

		// Get URLs of all sites where this attachment is shared.
		$site_urls = self::get_sync_site_urls_postmeta( $attachment_id );

		if ( empty( $site_urls ) ) {
			return [
				'success'      => true,
				'failed_sites' => [],
				'message'      => __( 'No connected brand sites for this attachment.', 'onemedia' ),
			];
		}

		$failed_sites = [];
		$tracked_urls = [];

		foreach ( $site_urls as $site_url ) {
			$site_url = rtrim( $site_url, '/' );

			// Skip if we've already processed this URL.
			if ( in_array( $site_url, $tracked_urls, true ) ) {
				continue;
			}

			$tracked_urls[] = $site_url;
			$api_key        = Settings::get_brand_site_api_key( $site_url );

			// Brand site not connected.
			if ( empty( $api_key ) ) {
				$failed_sites[] = [
					'site_name' => Settings::get_sitename_by_url( $site_url ),
					'url'       => $site_url,
					'message'   => __( 'API key not found', 'onemedia' ),
				];
				continue;
			}

			// Perform health check request.
			$response = wp_safe_remote_get(
				$site_url . '/wp-json/' . Abstract_REST_Controller::NAMESPACE . '/health-check',
				[
					'timeout' => self::HEALTH_CHECK_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'headers' => [
						'origin'           => get_site_url(),
						'X-OneMedia-Token' => $api_key,
						'Cache-Control'    => 'no-cache, no-store, must-revalidate',
					],
				]
			);

			if ( is_wp_error( $response ) ) {
				$failed_sites[] = [
					'site_name' => Settings::get_sitename_by_url( $site_url ),
					'url'       => $site_url,
					'message'   => $response->get_error_message(),
				];
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 === $response_code ) {
				continue;
			}

			$failed_sites[] = [
				'site_name' => Settings::get_sitename_by_url( $site_url ),
				'url'       => $site_url,
				'message'   => sprintf(
				/* translators: %d is the HTTP response code. */
					__( 'HTTP %d response', 'onemedia' ),
					$response_code
				),
			];
		}

		if ( ! empty( $failed_sites ) ) {
			$failed_sites_list = [];
			foreach ( $failed_sites as $failed_site ) {
				$site_name = $failed_site['site_name'];
				if ( in_array( $site_name, $failed_sites_list, true ) ) {
					continue;
				}

				$failed_sites_list[] = $site_name;
			}

			return [
				'success'      => false,
				'failed_sites' => $failed_sites,
				'message'      => sprintf(
				/* translators: %s is the list of unreachable sites. */
					__( 'Please check your connection for unreachable sites: %s.', 'onemedia' ),
					implode( ', ', $failed_sites_list )
				),
			];
		}

		return [
			'success'      => true,
			'failed_sites' => $failed_sites,
			'message'      => __( 'All connected sites are reachable.', 'onemedia' ),
		];
	}

	/**
	 * Get brand site's synced media array.
	 *
	 * The structure of this array is different on governing and brand sites.
	 *
	 * @return array Array of brand site's synced media.
	 */
	public static function fetch_brand_sites_synced_media(): array {
		return get_option( self::BRAND_SITES_SYNCED_MEDIA_OPTION, [] );
	}

	/**
	 * Get OneMedia sync site URLs postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync site URLs.
	 */
	private static function get_sync_site_urls_postmeta( int $attachment_id ): array {
		$sites = Attachment::get_sync_sites( $attachment_id );
		if ( empty( $sites ) ) {
			return [];
		}

		$site_urls = [];
		foreach ( $sites as $site ) {
			if ( ! isset( $site['site'] ) ) {
				continue;
			}

			$site_urls[] = untrailingslashit( esc_url_raw( $site['site'] ) );
		}
		return $site_urls;
	}
}
