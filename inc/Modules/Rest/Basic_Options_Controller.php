<?php
/**
 * This is routes for Settings options.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Rest;

use OneMedia\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Basic_Options_Controller
 */
class Basic_Options_Controller extends Abstract_REST_Controller {

	/**
	 * Health check request timeout.
	 *
	 * @var \OneMedia\Modules\Rest\number
	 */
	public const HEALTH_CHECK_REQUEST_TIMEOUT = 15;

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_site_type' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_site_type' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'args'                => [
						'site_type' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		/**
		 * Register a route which will store array of sites data like site name, site url, its GitHub repo and api key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shared_sites' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_shared_sites' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'args'                => [
						'shared_sites' => [
							'required' => true,
							'type'     => 'array',
						],
					],
				],
			]
		);

		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health_check' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
			]
		);

		/**
		 * Register a route to get api key option.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_secret_key' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'regenerate_secret_key' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
			]
		);

		/**
		 * Register a route to check if all the sites for a shared sync media are connected.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/check-sites-connected',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'check_sites_connected' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => [
					'attachment_id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);

		/**
		 * Register a route to get multisite type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/multisite-type',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_multisite_type' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			]
		);

		/**
		 * Register a route to manage governing site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_governing_site' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_governing_site' ],
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				],
			],
		);
	}

	/**
	 * Get the site type.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_type(): WP_REST_Response|\WP_Error {

		return rest_ensure_response(
			[
				'success'   => true,
				'site_type' => Settings::get_site_type(),
			]
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_site_type( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( $request->get_param( 'site_type' ) );
		$success   = update_option( Settings::OPTION_SITE_TYPE, $site_type, false );

		return rest_ensure_response(
			[
				'success'   => $success,
				'site_type' => $site_type,
			]
		);
	}

	/**
	 * Get shared sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_shared_sites(): WP_REST_Response|\WP_Error {
		$shared_sites = Settings::get_shared_sites();

		return rest_ensure_response(
			[
				'success'      => true,
				'shared_sites' => array_values( $shared_sites ),
			]
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$shared_sites   = $decoded_body['shared_sites'] ?? [];

		// check if same url exists more than once or not.
		$urls = [];
		foreach ( $shared_sites as $site ) {
			if ( isset( $site['url'] ) && in_array( $site['url'], $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'onemedia' ), [ 'status' => 400 ] );
			}
			$urls[] = $site['url'] ?? '';
		}

		// add unique id to each site if not exists.
		foreach ( $shared_sites as &$site ) {
			if ( isset( $site['id'] ) && ! empty( $site['id'] ) ) {
				continue;
			}

			$site['id'] = wp_generate_uuid4();
		}

		Settings::set_shared_sites( $shared_sites );

		return rest_ensure_response(
			[
				'success'    => true,
				'shared_sites' => array_values( $shared_sites ),
			]
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): WP_REST_Response|\WP_Error {

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'onemedia' ),
			]
		);
	}

	/**
	 * Get governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_governing_site(): WP_REST_Response|\WP_Error {
		$governing_site_url = Settings::get_parent_site_url();

		return rest_ensure_response(
			[
				'success'            => true,
				'governing_site_url' => $governing_site_url,
			]
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_governing_site(): WP_REST_Response|\WP_Error {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'onemedia' ),
			]
		);
	}

	/**
	 * Get the secret key.
	 *
	 * @return \WP_REST_Response| \WP_Error
	 */
	public function get_secret_key(): \WP_REST_Response|\WP_Error {
		$secret_key = Settings::get_api_key();

		return new \WP_REST_Response(
			[
				'success'    => true,
				'secret_key' => $secret_key,
			]
		);
	}

	/**
	 * Regenerate the secret key.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function regenerate_secret_key(): \WP_REST_Response|\WP_Error {

		$regenerated_key = Settings::regenerate_api_key();

		return new \WP_REST_Response(
			[
				'success'    => true,
				'message'    => __( 'Secret key regenerated successfully.', 'onemedia' ),
				'secret_key' => $regenerated_key,
			]
		);
	}

	/**
	 * Check if all the sites for a shared sync media are connected.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after checking the connected sites.
	 */
	public function check_sites_connected( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );

		// Validate attachment id.
		if ( empty( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'onemedia' ),
				[
					'status'  => 400,
					'success' => false,
				]
			);
		}

		// Check if all the sites for this attachment are connected.
		$health_check_connected_sites = self::health_check_attachment_brand_sites( $attachment_id );

		return rest_ensure_response( $health_check_connected_sites );
	}

	/**
	 * Get multisite type.
	 *
	 * @return \WP_REST_Response The response containing the multisite type (single, subdomain or subdirectory).
	 */
	public function get_multisite_type(): \WP_REST_Response {
		$multisite_type = self::fetch_multisite_type();
		return new \WP_REST_Response(
			[
				'status'         => 500,
				'multisite_type' => $multisite_type,
				'success'        => true,
			]
		);
	}

	/**
	 * Get the multisite type.
	 *
	 * @return string The multisite type (subdomain, subdirectory, or single).
	 */
	public function fetch_multisite_type(): string {
		if ( is_multisite() ) {
			return is_subdomain_install() ? 'subdomain' : 'subdirectory';
		}
		return 'single';
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
			$api_key        = self::get_brand_site_api_key( $site_url );

			// Brand site not connected.
			if ( empty( $api_key ) ) {
				$failed_sites[] = [
					'site_name' => self::get_sitename_by_url( $site_url ),
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
					'site_name' => self::get_sitename_by_url( $site_url ),
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
				'site_name' => self::get_sitename_by_url( $site_url ),
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
	public static function get_sync_sites_postmeta( int $attachment_id ): array {
		if ( ! Settings::is_governing_site() || ! $attachment_id ) {
			return [];
		}

		$sites = get_post_meta( $attachment_id, Media_Sharing_Controller::ONEMEDIA_SYNC_SITES_POSTMETA_KEY, true );
		if ( ! is_array( $sites ) ) {
			return [];
		}
		return $sites;
	}

	/**
	 * Get saved API key for a given connected brand site URL on governing site.
	 *
	 * @param string $site_url The brand site URL.
	 *
	 * @return string The saved API key if found, empty string otherwise.
	 */
	public static function get_brand_site_api_key( string $site_url ): string {
		if ( ! Settings::is_governing_site() || empty( $site_url ) ) {
			return '';
		}

		$brand_sites = Settings::get_shared_sites();
		foreach ( $brand_sites as $site ) {
			if ( rtrim( $site['url'], '/' ) === rtrim( $site_url, '/' ) ) {
				return $site['api_key'];
			}
		}
		return '';
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
		if ( Settings::is_governing_site() ) {
			$sites = Settings::get_shared_sites();
			foreach ( $sites as $site ) {
				if ( hash_equals( rtrim( $site['url'], '/' ), rtrim( $site_url, '/' ) ) ) {
					return $site['name'];
				}
			}
		} else {
			// If brand site create from site_url.
			$parsed_url = wp_parse_url( $site_url );
			if ( isset( $parsed_url['host'] ) ) {
				$host_parts = explode( '.', $parsed_url['host'] );
				$host_name  = $host_parts[0];
				$host_name  = str_replace( [ '-', '_' ], ' ', $host_name );
				$host_name  = ucwords( $host_name );
				return $host_name;
			}
		}
		return '';
	}
}
