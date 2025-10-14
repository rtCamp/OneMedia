<?php
/**
 * Class Basic_Options which contains basic rest routes for the plugin.
 *
 * @package OneMedia
 */

namespace OneMedia\REST;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Plugin_Configs\Secret_Key;
use OneMedia\Utils;
use OneMedia\Traits\Singleton;
use WP_REST_Server;

/**
 * Class Basic_Options
 */
class Basic_Options {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		/**
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/site-type',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_type' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_site_type' ),
					'permission_callback' => 'onemedia_validate_rest_api',
					'args'                => array(
						'site_type' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a route to get onemedia_child_site_api_key option.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/secret-key',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( Secret_Key::class, 'get_secret_key' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( Secret_Key::class, 'regenerate_secret_key' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
			)
		);

		/**
		 * Register a route which will store array of sites data like site name, site url and api key.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/brand-sites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_brand_sites' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_brand_sites' ),
					'permission_callback' => 'onemedia_validate_rest_api',
					'args'                => array(
						'sites' => array(
							'required'          => true,
							'type'              => 'array',
							'validate_callback' => function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);

		/**
		 * Register a route to perform health check from governing site to brand site.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/health-check',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '__return_true',
			)
		);

		/**
		 * Register a route to check if all the sites for a shared sync media are connected.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/check-sites-connected',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_sites_connected' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'attachment_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to get multisite type.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/multisite-type',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_multisite_type' ),
				'permission_callback' => '__return_true',
			)
		);
		
		/**
		 * Register a route to manage governing site connection on brand site.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/governing-site',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_governing_site' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_governing_site' ),
					'permission_callback' => 'onemedia_validate_rest_api',
				),
			),
		);
	}

	/**
	 * Get the site type.
	 *
	 * @return \WP_REST_Response|\WP_Error The response containing the site type.
	 */
	public function get_site_type(): \WP_REST_Response|\WP_Error {

		$site_type = Utils::get_current_site_type();

		return rest_ensure_response(
			array(
				'site_type' => $site_type,
				'success'   => true,
			)
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after setting the site type.
	 */
	public function set_site_type( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$site_type = $request->get_param( 'site_type' );

		// Sanitize site type.
		$site_type = sanitize_text_field( $site_type );

		// Validate site type, it should be either 'governing' or 'brand'.
		if ( ! isset( $site_type ) || empty( $site_type ) || ! is_string( $site_type ) || ( ONEMEDIA_PLUGIN_GOVERNING_SITE !== $site_type && ONEMEDIA_PLUGIN_BRAND_SITE !== $site_type ) ) {
			return new \WP_Error(
				'invalid_site_type',
				__( 'Invalid site type provided. It should be either "governing" or "brand".', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		$saved_site_type = Utils::get_current_site_type();
		if ( empty( $saved_site_type ) || ! hash_equals( $site_type, $saved_site_type ) ) {
			// Update site type option.
			$success = update_option( Constants::ONEMEDIA_SITE_TYPE_OPTION, $site_type );
	
			if ( ! $success ) {
				return new \WP_Error(
					'update_failed',
					__( 'Failed to update site type.', 'onemedia' ),
					array(
						'status'  => 500,
						'success' => false,
					)
				);
			}
		}

		return rest_ensure_response(
			array(
				'site_type' => $site_type,
				'success'   => true,
			)
		);
	}

	/**
	 * Get brand sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error The response containing the brand sites data.
	 */
	public function get_brand_sites(): \WP_REST_Response|\WP_Error {
		$sites = Utils::get_brand_sites();
		return rest_ensure_response(
			array(
				'sites'   => $sites,
				'message' => __( 'Brand sites retrieved successfully.', 'onemedia' ),
				'success' => true,
			)
		);
	}

	/**
	 * Set brand sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after setting the brand sites data.
	 */
	public function set_brand_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites        = $decoded_body['sites'] ?? array();

		// Check if same url exists more than once or not.
		$urls = array();
		foreach ( $sites as $site ) {
			// Sanitize and trim URL.
			$site['siteUrl'] = isset( $site['siteUrl'] ) ? esc_url_raw( trim( $site['siteUrl'] ) ) : '';
			$site['siteUrl'] = rtrim( $site['siteUrl'], '/' );

			// Sanitize site name and api key.
			$site['siteName'] = isset( $site['siteName'] ) ? sanitize_text_field( $site['siteName'] ) : '';
			$site['apiKey']   = isset( $site['apiKey'] ) ? sanitize_text_field( $site['apiKey'] ) : '';

			if ( empty( $site['siteUrl'] ) || empty( $site['siteName'] ) || empty( $site['apiKey'] ) ) {
				return new \WP_Error(
					'incomplete_site_data',
					__( 'Each site must have a valid site name, site URL, and API key.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}

			// Check if site URL is valid.
			if ( ! Utils::is_valid_url( $site['siteUrl'] ) ) {
				return new \WP_Error(
					'invalid_site_url',
					__( 'Invalid site URL provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}

			// Check for duplicate URLs.
			if ( in_array( $site['siteUrl'], $urls, true ) ) {
				return new \WP_Error(
					'duplicate_site_url',
					__( 'Brand Site already exists.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}
			$urls[] = $site['siteUrl'];
		}

		// Update brand sites option if there is any change in the sites.
		$saved_brand_sites = Utils::get_brand_sites();
		if ( ! hash_equals( wp_json_encode( $sites ), wp_json_encode( $saved_brand_sites ) ) ) {
			$success = update_option( Constants::ONEMEDIA_BRAND_SITES_OPTION, $sites );
	
			if ( ! $success ) {
				return new \WP_Error(
					'update_failed',
					__( 'Failed to update brand sites.', 'onemedia' ),
					array(
						'status'  => 500,
						'success' => false,
					)
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'sites'   => $sites,
			)
		);
	}

	/**
	 * Perform a health check on brand site from governing site.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after performing the health check.
	 */
	public function health_check(): \WP_REST_Response|\WP_Error {
		// This request is received on brand site.
		$health_check_response = onemedia_rest_api_validation( true );
		if ( ! $health_check_response || empty( $health_check_response ) ) {
			return new \WP_Error(
				'site_not_accessible',
				__( 'Health check failed. Please ensure the site is accessible.', 'onemedia' ),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		if ( is_wp_error( $health_check_response ) ) {
			return $health_check_response;
		}

		return rest_ensure_response( $health_check_response );
	}

	/**
	 * Check if all the sites for a shared sync media are connected.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after checking the connected sites.
	 */
	public function check_sites_connected( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$attachment_id = $request->get_param( 'attachment_id' );

		// Validate attachment id.
		if ( empty( $attachment_id ) || ! is_numeric( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Sanitize attachment id.
		$attachment_id = intval( $attachment_id );

		// Check if all the sites for this attachment are connected.
		$health_check_connected_sites = Utils::health_check_attachment_brand_sites( $attachment_id );

		return rest_ensure_response( $health_check_connected_sites );
	}

	/**
	 * Get multisite type.
	 *
	 * @return \WP_REST_Response The response containing the multisite type (single, subdomain or subdirectory).
	 */
	public function get_multisite_type(): \WP_REST_Response {
		$multisite_type = Utils::get_multisite_type();
		return new \WP_REST_Response(
			array(
				'status'         => 500,
				'multisite_type' => $multisite_type,
				'success'        => true,
			)
		);
	}

	/**
	 * Get governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error The response containing the governing site url.
	 */
	public function get_governing_site(): \WP_REST_Response|\WP_Error {
		$governing_site_url = Utils::get_governing_site_url();
		return new \WP_REST_Response(
			array(
				'success'            => true,
				'governing_site_url' => $governing_site_url,
			)
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error The response after removing the governing site url.
	 */
	public function remove_governing_site(): \WP_REST_Response|\WP_Error {
		$saved_site_type = Utils::get_current_site_type();

		if ( ! empty( $saved_site_type ) ) {
			$success = update_option( Constants::ONEMEDIA_GOVERNING_SITES_URL_OPTION, '', false );
	
			if ( ! $success ) {
				return new \WP_Error(
					'update_failed',
					__( 'Failed to remove governing site.', 'onemedia' ),
					array(
						'status'  => 500,
						'success' => false,
					)
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'onemedia' ),
			)
		);
	}
}
