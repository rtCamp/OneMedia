<?php
/**
 * Add all Admin site classes here.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Admin\Media_Taxonomy;
use OneMedia\Plugin_Configs\Constants;
use OneMedia\Traits\Singleton;

/**
 * Class Admin
 */
class Admin {

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
		Media_Taxonomy::get_instance();
		add_action( 'wp_ajax_onemedia_sync_media_upload', array( $this, 'handle_sync_media_upload' ) );
		add_action( 'wp_ajax_onemedia_replace_media', array( $this, 'handle_media_replace' ) );

		// Add sync status to attachment data for JavaScript (Media Modal).
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_sync_meta' ), 10, 3 );

		// Clear cache when attachment sync status changes.
		add_action( 'updated_post_meta', array( $this, 'clear_sync_cache' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'clear_sync_cache' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'clear_sync_cache' ), 10, 4 );
	}

	/**
	 * Handle onemedia_sync upload via AJAX.
	 *
	 * This function will be used for fallback when WP media library is not available for handling uploads.
	 *
	 * @return void
	 */
	public function handle_sync_media_upload(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'onemedia_upload_media' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload sync media.', 'onemedia' ) ), 403 );
		}

		if ( ! isset( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'onemedia' ) ), 400 );
		}

		$file = array(
			'name'     => isset( $_FILES['file']['name'] ) ? sanitize_file_name( $_FILES['file']['name'] ) : '',
			'type'     => isset( $_FILES['file']['type'] ) ? sanitize_mime_type( $_FILES['file']['type'] ) : '',
			'tmp_name' => isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( $_FILES['file']['tmp_name'] ) : '',
			'error'    => isset( $_FILES['file']['error'] ) ? intval( $_FILES['file']['error'] ) : 0,
			'size'     => isset( $_FILES['file']['size'] ) ? intval( $_FILES['file']['size'] ) : 0,
		);

		// Decode filename to handle special characters.
		$file['name'] = Utils::decode_filename( $file['name'] );

		// Validate file type.
		if ( ! in_array( $file['type'], Utils::get_supported_mime_types(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPG, PNG, WEBP, BMP, SVG and GIF files are allowed.', 'onemedia' ) ), 400 );
		}

		// Move the uploaded file to the uploads directory.
		$upload_dir  = wp_upload_dir();
		$target_path = $upload_dir['path'] . '/' . basename( $file['name'] );
		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to move uploaded file.', 'onemedia' ) ), 500 );
		}

		// Insert the file into the media library.
		$attachment    = array(
			'guid'           => $upload_dir['url'] . '/' . basename( $file['name'] ),
			'post_mime_type' => $file['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $target_path );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to insert attachment into media library.', 'onemedia' ) ), 500 );
		}

		// Generate attachment metadata and update the attachment.
		include_once ABSPATH . 'wp-admin/includes/image.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		$metadata = wp_generate_attachment_metadata( $attachment_id, $target_path );

		if ( ! $metadata ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate attachment metadata.', 'onemedia' ) ), 500 );
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$sync_status = filter_input( INPUT_GET, 'onemedia_sync_media_upload', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $sync_status ) && 'true' === $sync_status ) {
			// Assign the 'onemedia' term to the attachment.
			if ( taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) ) {
				wp_set_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_PLUGIN_TAXONOMY, true );
			}
		}

		// Return success response with attachment ID.
		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'message'       => __( 'Sync media uploaded successfully.', 'onemedia' ),
			)
		);
	}

	/**
	 * Handle media replacement via AJAX on governing site.
	 *
	 * @return void
	 */
	public function handle_media_replace(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'onemedia_upload_media' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to replace media.', 'onemedia' ) ), 403 );
		}

		// Check if this is a version restore operation.
		$is_version_restore = isset( $_POST['is_version_restore'] ) ?? filter_input( INPUT_POST, 'is_version_restore', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$is_version_restore = ! empty( $is_version_restore ) && $is_version_restore ? true : false;

		// Get the file input.
		$input_file = isset( $_FILES['file'] ) && ! empty( $_FILES['file']['name'] ) ? wp_unslash( $_FILES['file'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized later in sanitize_file_input().

		if ( ! $input_file && $is_version_restore ) {
			$file_json = isset( $_POST['file'] ) ? wp_unslash( $_POST['file'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized later in sanitize_file_input().
			if ( $file_json && ! empty( $file_json ) ) {
				$decoded = json_decode( $file_json, true );
				if ( is_array( $decoded ) ) {
					$input_file = $decoded;
				}
			}

			if ( is_array( $input_file ) ) {
				$input_file['tmp_name'] = $input_file['path'] ?? '';
			}
		}

		// Sanitize file input.
		$file = $this->sanitize_file_input( $input_file );

		if ( is_wp_error( $file ) ) {
			wp_send_json_error( array( 'message' => $file->get_error_message() ), 400 );
		}

		// Get and validate media ID.
		$current_media_id = filter_input( INPUT_POST, 'current_media_id', FILTER_VALIDATE_INT );
		if ( empty( $current_media_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media ID.', 'onemedia' ) ), 400 );
		}
		$current_media_id = absint( $current_media_id );

		if ( $is_version_restore ) {
			$result = $this->restore_attachment_version( $current_media_id, $file );
		} else {
			// Capture original file information before updating.
			$original_data = array(
				'attachment' => get_post( $current_media_id ),
				'metadata'   => wp_get_attachment_metadata( $current_media_id ),
				'file_path'  => get_attached_file( $current_media_id ),
				'url'        => wp_get_attachment_url( $current_media_id ),
				'alt_text'   => get_post_meta( $current_media_id, '_wp_attachment_image_alt', true ),
				'caption'    => wp_get_attachment_caption( $current_media_id ),
			);

			// Update the attachment with the new file.
			$result = $this->update_attachment( $current_media_id, $file );

			// Update version history, add the new version to versions array.
			if ( ! is_wp_error( $result ) ) {
				$this->update_attachment_versions( $current_media_id, $file, $result, $original_data );
			}
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		// Return success response.
		wp_send_json_success(
			array(
				'attachment_id' => $current_media_id,
				'message'       => __( 'Media replaced successfully.', 'onemedia' ),
			)
		);
	}

	/**
	 * Process and sanitize file data from either $_FILES or $_POST.
	 *
	 * @param array|null $input_file The raw input file data.
	 *
	 * @return array|WP_Error Sanitized file array or WP_Error on failure.
	 */
	public function sanitize_file_input( $input_file ): array|\WP_Error {
		// Verify file input exists.
		if ( ! isset( $input_file ) || empty( $input_file['name'] ) ) {
			return new \WP_Error( 'invalid_input', __( 'No file uploaded.', 'onemedia' ) );
		}

		// Sanitize all file fields.
		$file = array(
			'name'     => isset( $input_file['name'] ) ? sanitize_file_name( $input_file['name'] ) : '',
			'type'     => isset( $input_file['type'] ) ? sanitize_mime_type( $input_file['type'] ) : '',
			'tmp_name' => isset( $input_file['tmp_name'] ) ? sanitize_text_field( $input_file['tmp_name'] ) : '',
			'error'    => isset( $input_file['error'] ) ? intval( $input_file['error'] ) : 0,
			'size'     => isset( $input_file['size'] ) ? intval( $input_file['size'] ) : 0,
		);

		if ( isset( $input_file['attachment_id'] ) ) {
			$file['attachment_id'] = intval( $input_file['attachment_id'] );
		}
		if ( isset( $input_file['path'] ) ) {
			$file['path'] = sanitize_text_field( $input_file['path'] );
		}
		if ( isset( $input_file['url'] ) ) {
			$file['url'] = esc_url_raw( $input_file['url'] );
		}
		if ( isset( $input_file['guid'] ) ) {
			$file['guid'] = esc_url_raw( $input_file['guid'] );
		}
		if ( isset( $input_file['filename'] ) ) {
			$file['filename'] = sanitize_file_name( $input_file['filename'] );
		}
		if ( isset( $input_file['mime_type'] ) ) {
			$file['mime_type'] = sanitize_mime_type( $input_file['mime_type'] );
		}
		if ( isset( $input_file['alt'] ) ) {
			$file['alt'] = sanitize_text_field( $input_file['alt'] );
		}
		if ( isset( $input_file['caption'] ) ) {
			$file['caption'] = sanitize_text_field( $input_file['caption'] );
		}
		if ( isset( $input_file['metadata'] ) && is_array( $input_file['metadata'] ) ) {
			$file['metadata'] = $input_file['metadata'];
		}
		if ( isset( $input_file['dimensions'] ) && is_array( $input_file['dimensions'] ) ) {
			$file['dimensions'] = $input_file['dimensions'];
		}
		if ( isset( $input_file['checksum'] ) ) {
			$file['checksum'] = sanitize_text_field( $input_file['checksum'] );
		}

		// Decode filename.
		$file['name'] = Utils::decode_filename( $file['name'] );

		// Validate mime type.
		if ( ! in_array( $file['type'], Utils::get_supported_mime_types(), true ) ) {
			return new \WP_Error(
				'invalid_file_type',
				__( 'Invalid file type. Only JPG, PNG, WEBP, BMP, SVG and GIF files are allowed.', 'onemedia' )
			);
		}

		return $file;
	}

	/**
	 * Update attachment with new file.
	 *
	 * @param int   $attachment_id    The attachment ID.
	 * @param array $file             The file data.
	 * @param bool  $is_version_restore Whether this is a version restore operation.
	 * @param array $version_data     Version data for restore operations.
	 *
	 * @return array|WP_Error Result data or WP_Error on failure.
	 */
	public function update_attachment( int $attachment_id, array $file, bool $is_version_restore = false, array $version_data = array() ): array|\WP_Error {
		// Get existing attachment data.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new \WP_Error( 'invalid_attachment', __( 'Invalid attachment.', 'onemedia' ) );
		}

		// Get current file info.
		$current_file = get_attached_file( $attachment_id );
		$alt_text     = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$caption      = wp_get_attachment_caption( $attachment_id );

		if ( $is_version_restore ) {
			// For version restore, use existing file path and data.
			if ( ! $version_data ) {
				return new \WP_Error( 'missing_version_data', __( 'Missing version data for restore operation.', 'onemedia' ) );
			}

			$file_data = $version_data['file'];

			// Check if file still exists at the saved location.
			if ( ! file_exists( $file_data['path'] ) ) {
				return new \WP_Error( 'file_not_found', __( 'This version file could not be found. It may have been deleted.', 'onemedia' ) );
			}

			$target_path = $file_data['path'];
			$new_url     = $file_data['url'];
			$mime_type   = $file_data['mime_type'] ?? $file_data['type'];
			$title       = sanitize_file_name( pathinfo( $file_data['name'], PATHINFO_FILENAME ) );

			// Use existing metadata from version history.
			$metadata = $file_data['metadata'] ?? array();
		} else {
			// For new uploads, process the uploaded file.
			$upload_dir  = wp_upload_dir();
			$filename    = wp_unique_filename( $upload_dir['path'], $file['name'] );
			$target_path = $upload_dir['path'] . '/' . $filename;
			$new_url     = $upload_dir['url'] . '/' . $filename;
			$mime_type   = $file['type'];
			$title       = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );

			// Move the uploaded file to the uploads directory.
			if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
				return new \WP_Error( 'file_move_failed', __( 'Failed to move uploaded file.', 'onemedia' ) );
			}

			// Generate and update attachment metadata.
			include_once ABSPATH . 'wp-admin/includes/image.php';
			$metadata = wp_generate_attachment_metadata( $attachment_id, $target_path );
			if ( ! $metadata ) {
				return new \WP_Error( 'metadata_generation_failed', __( 'Failed to generate attachment metadata.', 'onemedia' ) );
			}
		}

		// Update attachment URL across posts.
		onemedia_replace_image_across_all_post_types(
			$attachment_id,
			$new_url,
			$alt_text,
			$caption
		);

		// Update attachment data.
		$attachment_data = array(
			'ID'             => $attachment_id,
			'guid'           => $new_url,
			'post_mime_type' => $mime_type,
			'post_title'     => $title,
		);

		$result = wp_update_post( $attachment_data );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update attachment.', 'onemedia' ) );
		}

		// Update attachment metadata.
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Update the attachment file path.
		update_attached_file( $attachment_id, $target_path );

		// Preserve existing taxonomy terms.
		if ( taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) ) {
			$current_terms = wp_get_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $attachment_id, $current_terms, ONEMEDIA_PLUGIN_TAXONOMY, false );
		}

		// Update synced media on brand sites.
		$hooks_instance = Hooks::get_instance();
		$hooks_instance->update_sync_attachments( $attachment_id );

		return array(
			'attachment_id' => $attachment_id,
			'new_url'       => $new_url,
			'target_path'   => $target_path,
			'metadata'      => $metadata,
		);
	}

	/**
	 * Restore a specific version of an attachment.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $version_file  The version file data to restore.
	 *
	 * @return array|WP_Error Result data or WP_Error on failure.
	 */
	public function restore_attachment_version( int $attachment_id, array $version_file ): array|\WP_Error {
		// Get existing versions.
		$existing_versions = Utils::get_sync_attachment_versions( $attachment_id );
		$is_new_meta       = ! is_array( $existing_versions ) || empty( $existing_versions );
		$existing_versions = is_array( $existing_versions ) ? array_values( $existing_versions ) : array();

		// Find the index of the version being restored.
		$restore_index = null;
		foreach ( $existing_versions as $index => $version ) {
			if ( isset( $version['file']['path'] ) && $version['file']['path'] === $version_file['path'] ) {
				$restore_index = $index;
				break;
			}
		}

		// If no versions exist or the specified version is not found, return error.
		if ( $is_new_meta || is_null( $restore_index ) ) {
			return new \WP_Error( 'no_version_history', __( 'No version history available for this attachment.', 'onemedia' ) );
		}

		// Get the version being restored.
		$restored_version = $existing_versions[ $restore_index ];

		// Update the attachment using the unified function (with version restore flag).
		$result = $this->update_attachment( $attachment_id, $version_file, true, $restored_version );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update timestamp for last used.
		$timestamp = time();

		// Update the versions list.
		if ( is_array( $existing_versions ) && ! empty( $existing_versions ) ) {
			// Remove the restored version from its current position.
			if ( isset( $existing_versions[ $restore_index ] ) ) {
				unset( $existing_versions[ $restore_index ] );
			}

			// Update its timestamp.
			$restored_version['last_used'] = $timestamp;

			// Reindex and add restored version to the front.
			$existing_versions = array_values( $existing_versions );
			array_unshift( $existing_versions, $restored_version );

			// Keep only the 10 most recent versions.
			$existing_versions = array_slice( $existing_versions, 0, 10 );

			// Update version history.
			Utils::update_sync_attachment_versions( $attachment_id, $existing_versions );
		}

		return $result;
	}

	/**
	 * Update attachment version history.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $file The file data.
	 * @param array $update_result The result from update_attachment function.
	 * @param array $original_data The original file information.
	 * @return void
	 */
	public function update_attachment_versions( int $attachment_id, array $file, array $update_result, array $original_data ): void {
		// Get existing versions.
		$existing_versions = Utils::get_sync_attachment_versions( $attachment_id );
		$is_new_meta       = ! is_array( $existing_versions ) || empty( $existing_versions );
		$existing_versions = is_array( $existing_versions ) ? array_values( $existing_versions ) : array();

		// Original file information.
		$attachment   = $original_data['attachment'];
		$old_metadata = $original_data['metadata'];
		$current_file = $original_data['file_path'];
		$old_url      = $original_data['url'];
		$alt_text     = $original_data['alt_text'];
		$caption      = $original_data['caption'];

		// Add current timestamp.
		$timestamp = time();

		// Snapshot of the current (pre-replacement) file.
		$current_snapshot = array(
			'last_used' => $timestamp,
			'file'      => array(
				'attachment_id' => $attachment_id,
				'path'          => $current_file,
				'url'           => $old_url,
				'guid'          => is_object( $attachment ) ? $attachment->guid : $old_url,
				'name'          => wp_basename( $current_file ),
				'type'          => get_post_mime_type( $attachment_id ),
				'alt'           => $alt_text,
				'caption'       => $caption,
				'size'          => ( file_exists( $current_file ) ? (int) filesize( $current_file ) : 0 ),
				'metadata'      => is_array( $old_metadata ) ? $old_metadata : array(),
				'dimensions'    => ( is_array( $old_metadata ) && isset( $old_metadata['width'], $old_metadata['height'] ) )
					? array(
						'width'  => (int) $old_metadata['width'],
						'height' => (int) $old_metadata['height'],
					)
					: array(),
				'checksum'      => ( file_exists( $current_file ) ? md5_file( $current_file ) : '' ),
			),
		);

		// Snapshot of the new file.
		$new_snapshot = array(
			'last_used' => $timestamp,
			'file'      => array(
				'attachment_id' => $attachment_id,
				'path'          => $update_result['target_path'],
				'url'           => $update_result['new_url'],
				'guid'          => $update_result['new_url'],
				'name'          => wp_basename( $update_result['target_path'] ),
				'type'          => $file['type'],
				'alt'           => $alt_text,
				'caption'       => $caption,
				'size'          => ( file_exists( $update_result['target_path'] ) ? (int) filesize( $update_result['target_path'] ) : (int) $file['size'] ),
				'metadata'      => $update_result['metadata'] ?? array(),
				'dimensions'    => isset( $update_result['metadata']['width'], $update_result['metadata']['height'] )
					? array(
						'width'  => (int) $update_result['metadata']['width'],
						'height' => (int) $update_result['metadata']['height'],
					)
					: array(),
				'checksum'      => ( file_exists( $update_result['target_path'] ) ? md5_file( $update_result['target_path'] ) : '' ),
			),
		);

		if ( $is_new_meta ) {
			// First replacement: new (current) at 0, previous (old) at 1.
			$versions = array( $new_snapshot, $current_snapshot );
		} else {
			// Subsequent replacement: new goes to index 0, others shift down.
			$versions = $existing_versions;
			array_unshift( $versions, $new_snapshot );
		}

		// Keep only the 5 most recent versions.
		$versions = array_slice( $versions, 0, 5 );

		Utils::update_sync_attachment_versions( $attachment_id, $versions );
	}

	/**
	 * Add sync status meta to attachment data for JavaScript.
	 *
	 * @param array    $response   The prepared attachment data.
	 * @param \WP_Post $attachment The attachment post object.
	 *
	 * @return array Modified attachment data with sync status.
	 */
	public function add_sync_meta( array $response, \WP_Post $attachment ): array {
		// if attachment ID is not set, return original response.
		if ( ! isset( $attachment->ID ) ) {
			return $response;
		}

		// Add sync status to the response.
		$response['is_sync_attachment'] = self::is_sync_attachment( $attachment->ID );

		return $response;
	}

	/**
	 * Check if an attachment is a sync attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool True if sync attachment, false otherwise.
	 */
	private static function is_sync_attachment( int $attachment_id ): bool {
		// Validate input.
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		// Check object cache first.
		$cache_key = "onemedia_sync_status_{$attachment_id}";
		$cached    = wp_cache_get( $cache_key, 'onemedia' );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		// get post meta value.
		$meta_value = '';
		$is_sync    = false;
		if ( Utils::is_brand_site() ) { // totally not sure why same meta is not used on both sites & why string instead of boolean.
			$meta_value = get_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, true );
			if ( 'sync' === $meta_value ) {
				$is_sync = true;
			}
		} elseif ( Utils::is_governing_site() ) {
			$meta_value = get_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY, true );
			$is_sync    = '1' === $meta_value || 1 === $meta_value || true === $meta_value;
		}

		// update cache.
		wp_cache_set( $cache_key, $is_sync, 'onemedia', HOUR_IN_SECONDS );

		return $is_sync;
	}

	/**
	 * Clear sync status cache when the relevant post meta is updated.
	 *
	 * @param int    $object_id  The attachment ID.
	 * @param string $meta_key   The meta key.
	 *
	 * @return void -- clear cache if the meta key matches.
	 */
	public function clear_sync_cache( int $object_id, string $meta_key ): void {
		if ( 'is_onemedia_sync' === $meta_key ) {
			wp_cache_delete( "onemedia_sync_status_{$object_id}", 'onemedia' );
		}
	}
}
