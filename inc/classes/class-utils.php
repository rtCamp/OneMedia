<?php
/**
 * Class Utils -- this is utils class to have common functions.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Traits\Singleton;

/**
 * Class Utils
 */
class Utils {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor.
	 */
	protected function __construct() { }

	/**
	 * Get the multisite type.
	 *
	 * @return string The multisite type (subdomain, subdirectory, or single).
	 */
	public static function get_multisite_type(): string {
		if ( is_multisite() ) {
			return is_subdomain_install() ? 'subdomain' : 'subdirectory';
		}
		return 'single';
	}

	/**
	 * Get the current site type.
	 *
	 * @return string The current site type (brand or governing).
	 */
	public static function get_current_site_type(): string {
		$onemedia_site_type = get_option( Constants::ONEMEDIA_SITE_TYPE_OPTION, '' );
		return $onemedia_site_type;
	}
	
	/**
	 * Get the governing site URL.
	 *
	 * @return string The governing site URL if the current site is a brand site, empty string otherwise.
	 */
	public static function get_governing_site_url(): string {
		if ( ! self::is_brand_site() ) {
			return '';
		}
		return get_option( Constants::ONEMEDIA_GOVERNING_SITES_URL_OPTION, '' );
	}

	/**
	 * Set the governing site URL.
	 *
	 * @param string $url The governing site URL to set.
	 *
	 * @return bool|\WP_Error True if the URL was set successfully, WP_Error on failure.
	 */
	public static function set_governing_site_url( string $url ): bool|\WP_Error {
		if ( ! $url || empty( $url ) || ! self::is_brand_site() ) {
			return new \WP_Error(
				'invalid_request',
				__( 'Invalid request to set governing site URL.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		$success = update_option( Constants::ONEMEDIA_GOVERNING_SITES_URL_OPTION, $url );

		if ( ! $success ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to save governing site URL.', 'onemedia' ),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		return true;
	}
	
	/**
	 * Get current site's OneMedia API key.
	 * 
	 * @param string $default_value Default value if empty.
	 *
	 * @return string The OneMedia API key.
	 */
	public static function get_onemedia_api_key( string $default_value = '' ): string {
		return get_option( Constants::ONEMEDIA_API_KEY_OPTION, $default_value );
	}

	/**
	 * Check if the current site is a brand site.
	 *
	 * @return bool True if the current site is a brand site, false otherwise.
	 */
	public static function is_brand_site(): bool {
		return hash_equals( ONEMEDIA_PLUGIN_BRAND_SITE, self::get_current_site_type() );
	}

	/**
	 * Check if the current site is a governing site.
	 *
	 * @return bool True if the current site is a governing site, false otherwise.
	 */
	public static function is_governing_site(): bool {
		return hash_equals( ONEMEDIA_PLUGIN_GOVERNING_SITE, self::get_current_site_type() );
	}

	/**
	 * Check if site type is set or not.
	 *
	 * @return bool True if site type is set, false otherwise.
	 */
	public static function is_site_type_set(): bool {
		$site_type = self::get_current_site_type();
		if ( ! empty( $site_type ) ) {
			// Site type is set.
			return true;
		}
		return false;
	}

	/**
	 * Check if an attachment is of sync type or not.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool True if the attachment is of sync type, false otherwise.
	 */
	public static function is_sync_attachment( int $attachment_id ): bool {
		if ( self::is_governing_site() ) {
			$sync = get_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, true );
		} elseif ( self::is_brand_site() ) {
			$sync_status = self::get_sync_status_postmeta( $attachment_id );
			$sync        = hash_equals( $sync_status, 'sync' );
		}

		if ( $sync ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Check if any of the brand sites are connected or not.
	 *
	 * @return bool True if any brand sites are connected, false otherwise.
	 */
	public static function has_brand_sites(): bool {
		if ( ! self::is_governing_site() ) {
			return false;
		}
		return ! empty( get_option( Constants::ONEMEDIA_BRAND_SITES_OPTION, array() ) );
	}
	
	/**
	 * Get connected brand sites.
	 *
	 * @return array Array of connected brand sites.
	 */
	public static function get_brand_sites(): array {
		if ( ! self::has_brand_sites() ) {
			return array();
		}
		return get_option( Constants::ONEMEDIA_BRAND_SITES_OPTION, array() );
	}

	/**
	 * Get brand site's synced media array.
	 * 
	 * The structure of this array is different on governing and brand sites.
	 *
	 * @return array Array of brand site's synced media.
	 */
	public static function get_brand_sites_synced_media(): array {
		return get_option( Constants::BRAND_SITES_SYNCED_MEDIA_OPTION, array() );
	}

	/**
	 * Get attachment key map.
	 * 
	 * This option contains the governing site to brand site attachment key map.
	 * It's used for checking if an attachment is already synced or not on the brand site.
	 *
	 * @return array The attachment key map array.
	 */
	public static function get_attachment_key_map(): array {
		if ( ! self::is_brand_site() ) {
			return array();
		}
		return get_option( Constants::ONEMEDIA_ATTACHMENT_KEY_MAP_OPTION, array() );
	}

	/**
	 * Get OneMedia sync status postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string The sync status value.
	 */
	public static function get_sync_status_postmeta( int $attachment_id ): string {
		if ( ! self::is_brand_site() || ! $attachment_id ) {
			return '';
		}
		return get_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, true );
	}

	/**
	 * Get OneMedia sync sites postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync sites.
	 */
	public static function get_sync_sites_postmeta( int $attachment_id ): array {
		if ( ! self::is_governing_site() || ! $attachment_id ) {
			return array();
		}

		$sites = get_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_SITES_POSTMETA_KEY, true );
		if ( ! is_array( $sites ) ) {
			return array();
		}
		return $sites;
	}

	/**
	 * Get OneMedia sync site URLs postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync site URLs.
	 */
	public static function get_sync_site_urls_postmeta( int $attachment_id ): array {
		$sites = self::get_sync_sites_postmeta( $attachment_id );
		if ( empty( $sites ) ) {
			return array();
		}

		$site_urls = array();
		foreach ( $sites as $site ) {
			if ( isset( $site['site'] ) ) {
				$site_urls[] = rtrim( esc_url_raw( $site['site'] ), '/' );
			}
		}
		return $site_urls;
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
			return array();
		}

		$terms = get_the_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Get OneMedia attachment terms with args.
	 *
	 * @param int|\WP_Post $attachment_id The attachment ID.
	 * @param array        $args          Arguments to pass to wp_get_post_terms function.
	 *
	 * @return array Array of terms.
	 */
	public static function get_onemedia_attachment_post_terms( int|\WP_Post $attachment_id, array $args = array() ): array {
		if ( ! $attachment_id ) {
			return array();
		}

		$terms = wp_get_post_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY, $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Get all brand sites.
	 *
	 * @return array Array of all brand sites with siteName, siteUrl and apiKey.
	 */
	public static function get_all_brand_sites(): array {
		$sites_value = self::get_brand_sites();
		$sites       = array();

		foreach ( $sites_value as $site ) {
			$site    = array(
				'siteName' => $site['siteName'],
				'siteUrl'  => $site['siteUrl'],
				'apiKey'   => $site['apiKey'],
			);
			$sites[] = $site;
		}

		return $sites;
	}

	/**
	 * Permission callback to check user capabilities.
	 *
	 * @return bool True if the user has required capability, false otherwise.
	 */
	public static function check_user_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get origin URL from header.
	 *
	 * @param array|null $server The server information.
	 *
	 * @return string|null The origin URL if found, null otherwise.
	 */
	public static function get_origin_url( array|null $server ): ?string {
		if ( ! is_array( $server ) ) {
			return null;
		}
		$http_origin = isset( $server['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $server['HTTP_ORIGIN'] ) ) : '';

		// Fallback to referer if origin is not set.
		if ( empty( $http_origin ) ) {
			$http_origin = isset( $server['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $server['HTTP_REFERER'] ) ) : '';
		}

		// Fallback to user agent if origin and referer are not set.
		if ( empty( $http_origin ) ) {
			$http_user_agent = isset( $server['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $server['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- this is to know requesting user domain for request which are generated from server.

			// Try to extract URL from user agent.
			preg_match( '/https?:\/\/[^ ]+/', $http_user_agent, $matches );
			if ( ! empty( $matches ) && isset( $matches[0] ) ) {
				$http_origin = esc_url_raw( $matches[0] );
			}
		}

		// Parse the URL to get only scheme and host.
		if ( ! empty( $http_origin ) ) {
			$parsed_origin = wp_parse_url( $http_origin );
			if ( ! empty( $parsed_origin['scheme'] ) && ! empty( $parsed_origin['host'] ) ) {
				$http_origin = $parsed_origin['scheme'] . '://' . $parsed_origin['host'] . '/';
			}
		}

		return ! empty( $http_origin ) ? $http_origin : null;
	}

	/**
	 * Check if two URLs belong to the same domain.
	 *
	 * @param string|null $url1 First URL.
	 * @param string|null $url2 Second URL.
	 *
	 * @return bool True if both URLs belong to the same domain, false otherwise.
	 */
	public static function is_same_domain( ?string $url1, ?string $url2 ): bool {
		if ( ! $url1 || ! $url2 || empty( $url1 ) || empty( $url2 ) ) {
			return false;
		}
		$parsed_url1 = wp_parse_url( $url1 );
		$parsed_url2 = wp_parse_url( $url2 );

		if ( ! isset( $parsed_url1['host'] ) || ! isset( $parsed_url2['host'] ) ) {
			return false;
		}
		return hash_equals( $parsed_url1['host'], $parsed_url2['host'] );
	}

	/**
	 * Check if a URL is valid or not.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL is valid, false otherwise.
	 */
	public static function is_valid_url( string $url ): bool {
		// Trim the URL up to the domain part for validation.
		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['scheme'] ) && isset( $parsed_url['host'] ) ) {
			$url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
		}
		
		$pattern = "/^https?:\\/\\/(?:www\\.)?[-a-zA-Z0-9@:%._\\+~#=]{1,256}\\.[a-zA-Z0-9()]{1,6}\\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&\\/=]*)$/";

		return (bool) preg_match( $pattern, $url );
	}

	/**
	 * Get supported mime types.
	 *
	 * @return array Array of supported mime types by the server.
	 */
	public static function get_supported_mime_types(): array {
		$allowed_types = Constants::ALLOWED_MIME_TYPES;

		// Remove any types that are not supported by the server.
		$supported_types = array_values( get_allowed_mime_types() );
		$allowed_types   = array_intersect( $allowed_types, $supported_types );

		return $allowed_types;
	}

	/**
	 * Get OneMedia sync versions postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync versions.
	 */
	public static function get_sync_attachment_versions( int $attachment_id ): array {
		if ( ! $attachment_id ) {
			return array();
		}

		$versions = get_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_VERSIONS_POSTMETA_KEY, true );
		if ( ! is_array( $versions ) ) {
			return array();
		}

		return $versions;
	}

	/**
	 * Update OneMedia sync versions postmeta value.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $versions      The array of sync versions to set.
	 *
	 * @return bool True if the update was successful, false otherwise.
	 */
	public static function update_sync_attachment_versions( int $attachment_id, array $versions ): bool {
		if ( ! $attachment_id || empty( $versions ) ) {
			return false;
		}

		return update_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_VERSIONS_POSTMETA_KEY, $versions );
	}

	/**
	 * Get site name by URL.
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return string The site name if found, empty string otherwise.
	 */
	public static function get_sitename_by_url( string $site_url ): string {
		// If governing site return from option.
		if ( self::is_governing_site() ) {
			$sites = self::get_brand_sites();
			foreach ( $sites as $site ) {
				if ( hash_equals( rtrim( $site['siteUrl'], '/' ), rtrim( $site_url, '/' ) ) ) {
					return $site['siteName'];
				}
			}
		} else {
			// If brand site create from site_url.
			$parsed_url = wp_parse_url( $site_url );
			if ( isset( $parsed_url['host'] ) ) {
				$host_parts = explode( '.', $parsed_url['host'] );
				$host_name  = $host_parts[0];
				$host_name  = str_replace( array( '-', '_' ), ' ', $host_name );
				$host_name  = ucwords( $host_name );
				return $host_name;
			}
		}
		return '';
	}

	/**
	 * Get a formatted file type label from a mime type string.
	 * E.g. 'image/jpg' => 'JPG', 'image/svg+xml' => 'SVG'
	 *
	 * @param string $mime_type The mime type string.
	 *
	 * @return string The formatted label.
	 */
	public static function get_file_type_label( string $mime_type ): string {
		if ( empty( $mime_type ) || ! is_string( $mime_type ) ) {
			return '';
		}
		$parts = explode( '/', $mime_type );
		$type  = isset( $parts[1] ) ? $parts[1] : '';

		// Handle cases like 'svg+xml'.
		$type = explode( '+', $type )[0];
		return strtoupper( $type );
	}

	/**
	 * Get saved API key for a given connected brand site URL on governing site.
	 *
	 * @param string $site_url The brand site URL.
	 *
	 * @return string The saved API key if found, empty string otherwise.
	 */
	public static function get_brand_site_api_key( string $site_url ): string {
		if ( ! self::is_governing_site() || empty( $site_url ) ) {
			return '';
		}

		$brand_sites = self::get_brand_sites();
		foreach ( $brand_sites as $site ) {
			if ( hash_equals( rtrim( $site['siteUrl'], '/' ), rtrim( $site_url, '/' ) ) ) {
				return $site['apiKey'];
			}
		}
		return '';
	}

	/**
	 * Perform health check on brand sites where a given attachment is shared.
	 *
	 * @param int|null $attachment_id The attachment ID.
	 *
	 * @return array Array with 'success' boolean and 'failed_sites' array.
	 */
	public static function health_check_attachment_brand_sites( int|null $attachment_id ): array {
		if ( ! $attachment_id ) {
			return array(
				'success'      => false,
				'failed_sites' => array(),
				'message'      => __( 'Invalid attachment ID.', 'onemedia' ),
			);
		}

		// Get URLs of all sites where this attachment is shared.
		$site_urls = self::get_sync_site_urls_postmeta( $attachment_id );

		if ( empty( $site_urls ) ) {
			return array(
				'success'      => true,
				'failed_sites' => array(),
				'message'      => __( 'No connected brand sites for this attachment.', 'onemedia' ),
			);
		}

		$failed_sites = array();
		$tracked_urls = array();

		foreach ( $site_urls as $site_url ) {
			$site_url = rtrim( $site_url, '/' );

			// Skip if we've already processed this URL.
			if ( in_array( $site_url, $tracked_urls, true ) ) {
				continue;
			}

			$tracked_urls[] = $site_url;
			$api_key        = self::get_brand_site_api_key( $site_url );

			// Brand site not connected.
			if ( empty( $api_key ) ) {
				$failed_sites[] = array(
					'site_name' => self::get_sitename_by_url( $site_url ),
					'url'       => $site_url,
					'message'   => __( 'API key not found', 'onemedia' ),
				);
				continue;
			}

			// Perform health check request.
			$response = wp_remote_get(
				$site_url . '/wp-json/' . Constants::NAMESPACE . '/health-check',
				array(
					'timeout' => Constants::HEALTH_CHECK_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'headers' => array(
						'X-OneMedia-Token' => $api_key,
						'Cache-Control'    => 'no-cache, no-store, must-revalidate',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$failed_sites[] = array(
					'site_name' => self::get_sitename_by_url( $site_url ),
					'url'       => $site_url,
					'message'   => $response->get_error_message(),
				);
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				$failed_sites[] = array(
					'site_name' => self::get_sitename_by_url( $site_url ),
					'url'       => $site_url,
					'message'   => sprintf(
						/* translators: %d is the HTTP response code. */
						__( 'HTTP %d response', 'onemedia' ),
						$response_code
					),
				);
			}
		}

		
		if ( ! empty( $failed_sites ) ) {
			$failed_sites_list = array();
			foreach ( $failed_sites as $failed_site ) {
				$site_name = $failed_site['site_name'];
				if ( ! in_array( $site_name, $failed_sites_list, true ) ) {
					$failed_sites_list[] = $site_name;
				}
			}

			return array(
				'success'      => false,
				'failed_sites' => $failed_sites,
				'message'      => sprintf(
					/* translators: %s is the list of unreachable sites. */
					__( 'Please check your connection for unreachable sites: %s.', 'onemedia' ),
					implode( ', ', $failed_sites_list )
				),
			);
		}

		return array(
			'success'      => true,
			'failed_sites' => $failed_sites,
			'message'      => __( 'All connected sites are reachable.', 'onemedia' ),
		);
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
}
