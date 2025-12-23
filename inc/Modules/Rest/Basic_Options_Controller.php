<?php
/**
 * This is routes for Settings options.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Rest;

use OneMedia\Modules\MediaSharing\Attachment;
use OneMedia\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Basic_Options_Controller
 */
class Basic_Options_Controller extends Abstract_REST_Controller {

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
		$shared_sites = $decoded_body['shared_sites'] ?? [];

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
				'success'      => true,
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
		$health_check_connected_sites = Attachment::health_check_attachment_brand_sites( $attachment_id );

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
}
