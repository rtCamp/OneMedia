<?php
/**
 * Registers attachment post meta used for media sharing.
 *
 * @package OneMedia\Modules\MediaSharing;
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Rest\Abstract_REST_Controller;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Modules\Taxonomies\Media;

/**
 * Class - Attachment
 */
class Attachment implements Registrable {
	/**
	 * Meta key for sync status.
	 */
	public const SYNC_STATUS_POSTMETA_KEY = 'onemedia_sync_status';

	/**
	 * OneMedia sync versions postmeta key.
	 *
	 * @var string
	 */
	public const SYNC_VERSIONS_POSTMETA_KEY = 'onemedia_sync_versions';

	/**
	 * Meta key for sync sites.
	 */
	public const SYNC_SITES_POSTMETA_KEY = 'onemedia_sync_sites';

	/**
	 * Meta key for indicating if attachment is syncing.
	 */
	public const IS_SYNC_POSTMETA_KEY = 'is_onemedia_sync';

	/**
	 * Health check request timeout.
	 *
	 * @var int
	 */
	private const HEALTH_CHECK_REQUEST_TIMEOUT = 15;

	/**
	 * Sync status values.
	 */
	public const SYNC_STATUS_SYNC    = 'sync';
	public const SYNC_STATUS_NO_SYNC = 'no_sync';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_attachment_post_meta' ] );
	}

	/**
	 * Register attachment post meta for media sharing.
	 */
	public function register_attachment_post_meta(): void {
		$post_meta = [
			self::SYNC_STATUS_POSTMETA_KEY   => [
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ],
					],
				],
				'single'            => true,
				'type'              => 'string',
				'default'           => self::SYNC_STATUS_NO_SYNC,
				'revisions_enabled' => false,
				'description'       => __( 'Indicates if the attachment is shared via OneMedia.', 'onemedia' ),
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			],
			self::SYNC_VERSIONS_POSTMETA_KEY => [
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'last_used' => [
									'type' => 'integer',
								],
								'file'      => [
									'type'       => 'object',
									'properties' => [
										'attachment_id' => [ 'type' => 'integer' ],
										'path'          => [ 'type' => 'string' ],
										'url'           => [
											'type'   => 'string',
											'format' => 'uri',
										],
										'guid'          => [
											'type'   => 'string',
											'format' => 'uri',
										],
										'name'          => [ 'type' => 'string' ],
										'type'          => [ 'type' => 'string' ],
										'alt'           => [ 'type' => 'string' ],
										'caption'       => [ 'type' => 'string' ],
										'size'          => [ 'type' => 'integer' ],
										'metadata'      => [
											'type' => 'object',
										],
										'dimensions'    => [
											'type'       => 'object',
											'properties' => [
												'width'  => [ 'type' => 'integer' ],
												'height' => [ 'type' => 'integer' ],
											],
										],
										'checksum'      => [ 'type' => 'string' ],
									],
								],
							],
						],
					],
				],
				'default'           => [],
				'revisions_enabled' => false,
				'description'       => __( 'Stores revisions of the synced attachment for OneMedia.', 'onemedia' ),
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => static function ( $value ) {
					return is_array( $value ) ? $value : [];
				},
			],
		];

		foreach ( $post_meta as $meta_key => $args ) {
			register_post_meta( 'attachment', $meta_key, $args );
		}
	}

	/**
	 * Get OneMedia attachment terms.
	 *
	 * @param int|\WP_Post $attachment_id The attachment ID.
	 *
	 * @return array Array of terms.
	 */
	public static function get_terms( int|\WP_Post $attachment_id ): array {
		if ( ! $attachment_id ) {
			return [];
		}

		$terms = get_the_terms( $attachment_id, Media::TAXONOMY );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return [];
		}

		return $terms;
	}

	/**
	 * Get OneMedia attachment terms with args.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $args          Arguments to pass to wp_get_post_terms function.
	 *
	 * @return array Array of terms.
	 */
	public static function get_post_terms( int $attachment_id, array $args = [] ): array {
		$terms = wp_get_post_terms( $attachment_id, Media::TAXONOMY, $args );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return $terms;
	}

	/**
	 * Gets the sync status of an attachment.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return 'sync'|'no_sync'|null The sync status value or null if not set.
	 */
	public static function get_sync_status( int $attachment_id ): ?string {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return null;
		}

		$meta_value = get_post_meta( $attachment_id, self::SYNC_STATUS_POSTMETA_KEY, true );

		if ( in_array( $meta_value, [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ], true ) ) {
			return $meta_value;
		}

		return null;
	}

	/**
	 * Updates the sync status of an attachment.
	 *
	 * @param int              $attachment_id The ID of the attachment.
	 * @param 'sync'|'no_sync' $status        The sync status to set.
	 *
	 * @return int|bool Meta ID if the key didn't exist, false on failure, true on success.
	 */
	public static function update_sync_status( int $attachment_id, string $status ) {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return false;
		}

		if ( ! in_array( $status, [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ], true ) ) {
			return false;
		}

		return update_post_meta( $attachment_id, self::SYNC_STATUS_POSTMETA_KEY, $status );
	}

	/**
	 * Get the sync sites of an attachment.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_sync_sites( int $attachment_id ): array {
		if ( ! Settings::is_governing_site() || ! $attachment_id ) {
			return [];
		}

		$meta_value = get_post_meta( $attachment_id, self::SYNC_SITES_POSTMETA_KEY, true );

		return is_array( $meta_value ) ? $meta_value : [];
	}

	/**
	 * Gets whether an attachment is currently synced or not.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return bool True if the attachment is a sync attachment, false otherwise.
	 */
	public static function is_sync_attachment( int $attachment_id ): bool {

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		$is_sync = false;
		if ( Settings::is_consumer_site() ) { // totally not sure why same meta is not used on both sites & why string instead of boolean.
			$is_sync = self::SYNC_STATUS_SYNC === self::get_sync_status( $attachment_id );
		} elseif ( Settings::is_governing_site() ) {
			$is_sync = (bool) get_post_meta( $attachment_id, self::IS_SYNC_POSTMETA_KEY, true );
		}

		return (bool) $is_sync;
	}

	/**
	 * Updates whether an attachment is currently syncing.
	 *
	 * @param int  $attachment_id The ID of the attachment.
	 * @param bool $is_syncing    True if syncing, false otherwise.
	 *
	 * @return int|bool Meta ID if the key didn't exist, false on failure, true on success.
	 */
	public static function update_is_syncing( int $attachment_id, bool $is_syncing ) {
		if ( Settings::is_consumer_site() || ! $attachment_id ) {
			return false;
		}

		return update_post_meta( $attachment_id, self::IS_SYNC_POSTMETA_KEY, $is_syncing );
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

		return (bool) update_post_meta( $attachment_id, self::SYNC_VERSIONS_POSTMETA_KEY, $versions );
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
			return [];
		}

		$versions = get_post_meta( $attachment_id, self::SYNC_VERSIONS_POSTMETA_KEY, true );
		if ( ! is_array( $versions ) ) {
			return [];
		}

		return $versions;
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
					'timeout' => self::HEALTH_CHECK_REQUEST_TIMEOUT,
					// phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'headers' => [
						'Origin'           => get_site_url(),
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
	 * Get OneMedia sync site URLs postmeta value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array The array of sync site URLs.
	 */
	private static function get_sync_site_urls_postmeta( int $attachment_id ): array {
		$sites = self::get_sync_sites( $attachment_id );
		if ( empty( $sites ) ) {
			return [];
		}

		$site_urls = [];
		foreach ( $sites as $site ) {
			if ( ! isset( $site['site'] ) ) {
				continue;
			}

			$site_urls[] = trailingslashit( esc_url_raw( $site['site'] ) );
		}

		return $site_urls;
	}
}
