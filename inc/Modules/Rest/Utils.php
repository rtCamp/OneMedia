<?php
/**
 * Utils class that holds shared functionalities used in Rest Controllers and elsewhere.
 *
 * @package OneMedia;
 */

namespace OneMedia\Modules\Rest;

use OneMedia\Modules\Settings\Settings;
use OneMedia\Modules\Taxonomies\Term_Restriction;

/**
 * Class Admin
 */
class Utils {

	/**
	 * OneMedia sync sites postmeta key.
	 *
	 * @var string
	 */
	public const ONEMEDIA_SYNC_SITES_POSTMETA_KEY = 'onemedia_sync_sites';

	/**
	 * Is OneMedia sync postmeta key.
	 *
	 * @var string
	 */
	public const IS_ONEMEDIA_SYNC_POSTMETA_KEY = 'is_onemedia_sync';

	/**
	 * OneMedia sync status postmeta key.
	 *
	 * @var string
	 */
	public const ONEMEDIA_SYNC_STATUS_POSTMETA_KEY = 'onemedia_sync_status';

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
	 * Allowed mime types array.
	 *
	 * This is a list of potentially supported mime types, any unsupported mime types will
	 * be removed during usage, on that particular server.
	 *
	 * @var array
	 */
	private const ALLOWED_MIME_TYPES = [
		'image/jpg',
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/bmp',
		'image/svg+xml',
	];

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
	 * Get supported mime types.
	 *
	 * @return array Array of supported mime types by the server.
	 */
	public static function get_supported_mime_types(): array {
		$allowed_types = self::ALLOWED_MIME_TYPES;

		// Remove any types that are not supported by the server.
		$supported_types = array_values( get_allowed_mime_types() );
		$allowed_types   = array_intersect( $allowed_types, $supported_types );

		return $allowed_types;
	}

	/**
	 * Decode filename to handle special characters.
	 *
	 * @param string $filename The filename to decode.
	 *
	 * @return string The decoded filename.
	 */
	public static function decode_filename( string $filename ): string {
		return html_entity_decode( $filename, ENT_QUOTES, 'UTF-8' );
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
	 * Get OneMedia attachment terms.
	 *
	 * @param int|\WP_Post $attachment_id The attachment ID.
	 *
	 * @return array Array of terms.
	 */
	public static function get_onemedia_attachment_terms( int|\WP_Post $attachment_id ): array {
		if ( ! $attachment_id ) {
			return [];
		}

		$terms = get_the_terms( $attachment_id, Term_Restriction::ONEMEDIA_PLUGIN_TAXONOMY );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return [];
		}
		return $terms;
	}

	/**
	 * Get OneMedia sync site URLs postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync site URLs.
	 */
	private function get_sync_site_urls_postmeta( int $attachment_id ): array {
		$sites = self::get_sync_sites_postmeta( $attachment_id );
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

	/**
	 * Get OneMedia sync sites postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync sites.
	 */
	private static function get_sync_sites_postmeta( int $attachment_id ): array {
		if ( ! Settings::is_governing_site() || ! $attachment_id ) {
			return [];
		}

		$sites = get_post_meta( $attachment_id, self::ONEMEDIA_SYNC_SITES_POSTMETA_KEY, true );
		if ( ! is_array( $sites ) ) {
			return [];
		}
		return $sites;
	}
}
