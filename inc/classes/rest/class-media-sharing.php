<?php
/**
 * Class Media_Sharing which contains basic rest routes for the plugin.
 *
 * @package OneMedia
 */

namespace OneMedia\REST;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Utils;
use OneMedia\Traits\Singleton;
use WP_REST_Server;

/**
 * Class Media_Sharing
 */
class Media_Sharing {

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
		 * Register a route to get all media files with pagination.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/media',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_media_files' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'search_term' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
					'page'        => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'per_page'    => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'image_type'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to sync media files with brand sites.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/sync-media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_media_files' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'brand_sites'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
					'media_details' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
					'sync_option'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to add media files.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/add-media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_media_files' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'media_files' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
					'sync_option' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to update media files.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/update-attachment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_media_files' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'attachment_id'   => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'attachment_url'  => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
					'attachment_data' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to delete media metadata of syned.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/delete-media-metadata',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'delete_media_metadata' ),
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
		 * Register a route to get onemedia_brand_sites_synced_media option.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/brand-sites-synced-media',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_brand_sites_synced_media' ),
				'permission_callback' => 'onemedia_validate_rest_api',
			)
		);

		/**
		 * Register a route to update an existing attachment as onemedia.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/update-existing-attachment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_existing_attachment' ),
				'permission_callback' => 'onemedia_validate_rest_api',
				'args'                => array(
					'attachment_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'sync_option'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
					),
				),
			)
		);

		/**
		 * Register a route to check if attachment is sync or not.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/is-sync-attachment',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'is_sync_attachment' ),
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
		 * Register a route to get sync attachment versions.
		 */
		register_rest_route(
			Constants::NAMESPACE,
			'/sync-attachment-versions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_sync_attachment_versions' ),
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
	}

	/**
	 * Get brand sites synced media.
	 *
	 * @return \WP_Error|\WP_REST_Response The response containing the brand sites synced media.
	 */
	public function get_brand_sites_synced_media(): \WP_Error|\WP_REST_Response {
		// Get the sync option data.
		$brand_sites_synced_media = Utils::get_brand_sites_synced_media();

		if ( ! is_array( $brand_sites_synced_media ) ) {
			$brand_sites_synced_media = array();
		}

		// Get all registered brand sites.
		$all_brand_sites = Utils::get_all_brand_sites();
		if ( ! is_array( $all_brand_sites ) ) {
			$all_brand_sites = array();
		}

		// Create URL to site name mapping first.
		$url_to_name_mapping = array();
		foreach ( $all_brand_sites as $site_data ) {
			$clean_url                         = rtrim( $site_data['siteUrl'], '/' );
			$url_to_name_mapping[ $clean_url ] = $site_data['siteName'];
		}

		// Create the desired mapping structure: 'id' => array('siteURL' => 'siteName').
		$site_mapping = array();

		foreach ( $brand_sites_synced_media as $id => $site_data ) {
			foreach ( $site_data as $site_url => $media_id ) {
				// Get the site name from the URL to name mapping.
				$site_url                         = rtrim( $site_url, '/' );
				$site_mapping[ $id ][ $site_url ] = $url_to_name_mapping[ $site_url ] ?? __( 'Unknown Site', 'onemedia' );
			}
		}

		// Return the response.
		return rest_ensure_response(
			array(
				'status'  => 200,
				'data'    => $site_mapping,
				'success' => true,
			)
		);
	}

	/**
	 * Deleted media metadata.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response after deleting media metadata.
	 */
	public function delete_media_metadata( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$attachment_id = $request->get_param( 'attachment_id' );

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

		// Delete the metadata for the attachment.
		delete_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY );
		delete_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_SITES_POSTMETA_KEY );
		delete_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY );

		return rest_ensure_response(
			array(
				'message' => __( 'Media metadata deleted successfully.', 'onemedia' ),
				'status'  => 200,
				'success' => true,
			)
		);
	}

	/**
	 * Update media files.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response after updating media files.
	 */
	public function update_media_files( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$attachment_id   = $request->get_param( 'attachment_id' );
		$attachment_url  = $request->get_param( 'attachment_url' );
		$attachment_data = $request->get_param( 'attachment_data' );

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

		// Validate attachment URL.
		if ( empty( $attachment_url ) || ! is_string( $attachment_url ) || ! Utils::is_valid_url( $attachment_url ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Validate attachment data.
		if (
			empty( $attachment_data ) ||
			! is_array( $attachment_data ) ||
			( isset( $attachment_data['title'] ) && ! is_string( $attachment_data['title'] ) ) ||
			( isset( $attachment_data['alt_text'] ) && ! is_string( $attachment_data['alt_text'] ) ) ||
			( isset( $attachment_data['caption'] ) && ! is_string( $attachment_data['caption'] ) ) ||
			( isset( $attachment_data['description'] ) && ! is_string( $attachment_data['description'] ) ) ||
			( isset( $attachment_data['terms'] ) && ! is_array( $attachment_data['terms'] ) )
		) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid attachment data provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/media.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		// Sanitize attachment url.
		$attachment_url = esc_url_raw( trim( $attachment_url ) );

		// Update the attachment data in the database or perform any necessary actions.
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Update attachment title, alt_text, caption, description.
		$attachment_post = array(
			'ID'           => $attachment_id,
			'post_title'   => isset( $attachment_data['title'] ) ? sanitize_text_field( $attachment_data['title'] ) : '',
			'post_excerpt' => isset( $attachment_data['caption'] ) ? sanitize_text_field( $attachment_data['caption'] ) : '',
			'post_content' => isset( $attachment_data['description'] ) ? sanitize_textarea_field( $attachment_data['description'] ) : '',
			'post_terms'   => isset( $attachment_data['terms'] ) ? array_map( 'sanitize_text_field', $attachment_data['terms'] ) : array(),
		);

		// Decode HTML entities in title.
		$attachment_post['post_title'] = Utils::decode_filename( $attachment_post['post_title'] );

		// Download the file from the URL and save it to the uploads directory and replace the old file.
		$uploads  = wp_get_upload_dir();
		$filename = wp_unique_filename( $uploads['path'], basename( $attachment_url ) );
		$new_file = $uploads['path'] . '/' . $filename;

		// Get the file data from the URL.
		$file_data = $this->fetch_remote_file( $attachment_url, $new_file, false );
		$file_data = ! is_wp_error( $file_data ) && isset( $file_data['file_data'] ) ? $file_data['file_data'] : false;

		if ( false === $file_data ) {
			return new \WP_Error(
				'file_download_failed',
				__( 'Failed to download file from URL.', 'onemedia' ),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		// Ignoring the PHPCS warning since we are putting the file in the uploads directory.
		file_put_contents( $new_file, $file_data ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		// Not checking output because update_attached_file returns false even if the filename is same as previous one.
		// Update the attachment post with the new file path.
		update_attached_file( $attachment_id, $new_file );

		// Regenerate metadata and intermediate sizes.
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/image.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/media.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $new_file );
		if ( $metadata ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// Update the attachment post.
		$update_post_success = wp_update_post( $attachment_post );

		if ( is_wp_error( $update_post_success ) ) {
			return new \WP_Error(
				'post_update_failed',
				__( 'Failed to update attachment post.', 'onemedia' ),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		// Update attachment alt text.
		if ( isset( $attachment_data['alt_text'] ) ) {
			// Not checking output because update_post_meta returns false even if the value is same as previous one.
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $attachment_data['alt_text'] );
		}

		// Get attachment permalink.
		$attachment_permalink = get_attachment_link( $attachment_id );
		$alt_text             = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$caption              = wp_get_attachment_caption( $attachment_id );

		onemedia_replace_image_across_all_post_types(
			$attachment_id,
			$attachment_permalink,
			$alt_text,
			$caption
		);

		return rest_ensure_response(
			array(
				'message' => __( 'Media file updated successfully.', 'onemedia' ),
				'status'  => 200,
				'success' => true,
			)
		);
	}

	/**
	 * Get media files with pagination.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error The response containing the media files.
	 */
	public function get_media_files( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$page        = $request->get_param( 'page' );
		$per_page    = $request->get_param( 'per_page' );
		$image_type  = $request->get_param( 'image_type' );
		$search_term = $request->get_param( 'search_term' );

		// Validate page param.
		$page = isset( $page ) && is_numeric( $page ) && $page > 0 ? intval( $page ) : 1;

		// Validate per page param.
		$per_page = isset( $per_page ) && is_numeric( $per_page ) && $per_page > 0 ? intval( $per_page ) : 10;
		$per_page = $per_page > 100 ? 100 : $per_page; // Limit per_page to 100 max.

		// Validate search term param.
		$image_type = isset( $image_type ) && is_string( $image_type ) ? sanitize_text_field( $image_type ) : '';

		if ( ! empty( $image_type ) && ONEMEDIA_PLUGIN_TAXONOMY_TERM !== $image_type ) {
			$term_exists_fn = function_exists( 'wpcom_vip_term_exists' ) ? 'wpcom_vip_term_exists' : 'term_exists';

			// Check if the term exists in onemedia_media_type taxonomy.
			if ( ! $term_exists_fn( $image_type, ONEMEDIA_PLUGIN_TAXONOMY ) ) {
				return new \WP_Error(
					'invalid_image_type',
					__( 'Invalid image type provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}
		}

		// Validate search term param.
		$search_term = isset( $search_term ) && is_string( $search_term ) ? sanitize_text_field( $search_term ) : '';

		$args = array(
			'post_type'      => 'attachment',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'any',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'menu_order'     => 'ASC',
			'post_mime_type' => Utils::get_supported_mime_types(),
		);

		// Add search functionality.
		if ( ! empty( $search_term ) ) {
			$args['s'] = sanitize_text_field( $search_term );
		}

		// If image_type is provided, filter by onemedia_media_type taxonomy.
		if ( ! empty( $image_type ) ) {
         	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = array(
				array(
					'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $image_type,
					'operator' => 'IN',
				),
			);
		} else {
			// Exclude onemedia_media_type taxonomy.
         	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'] = array(
				array(
					'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( ONEMEDIA_PLUGIN_TAXONOMY_TERM ), // Exclude 'onemedia' term.
					'operator' => 'NOT IN',
				),
			);
		}
		$query       = new \WP_Query( $args );
		$media_files = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id       = get_the_ID();
				$title         = Utils::decode_filename( get_the_title( $post_id ) );
				$media_files[] = array(
					'id'        => $post_id,
					'url'       => wp_get_attachment_url( $post_id ),
					'title'     => $title,
					'mime_type' => get_post_mime_type( $post_id ),
					'revision'  => get_post_meta( $post_id, Constants::ONEMEDIA_SYNC_VERSIONS_POSTMETA_KEY, true ),
				);
			}
			wp_reset_postdata();
		}

		$response = array(
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $query->found_posts,
			'total_pages' => ceil( $query->found_posts / $per_page ),
			'media_files' => $media_files,
			'status'      => 200,
			'success'     => true,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Sync media files with brand sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response after syncing media files.
	 */
	public function sync_media_files( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$brand_sites   = $request->get_param( 'brand_sites' );
		$media_details = $request->get_param( 'media_details' );
		$sync_option   = $request->get_param( 'sync_option' );

		// Validate brand_sites array.
		if ( empty( $brand_sites ) || ! is_array( $brand_sites ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid brand sites provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Trim brand sites array to remove empty values and sanitize URLs.
		$brand_sites = array_filter( array_map( 'trim', $brand_sites ) );
		$brand_sites = array_map( 'esc_url_raw', $brand_sites );

		// Brand sites should be an array of valid urls.
		foreach ( $brand_sites as $site ) {
			if ( ! Utils::is_valid_url( $site ) ) {
				return new \WP_Error(
					'invalid_site_url',
					__( 'Invalid site URL(s) provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}
		}

		// Validate sync_option, it should be either 'sync' or 'no_sync'.
		if ( empty( $sync_option ) || ! is_string( $sync_option ) || ( 'sync' !== $sync_option && 'no_sync' !== $sync_option ) ) {
			return new \WP_Error(
				'invalid_sync_option',
				__( 'Invalid sync option provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Validate media details array.
		if ( empty( $media_details ) || ! is_array( $media_details ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid media details provided', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Sanitize and validate each media detail and check for unsupported mime types.
		foreach ( $media_details as $key => $media ) {
			// Sanitize media details.
			$media['id']        = isset( $media['id'] ) ? intval( $media['id'] ) : 0;
			$media['url']       = isset( $media['url'] ) ? esc_url_raw( $media['url'] ) : '';
			$media['title']     = isset( $media['title'] ) ? sanitize_text_field( $media['title'] ) : '';
			$media['mime_type'] = isset( $media['mime_type'] ) ? sanitize_text_field( $media['mime_type'] ) : '';

			// Validate each media.
			if (
				! is_array( $media ) ||
				empty( $media['id'] ) ||
				! is_numeric( $media['id'] ) ||
				empty( $media['url'] ) ||
				( ! Utils::is_valid_url( $media['url'] ) ) ||
				empty( $media['title'] ) ||
				! is_string( $media['title'] )
			) {
				return new \WP_Error(
					'invalid_media_details',
					__( 'Invalid media details provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}

			// Mime type should be one of the supported mime types.
			if ( ! in_array( $media['mime_type'], Utils::get_supported_mime_types(), true ) ) {
				return new \WP_Error(
					'invalid_mime_type',
					__( 'Invalid mime type provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}

			// For each media file its sync is checked then add meta data is_onemedia_sync to be true and onemedia_sync_sites meta to array of sites where it is synced.
			if ( 'sync' === $sync_option ) {
				update_post_meta( $media['id'], Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, true );
			} else {
				// If not syncing, set the sync status to false.
				update_post_meta( $media['id'], Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, 0 );
			}

			// Share the attachment metadata with the brand sites.
			// Get attachment metadata.
			$attachment_data = wp_get_attachment_metadata( $media['id'] );

			// Get attachment title, alt text, caption and description.
			$attachment_data['post_title']  = $media['title'];
			$attachment_data['alt_text']    = get_post_meta( $media['id'], '_wp_attachment_image_alt', true );
			$attachment_data['caption']     = get_post_field( 'post_excerpt', $media['id'] );
			$attachment_data['description'] = get_post_field( 'post_content', $media['id'] );

			// Add attachment metadata to media details.
			$media_details[ $key ]['attachment_data'] = $attachment_data;
		}

		// Perform the media sync operation here.
		$brand_site_prefix = '/wp-json/' . Constants::NAMESPACE . '/add-media';

		// Failed to sync media files to brand sites.
		$failed_sites = array();

		// Get all registered brand sites to compare endpoint and get API token before sharing media.
		$all_brand_sites = Utils::get_all_brand_sites();

		foreach ( $brand_sites as $site ) {
			$site_url = $site;

			// Strip the trailing slash.
			$site_url   = rtrim( $site_url, '/' );
			$site_token = '';

			if ( empty( $site_url ) ) {
				$failed_sites[] = array(
					'site'      => $site,
					'message'   => sprintf(
						/* translators: %s: site URL */
						__( 'Invalid site URL: %s', 'onemedia' ),
						$site_url . $brand_site_prefix
					),
					'site_url'  => $site_url . $brand_site_prefix,
					'token'     => $site_token,
					'child_key' => Utils::get_onemedia_api_key( 'default_api_key' ),
				);
				continue;
			}

			$site_name = Utils::get_sitename_by_url( $site_url );

			// Find the site in all brand sites to get its API token.
			foreach ( $all_brand_sites as $site_data ) {
				// Trim trailing slash.
				if ( rtrim( $site_data['siteUrl'], '/' ) === rtrim( $site_url, '/' ) ) {
					$site_token = $site_data['apiKey'];
					break;
				}
			}

			// Success response.
			$success_response = array();

			// Prepare the request to the brand site.
			$response = wp_remote_post(
				$site_url . $brand_site_prefix,
				array(
					'headers'   => array(
							'X-OneMedia-Token' => ( $site_token ),
							'Cache-Control'    => 'no-cache, no-store, must-revalidate',
					),
					'body'      => (
						array(
							'media_files' => $media_details,
							'sync_option' => $sync_option,
						)
					),
					'timeout'   => Constants::SYNC_MEDIA_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'sslverify' => false,
				)
			);

			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body, true );

			// Check the response from the brand site.
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( is_wp_error( $response ) || 200 !== $response_code ) {
				$errors             = $response_body['data']['errors'] ?? array();
				$is_mime_type_error = $response_body['data']['is_mime_type_error'] ?? false;

				$failed_sites[] = array(
					'site_name'          => $site_name,
					'site'               => $site_url,
					'message'            => $response_body['message'] ?? __( 'Failed to sync media files.', 'onemedia' ),
					'errors'             => $errors,
					'site_url'           => $site_url . $brand_site_prefix,
					'token'              => $site_token,
					'child_key'          => Utils::get_onemedia_api_key( 'default_api_key' ),
					'is_mime_type_error' => $is_mime_type_error,
				);
			}

			// If there are any successful media syncs, process them.
			// Successful response from the brand site, the media files were synced.
			$success_response[] = $response_body;

			$media_response_list = isset( $response_body['media'] ) ? $response_body['media'] : array();

			// In case of error response, media list is in data key.
			if ( empty( $media_response_list ) && isset( $response_body['data']['media'] ) ) {
				$media_response_list = $response_body['data']['media'];
			}

			if ( ! empty( $media_response_list ) ) {
				foreach ( $media_response_list as $media ) {
					// Get onemedia_sync_sites meta for each parent_id media.
					$parent_id           = $media['parent_id'];
					$onemedia_sync_sites = Utils::get_sync_sites_postmeta( $parent_id );

					if ( ! is_array( $onemedia_sync_sites ) ) {
						$onemedia_sync_sites = array();
					}

					// Add brand site with its id so that it can be used to sync media files.
					$onemedia_sync_sites[] = array(
						'site' => $site,
						'id'   => $media['id'],
					);

					// Update onemedia_sync_sites meta for each parent_id media.
					update_post_meta( $parent_id, Constants::ONEMEDIA_SYNC_SITES_POSTMETA_KEY, $onemedia_sync_sites );

					// Create option to store siteurl, parent media id and brand site media id.
					if ( 'sync' === $sync_option ) {
						$brand_sites_synced_media = Utils::get_brand_sites_synced_media();

						if ( ! is_array( $brand_sites_synced_media ) ) {
							$brand_sites_synced_media = array();
						}

						// Add brand site media id to the option.
						$parent_sync_media_mapping = array(
							$site => $media['id'],
						);

						if ( ! isset( $brand_sites_synced_media[ $parent_id ] ) ) {
							$brand_sites_synced_media[ $parent_id ] = array();
						}

						$brand_sites_synced_media[ $parent_id ] = array_merge(
							$brand_sites_synced_media[ $parent_id ],
							$parent_sync_media_mapping
						);

						// Update the synced media mapping option only if there is a change.
						$saved_brand_sites_synced_media = Utils::get_brand_sites_synced_media();
						if ( ! hash_equals( wp_json_encode( $saved_brand_sites_synced_media ), wp_json_encode( $brand_sites_synced_media ) ) ) {
							$success = update_option( Constants::BRAND_SITES_SYNCED_MEDIA_OPTION, $brand_sites_synced_media );

							if ( ! $success ) {
								$failed_sites[] = array(
									'site_name' => $site_name,
									'site'      => $site,
									'message'   => __( 'Failed to update synced media.', 'onemedia' ),
									'site_url'  => $site_url . $brand_site_prefix,
									'token'     => $site_token,
									'child_key' => Utils::get_onemedia_api_key( 'default_api_key' ),
								);
							}
						}
					}
				}
			}
		}

		if ( ! empty( $failed_sites ) ) {
			return new \WP_Error(
				'sync_failed',
				__( 'Failed to sync media files to some brand sites.', 'onemedia' ),
				array(
					'status'       => 500,
					'failed_sites' => $failed_sites,
					'success'      => false,
				)
			);
		}

		return rest_ensure_response(
			array(
				'message'              => __( 'Media files synced successfully.', 'onemedia' ),
				'status'               => 200,
				'success'              => true,
				'success_response'     => $success_response,
				'onemedia_sync_option' => Utils::get_brand_sites_synced_media(),
			)
		);
	}

	/**
	 * Add media files.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response after adding media files.
	 */
	public function add_media_files( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$media_files = $request->get_param( 'media_files' );
		$sync_status = $request->get_param( 'sync_option' );

		// Validate sync_option, it should be either 'sync' or 'no_sync'.
		if ( empty( $sync_status ) || ! is_string( $sync_status ) || ( 'sync' !== $sync_status && 'no_sync' !== $sync_status ) ) {
			return new \WP_Error(
				'invalid_sync_option',
				__( 'Invalid sync option provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Validate media files array.
		if ( empty( $media_files ) || ! is_array( $media_files ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid media files provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		$successful_uploads     = array();
		$errors                 = array();
		$unsupported_file_types = array();

		// Include necessary WordPress media handling functions.
		if ( ! function_exists( 'media_sideload_image' ) ) {
			include_once ABSPATH . 'wp-admin/includes/media.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			include_once ABSPATH . 'wp-admin/includes/file.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			include_once ABSPATH . 'wp-admin/includes/image.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		// Perform the media addition operation here.
		foreach ( $media_files as $media_file ) {
			$media_url       = isset( $media_file['url'] ) ? esc_url_raw( trim( $media_file['url'] ) ) : '';
			$parent_media_id = isset( $media_file['id'] ) ? intval( $media_file['id'] ) : 0;
			$media_title     = isset( $media_file['title'] ) ? $media_file['title'] : basename( $media_url );
			$media_mime_type = isset( $media_file['mime_type'] ) ? sanitize_text_field( $media_file['mime_type'] ) : '';
			$attachment_data = isset( $media_file['attachment_data'] ) ? $media_file['attachment_data'] : array();

			// Validate each media.
			if (
				! is_array( $media_file ) ||
				empty( $parent_media_id ) ||
				! is_numeric( $parent_media_id ) ||
				empty( $media_url ) ||
				! Utils::is_valid_url( $media_url ) ||
				empty( $media_title ) ||
				! is_string( $media_title ) ||
				empty( $media_mime_type ) ||
				! is_string( $media_mime_type ) ||
				( ! empty( $attachment_data ) && ! is_array( $attachment_data ) )
			) {
				return new \WP_Error(
					'invalid_media_details',
					__( 'Invalid media details provided.', 'onemedia' ),
					array(
						'status'  => 400,
						'success' => false,
					)
				);
			}

			// Sanitize attachment data if provided.
			$attachment_data = array_map( 'sanitize_text_field', $attachment_data );

			// Add attachment title, alt_text, caption, description.
			$attachment_metadata = array(
				'post_title'   => isset( $attachment_data['post_title'] ) ? sanitize_text_field( $attachment_data['post_title'] ) : '',
				'alt_text'     => isset( $attachment_data['alt_text'] ) ? sanitize_text_field( $attachment_data['alt_text'] ) : '',
				'post_excerpt' => isset( $attachment_data['caption'] ) ? sanitize_text_field( $attachment_data['caption'] ) : '',
				'post_content' => isset( $attachment_data['description'] ) ? sanitize_textarea_field( $attachment_data['description'] ) : '',
			);

			// Decode HTML entities in title.
			$attachment_metadata['post_title'] = Utils::decode_filename( $attachment_metadata['post_title'] );

			// Get governing to brand site attachment mapping to prevent duplicate media files.
			$attachment_key_map = Utils::get_attachment_key_map();

			// If this media file was shared previously.
			if ( array_key_exists( $parent_media_id, $attachment_key_map ) ) {
				$attachment_id       = isset( $media_file['child_id'] ) ? intval( $media_file['child_id'] ) : null;
				$saved_attachment_id = intval( $attachment_key_map[ $parent_media_id ] ) ?? 0;

				// TODO: Update this to a more robust check.
				// If $media_file['child_id'] is not provided, it means media was shared in the same configuration.
				if ( ! $attachment_id ) {
					// Media already shared in the same configuration.

					if ( 'sync' === $sync_status ) {
						// Add attachment metadata for sync status.
						if ( $sync_status ) {
							update_post_meta( $saved_attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, $sync_status );
						}

						// Update the existing attachment with new metadata if any changes are present.
						if ( $saved_attachment_id && is_numeric( $saved_attachment_id ) && 'attachment' === get_post_type( $saved_attachment_id ) ) {
							$this->add_source_metadata_to_file( $saved_attachment_id, $attachment_metadata );
						}
					}

					$successful_uploads[] = array(
						'id'           => $saved_attachment_id,
						'url'          => wp_get_attachment_url( $saved_attachment_id ),
						'title'        => $media_title,
						'parent_id'    => $parent_media_id,
						'parent_terms' => Utils::get_onemedia_attachment_terms( $saved_attachment_id ) ?? array(),
					);
				} else {
					// Media already shared but in different configuration. Convert media from non-sync to sync.
					if ( ! is_numeric( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
						$errors[] = array(
							'url'   => $media_url,
							'title' => $media_title,
							'error' => __( 'Invalid attachment ID provided for converted media.', 'onemedia' ),
						);
						continue;
					}

					if ( ! hash_equals( wp_json_encode( $saved_attachment_id ), wp_json_encode( $attachment_id ) ) ) {
						$errors[] = array(
							'url'   => $media_url,
							'title' => $media_title,
							'error' => __( 'Attachment ID does not match the saved attachment ID for the parent media.', 'onemedia' ),
						);
						continue;
					}

					// Add attachment metadata for sync status.
					if ( $sync_status ) {
						update_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, $sync_status );
					}

					// Update the existing attachment with new metadata if any changes are present.
					$this->add_source_metadata_to_file( $attachment_id, $attachment_metadata );

					$successful_uploads[] = array(
						'id'           => $attachment_id,
						'url'          => wp_get_attachment_url( $attachment_id ),
						'title'        => $media_title,
						'parent_id'    => $parent_media_id,
						'parent_terms' => Utils::get_onemedia_attachment_terms( $attachment_id ) ?? array(),
					);
				}
			} else { // New media file, not shared previously.

				// Check for unsupported mime types before adding.
				if ( ! in_array( $media_mime_type, Utils::get_supported_mime_types(), true ) ) {
					$unsupported_file_types[] = Utils::get_file_type_label( $media_mime_type );
					continue;
				}

				// Get file details and check if it's a local file.
				$file_details = $this->handle_local_url( $media_url );

				if ( is_wp_error( $file_details ) ) {
					$errors[] = array(
						'url'   => $media_url,
						'title' => $media_title,
						'error' => $file_details->get_error_message(),
					);
					continue;
				}

				// Get all terms of onemedia_media_type taxonomy from current media file.
				$terms = array( ONEMEDIA_PLUGIN_TAXONOMY_TERM );

				// Add the source attachment metadata to the media file.
				$file_details['attachment_metadata'] = $attachment_metadata;

				// Insert the attachment with the file details.
				$attachment_id = $this->create_attachment_from_file( $file_details, $media_title, $sync_status, $terms );

				if ( is_wp_error( $attachment_id ) ) {
					// Clean up temp file if one was created.
					if ( isset( $file_details['temp_file'] ) && file_exists( $file_details['temp_file'] ) ) {
						// Ignoring the PHPCS warning because the $temp_file is in the /tmp/ directory.
						if ( ! unlink( $file_details['temp_file'] ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
							$errors[] = array(
								'url'   => $media_url,
								'title' => $media_title,
								'error' => __( 'Failed to delete temporary file.', 'onemedia' ),
							);
						}
					}

					// Extract all error messages from WP_Error object.
					$attachment_errors = $attachment_id->get_error_messages();

					if ( ! is_array( $attachment_errors ) || empty( $attachment_errors ) ) {
						$attachment_errors = array(
							__( 'Unknown error occurred while creating attachment.', 'onemedia' ),
						);
					}

					foreach ( $attachment_errors as $error_message ) {
						$errors[] = array(
							'url'   => $media_url,
							'title' => $media_title,
							'error' => $error_message,
						);
					}
					continue;
				}

				// Add the parent to child attachment mapping to option to prevent duplicate media files.
				$attachment_key_map[ $parent_media_id ] = $attachment_id;

				$saved_attachment_key_map = Utils::get_attachment_key_map();
				if ( ! hash_equals( wp_json_encode( $saved_attachment_key_map ), wp_json_encode( $attachment_key_map ) ) ) {
					$success = update_option( Constants::ONEMEDIA_ATTACHMENT_KEY_MAP_OPTION, $attachment_key_map );

					if ( ! $success ) {
						$errors[] = array(
							'url'   => $media_url,
							'title' => $media_title,
							'error' => __( 'Failed to update attachment key map option.', 'onemedia' ),
						);
						continue;
					}
				}

				$successful_uploads[] = array(
					'id'           => $attachment_id,
					'url'          => wp_get_attachment_url( $attachment_id ),
					'title'        => $media_title,
					'parent_id'    => $parent_media_id,
					'parent_terms' => Utils::get_onemedia_attachment_terms( $attachment_id ) ?? array(),
				);
			}
		}

		// If there were any unsupported file types.
		if ( ! empty( $unsupported_file_types ) ) {
			// Remove duplicate unsupported file types.
			$unsupported_file_types = array_unique( $unsupported_file_types );
			$unsupported_file_types = implode( ', ', $unsupported_file_types );

			return new \WP_Error(
				'unsupported_file_types',
				sprintf(
					/* translators: %1$s: Site name, %2$s: Unsupported file types. */
					__( '%1$s site doesn\'t support the following file type(s): %2$s.', 'onemedia' ),
					Utils::get_sitename_by_url( get_bloginfo( 'url' ) ),
					$unsupported_file_types,
				),
				array(
					'status'             => 500,
					'errors'             => $errors,
					'media'              => $successful_uploads,
					'success'            => false,
					'is_mime_type_error' => true,
				)
			);
		}

		// If there were any errors.
		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'media_addition_failed',
				sprintf(
					/* translators: %s: Site name */
					__( 'Some media files failed to upload to %s site.', 'onemedia' ),
					get_bloginfo( 'name' )
				),
				array(
					'status'  => 500,
					'errors'  => $errors,
					'media'   => $successful_uploads,
					'success' => false,
				)
			);
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Media files added.', 'onemedia' ),
				'media'   => $successful_uploads,
				'status'  => 200,
				'success' => true,
			)
		);
	}

	/**
	 * Update existing attachment metadata.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response after updating attachment metadata.
	 */
	public function update_existing_attachment( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$attachment_id = $request->get_param( 'attachment_id' );
		$sync_option   = $request->get_param( 'sync_option' );

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

		if ( empty( $sync_option ) || ! is_string( $sync_option ) || ( 'sync' !== $sync_option && 'no_sync' !== $sync_option ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Get attachment file type.
		$attachment_file_type = get_post_mime_type( $attachment_id );

		// Validate file type.
		if ( ! in_array( $attachment_file_type, Utils::get_supported_mime_types(), true ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid attachment file type provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		if ( 'sync' === $sync_option ) {
			// Assign the term to the attachment.
			if ( taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) ) {
				$success = wp_set_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_PLUGIN_TAXONOMY, true );

				if ( is_wp_error( $success ) ) {
					return new \WP_Error(
						'error',
						__( 'Failed to assign term to attachment.', 'onemedia' ),
						array(
							'status'  => 500,
							'success' => false,
						)
					);
				}
			}

			// Check if the media is already marked as sync media.
			$is_sync_media = Utils::is_sync_attachment( $attachment_id );
			if ( $is_sync_media ) {
				return rest_ensure_response(
					array(
						'message' => __( 'Media has been added already.', 'onemedia' ),
						'status'  => 500,
						'success' => false,
					)
				);
			}

			// Assign the meta key to the attachment.
			update_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, true );

			// Convert non sync to sync media if it was previously shared as non sync.
			// Check if the media is already shared in non-sync mode.
			$shared_sites = Utils::get_sync_sites_postmeta( $attachment_id );
			if ( is_array( $shared_sites ) ) {
				// Perform the media sync operation here.
				$brand_site_prefix = '/wp-json/' . Constants::NAMESPACE . '/add-media';

				// Failed to sync media files to brand sites.
				$failed_sites = array();

				// Success response.
				$success_response = array();

				// Get all registered brand sites to compare endpoint and get API token before sharing media.
				$all_brand_sites = Utils::get_all_brand_sites();

				foreach ( $shared_sites as $site ) {
					$site_url = $site['site'];

					// Strip the trailing slash.
					$site_url   = rtrim( $site_url, '/' );
					$site_name  = Utils::get_sitename_by_url( $site_url );
					$site_token = '';

					if ( empty( $site_url ) ) {
						$failed_sites[] = array(
							'site_name' => $site_name,
							'site'      => $site,
							'message'   => __( 'Invalid site URL.', 'onemedia' ),
							'site_url'  => $site_url . $brand_site_prefix,
							'token'     => $site_token,
							'child_key' => Utils::get_onemedia_api_key( 'default_api_key' ),
						);
						continue;
					}

					// Find the site in all brand sites to get its API token.
					foreach ( $all_brand_sites as $site_data ) {
						// Trim trailing slash.
						if ( rtrim( $site_data['siteUrl'], '/' ) === rtrim( $site_url, '/' ) ) {
							$site_token = $site_data['apiKey'];
							break;
						}
					}

					// This is the attachment id on the brand site since this media was previously shared.
					$child_id = $site['id'];
					if ( ! isset( $child_id ) ) {
						$failed_sites[] = array(
							'site_name' => $site_name,
							'site'      => $site,
							'message'   => __( 'Invalid child ID data.', 'onemedia' ),
							'site_url'  => $site_url . $brand_site_prefix,
							'token'     => $site_token,
							'child_key' => Utils::get_onemedia_api_key( 'default_api_key' ),
						);
						continue;
					}

					// Share the attachment metadata with the brand sites.
					// Get attachment metadata.
					$attachment_data = wp_get_attachment_metadata( $attachment_id );

					$title = get_the_title( $attachment_id );

					// Get attachment title, alt text, caption and description.
					$attachment_data['post_title']  = $title;
					$attachment_data['alt_text']    = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
					$attachment_data['caption']     = get_post_field( 'post_excerpt', $attachment_id );
					$attachment_data['description'] = get_post_field( 'post_content', $attachment_id );

					// Add the file to convert its type from non sync to sync.
					$media_files = array(
						array(
							'id'              => $attachment_id,
							'url'             => wp_get_attachment_url( $attachment_id ),
							'title'           => $title,
							'child_id'        => $child_id,
							'mime_type'       => $attachment_file_type,
							'attachment_data' => $attachment_data,
						),
					);

					// Prepare the request to the brand site.
					$response = wp_remote_post(
						$site_url . $brand_site_prefix,
						array(
							'headers'   => array(
								'X-OneMedia-Token' => ( $site_token ),
								'Cache-Control'    => 'no-cache, no-store, must-revalidate',
							),
							'body'      => (
								array(
									'media_files' => $media_files,
									'sync_option' => $sync_option,
								)
							),
							'timeout'   => Constants::SYNC_MEDIA_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
							'sslverify' => false,
						)
					);

					$response_body = wp_remote_retrieve_body( $response );
					$response_body = json_decode( $response_body, true );

					// Check the response from the brand site.
					$response_code = wp_remote_retrieve_response_code( $response );

					if ( is_wp_error( $response ) || 200 !== $response_code ) {
						$errors             = $response_body['data']['errors'] ?? array();
						$is_mime_type_error = $response_body['data']['is_mime_type_error'] ?? false;

						$failed_sites[] = array(
							'site_name'          => $site_name,
							'site'               => $site,
							'message'            => $response_body['message'] ?? __( 'Failed to sync media files.', 'onemedia' ),
							'errors'             => $errors,
							'site_url'           => $site_url . $brand_site_prefix,
							'token'              => $site_token,
							'child_key'          => Utils::get_onemedia_api_key( 'default_api_key' ),
							'is_mime_type_error' => $is_mime_type_error,
						);
					}

					// If there are any successful media syncs, process them.
					// Successful response from the brand site, the media files were synced.
					$success_response[] = $response_body;

					$media_response_list = isset( $response_body['media'] ) ? $response_body['media'] : array();

					// In case of error response, media list is in data key.
					if ( empty( $media_response_list ) && isset( $response_body['data']['media'] ) ) {
						$media_response_list = $response_body['data']['media'];
					}

					if ( ! empty( $media_response_list ) ) {
						foreach ( $media_response_list as $media ) {
							$parent_id = $media['parent_id'];

							// Create option to store siteurl, parent media id and brand site media id.
							$brand_sites_synced_media = Utils::get_brand_sites_synced_media();
							if ( ! is_array( $brand_sites_synced_media ) ) {
								$brand_sites_synced_media = array();
							}

							// Add brand site media id to the option.
							$parent_sync_media_mapping = array(
								$site_url => $media['id'],
							);

							if ( ! isset( $brand_sites_synced_media[ $parent_id ] ) ) {
								$brand_sites_synced_media[ $parent_id ] = array();
							}

							$brand_sites_synced_media[ $parent_id ] = array_merge(
								$brand_sites_synced_media[ $parent_id ],
								$parent_sync_media_mapping
							);

							// Update the synced media mapping option only if there is a change.
							$saved_brand_sites_synced_media = Utils::get_brand_sites_synced_media();
							if ( ! hash_equals( wp_json_encode( $saved_brand_sites_synced_media ), wp_json_encode( $brand_sites_synced_media ) ) ) {
								$success = update_option( Constants::BRAND_SITES_SYNCED_MEDIA_OPTION, $brand_sites_synced_media );

								if ( ! $success ) {
									$failed_sites[] = array(
										'site_name' => $site_name,
										'site'      => $site,
										'message'   => __( 'Failed to update synced media.', 'onemedia' ),
										'site_url'  => $site_url . $brand_site_prefix,
										'token'     => $site_token,
										'child_key' => Utils::get_onemedia_api_key( 'default_api_key' ),
									);
									continue;
								}
							}
						}
					}
				}

				if ( ! empty( $failed_sites ) ) {
					return new \WP_Error(
						'sync_failed',
						__( 'Failed to sync media files to some brand sites.', 'onemedia' ),
						array(
							'attachment_id' => $attachment_id,
							'status'        => 500,
							'failed_sites'  => $failed_sites,
							'success'       => false,
						)
					);
				}

				return rest_ensure_response(
					array(
						'message'              => __( 'Sync media added successfully!', 'onemedia' ),
						'status'               => 200,
						'success_response'     => $success_response,
						'attachment_id'        => $attachment_id,
						'onemedia_sync_option' => Utils::get_brand_sites_synced_media(),
						'success'              => true,
					)
				);
			}
		} else {
			// If not syncing, set the sync status to false.
			update_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, 0 );
		}

		// Return success response with attachment ID.
		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'message'       => __( 'Attachment marked as sync media.', 'onemedia' ),
				'status'        => 200,
				'success'       => true,
			)
		);
	}

	/**
	 * Check if an attachment is a sync attachment.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response indicating if the attachment is a sync attachment.
	 */
	public function is_sync_attachment( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$attachment_id = $request->get_param( 'attachment_id' );

		// Validate attachment ID.
		if ( empty( $attachment_id ) || ! is_numeric( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'invalid_attachment_id',
				__( 'Invalid attachment ID provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		$is_sync = Utils::is_sync_attachment( $attachment_id );

		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'is_sync'       => $is_sync,
				'status'        => 200,
				'success'       => true,
			)
		);
	}

	/**
	 * Get sync versions of an attachment.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_Error|\WP_REST_Response The response containing sync versions of the attachment.
	 */
	public function get_sync_attachment_versions( \WP_REST_Request $request ): \WP_Error|\WP_REST_Response {
		$attachment_id = $request->get_param( 'attachment_id' );

		// Validate attachment ID.
		if ( empty( $attachment_id ) || ! is_numeric( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'invalid_attachment_id',
				__( 'Invalid attachment ID provided.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Check if attachment is not a sync attachment.
		$is_sync = Utils::is_sync_attachment( $attachment_id );
		if ( ! $is_sync ) {
			return new \WP_Error(
				'not_sync_attachment',
				__( 'Attachment is not a sync attachment.', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		$versions = Utils::get_sync_attachment_versions( $attachment_id );

		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'versions'      => $versions,
				'status'        => 200,
				'success'       => true,
			)
		);
	}

	/**
	 * Handle local domain URLs by creating a viable file path.
	 *
	 * @param string $url The local URL to process.
	 *
	 * @return array|\WP_Error File details or error.
	 */
	private function handle_local_url( string $url ): array|\WP_Error {
		// Try direct file system access first (works in some hosting environments).
		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['path'] ) ) {
			return new \WP_Error(
				'invalid_url',
				__( 'URL does not contain a valid path', 'onemedia' ),
				array(
					'status'  => 400,
					'success' => false,
				)
			);
		}

		// Try multiple possible paths to find the file.
		$possible_paths = array(
			// Direct absolute path conversion.
			ABSPATH . ltrim( $parsed_url['path'], '/' ),

			// For setups where wp-content is not at ABSPATH.
			ABSPATH . ltrim( str_replace( '/wp-content', 'wp-content', $parsed_url['path'] ), '/' ),

			// For multisite installations.
			WP_CONTENT_DIR . str_replace( '/wp-content', '', $parsed_url['path'] ),

			// In case of different domain pointing to same file structure.
			dirname( ABSPATH ) . $parsed_url['path'],
		);

		$file_path = null;

		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) ) {
				$file_path = $path;
				break;
			}
		}

		// If file found locally.
		if ( $file_path ) {
			return array(
				'path'   => $file_path,
				'name'   => basename( $file_path ),
				'type'   => wp_check_filetype( basename( $file_path ) )['type'],
				'source' => 'local',
			);
		}

		// If file not found directly, try curl fallback.
		return $this->fetch_remote_file( $url, '', true );
	}

	/**
	 * Fetch a remote file when direct file system access isn't available.
	 *
	 * @param string $url URL to fetch.
	 * @param string $temp_file Temporary file path.
	 * @param bool   $put_contents Whether to put contents into the temp file.
	 *
	 * @return array|\WP_Error File details or error
	 */
	private function fetch_remote_file( string $url, string $temp_file = '', bool $put_contents = false ): array|\WP_Error {
		// If tempfile is empty, create a new one.
		if ( empty( $temp_file ) && ! file_exists( $temp_file ) ) {
			// Create unique temp filename in the /tmp/ directory.
			$temp_file = wp_tempnam( basename( $url ) );
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$args = array(
			'headers'   => array(
				'Host'       => $host,
				'User-Agent' => 'Mozilla/5.0 WordPress/' . get_bloginfo( 'version' ),
			),
			'timeout'   => Constants::FETCH_MEDIA_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'sslverify' => false,
		);

		$response  = wp_remote_get( $url, $args );
		$status    = wp_remote_retrieve_response_code( $response );
		$file_data = wp_remote_retrieve_body( $response );
		$errors    = array();

		if ( is_wp_error( $response ) || 200 !== $status || empty( $file_data ) ) {
			// Ignoring the PHPCS warning because the $temp_file is in the /tmp/ directory.
			if ( file_exists( $temp_file ) && ! unlink( $temp_file ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
				$errors[] = array(
					'error' => __( 'Failed to delete temporary file.', 'onemedia' ),
				);
			}
			return new \WP_Error(
				'download_failed',
				sprintf(
				/* translators: %s: server status */
					__( 'Failed to download file from remote URL. Server status: %s', 'onemedia' ),
					$status
				),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		if ( $put_contents ) {
			// Ignoring the PHPCS warning since we are putting the file in the uploads directory.
			file_put_contents( $temp_file, $file_data ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		}

		return array(
			'path'      => $temp_file,
			'name'      => basename( $url ),
			'type'      => wp_check_filetype( basename( $url ) )['type'],
			'source'    => 'remote',
			'file_data' => $file_data,
			'temp_file' => $temp_file, // Flag so we know to clean it up later.
			'errors'    => $errors,
		);
	}

	/**
	 * Create a WordPress attachment from a file.
	 *
	 * @param array  $file_details File details from handle_local_url.
	 * @param string $title        Title for the attachment.
	 * @param string $sync_status  Sync status to be added as metadata.
	 * @param array  $terms        Terms to be assigned to the attachment.
	 *
	 * @return int|\WP_Error Attachment ID or error
	 */
	private function create_attachment_from_file( array $file_details, string $title, string $sync_status, array $terms = array() ): int|\WP_Error {
		// Get upload directory info.
		$uploads = wp_upload_dir();

		if ( isset( $uploads['error'] ) && false !== $uploads['error'] ) {
			return new \WP_Error(
				'upload_dir_error',
				$uploads['error'],
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		// Create unique filename in uploads directory.
		$filename = wp_unique_filename( $uploads['path'], $file_details['name'] );
		$new_file = $uploads['path'] . '/' . $filename;

		// Copy the file to the uploads directory.
		if ( ! copy( $file_details['path'], $new_file ) ) {
			return new \WP_Error(
				'file_copy_failed',
				__( 'Failed to copy file to uploads directory', 'onemedia' ),
				array(
					'status'  => 500,
					'success' => false,
				)
			);
		}

		// Create attachment post.
		$attachment = array(
			'guid'           => $uploads['url'] . '/' . $filename,
			'post_mime_type' => $file_details['type'],
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment.
		$attachment_id = wp_insert_attachment( $attachment, $new_file );

		$errors = array();

		if ( is_wp_error( $attachment_id ) ) {
			// Ignoring the PHPCS warning because the $temp_file is in the /tmp/ directory.
			if ( file_exists( $new_file ) && ! unlink( $new_file ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
				$errors[] = array(
					'error' => __( 'Failed to delete file after failed attachment insert.', 'onemedia' ),
				);
			}
			return $attachment_id;
		}

		// Add attachment metadata for sync status.
		if ( $sync_status ) {
			update_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, $sync_status );
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/image.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		// Generate and update attachment metadata.
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $new_file );

		if ( ! $attachment_data || empty( $attachment_data ) ) {
			$errors[] = array(
				'error' => __( 'Failed to generate attachment metadata.', 'onemedia' ),
			);
		}

		if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
			include_once ABSPATH . 'wp-admin/includes/media.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		}

		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		// Update attachment metadata from source file.
		$source_metadata = $file_details['attachment_metadata'] ?? array();
		$this->add_source_metadata_to_file( $attachment_id, $source_metadata );

		// Delete all onemedia_media_type terms for this attachment.
		wp_set_object_terms( $attachment_id, array(), ONEMEDIA_PLUGIN_TAXONOMY, false );

		// Set the attachment terms if provided.
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				wp_set_object_terms( $attachment_id, $term, ONEMEDIA_PLUGIN_TAXONOMY, false );
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'attachment_errors',
				__( 'Attachment created with some errors.', 'onemedia' ),
				array(
					'attachment_id' => $attachment_id,
					'status'        => 500,
					'errors'        => $errors,
					'success'       => false,
				)
			);
		}

		return $attachment_id;
	}

	/**
	 * Add source metadata (title, alt text, caption, description) to the attachment.
	 *
	 * @param int   $attachment_id   Attachment ID to update.
	 * @param array $source_metadata Source metadata from the original media.
	 *
	 * @return void
	 */
	private function add_source_metadata_to_file( int $attachment_id, array $source_metadata ): void {
		if ( empty( $source_metadata ) || empty( $attachment_id ) || ! is_numeric( $attachment_id ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		$update_post = array(
			'ID' => $attachment_id,
		);

		// Update the post title.
		if ( ! empty( $source_metadata['post_title'] ) ) {
			$update_post['post_title'] = $source_metadata['post_title'];
		}

		// Update the post excerpt (caption).
		if ( ! empty( $source_metadata['post_excerpt'] ) ) {
			$update_post['post_excerpt'] = $source_metadata['post_excerpt'];
		}

		// Update the post content (description).
		if ( ! empty( $source_metadata['post_content'] ) ) {
			$update_post['post_content'] = $source_metadata['post_content'];
		}

		// If there are fields to update, perform the update.
		if ( count( $update_post ) > 1 ) {
			wp_update_post( $update_post );
		}

		// Update alt text if provided.
		if ( ! empty( $source_metadata['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $source_metadata['alt_text'] );
		}
	}
}
