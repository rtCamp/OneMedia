<?php
/**
 * Add all Admin site classes here.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Admin\Media_Taxonomy;
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

		if ( ! isset( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'onemedia' ) ), 400 );
		}

		$current_media_id = filter_input( INPUT_POST, 'current_media_id', FILTER_VALIDATE_INT );
		if ( empty( $current_media_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media ID.', 'onemedia' ) ), 400 );
		}
		$current_media_id = absint( $current_media_id );

		$file = array(
			'name'     => isset( $_FILES['file']['name'] ) ? sanitize_file_name( $_FILES['file']['name'] ) : '',
			'type'     => isset( $_FILES['file']['type'] ) ? sanitize_mime_type( $_FILES['file']['type'] ) : '',
			'tmp_name' => isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( $_FILES['file']['tmp_name'] ) : '',
			'error'    => isset( $_FILES['file']['error'] ) ? intval( $_FILES['file']['error'] ) : 0,
			'size'     => isset( $_FILES['file']['size'] ) ? intval( $_FILES['file']['size'] ) : 0,
		);

		// Decode filename to handle special characters.
		$file['name'] = html_entity_decode( $file['name'], ENT_QUOTES, 'UTF-8' );

		$attachment_id = absint( $current_media_id );

		// Validate file type.
		if ( ! in_array( $file['type'], Utils::get_supported_mime_types(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPG, PNG, WEBP, BMP, SVG and GIF files are allowed.', 'onemedia' ) ), 400 );
		}

		// Get existing attachment data.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'onemedia' ) ), 400 );
		}

		// Get current file path and new upload path.
		$current_file = get_attached_file( $attachment_id );
		$upload_dir   = wp_upload_dir();
		$filename     = wp_unique_filename( $upload_dir['path'], $file['name'] );
		$target_path  = $upload_dir['path'] . '/' . $filename;
		$new_url      = $upload_dir['url'] . '/' . $filename;

		// Move the uploaded file to the uploads directory.
		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to move uploaded file.', 'onemedia' ) ), 500 );
		}

		// Get old and new attachment URLs.
		$old_url              = wp_get_attachment_url( $attachment_id );
		$attachment_permalink = $new_url;
		$alt_text             = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$caption              = wp_get_attachment_caption( $attachment_id );

		// Update attachment url.
		onemedia_replace_image_across_all_post_types(
			$attachment_id,
			$attachment_permalink,
			$alt_text,
			$caption,
		);

		// Update attachment data.
		$attachment_data = array(
			'ID'             => $attachment_id,
			'guid'           => $new_url,
			'post_mime_type' => $file['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
		);

		$result = wp_update_post( $attachment_data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update attachment.', 'onemedia' ) ), 500 );
		}

		// Generate and update attachment metadata.
		include_once ABSPATH . 'wp-admin/includes/image.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		$metadata = wp_generate_attachment_metadata( $attachment_id, $target_path );
		if ( ! $metadata ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate attachment metadata.', 'onemedia' ) ), 500 );
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Update the attachment file path.
		update_attached_file( $attachment_id, $target_path );

		// Preserve existing taxonomy terms.
		if ( taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) ) {
			$current_terms = wp_get_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $attachment_id, $current_terms, ONEMEDIA_PLUGIN_TAXONOMY, false );
		}

		$hooks_instance = Hooks::get_instance();

		// Update synced media on brand sites.
		$hooks_instance->update_sync_attachments( $attachment_id );

		// Return success response.
		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'message'       => __( 'Media replaced successfully.', 'onemedia' ),
			)
		);
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
		$file['name'] = html_entity_decode( $file['name'], ENT_QUOTES, 'UTF-8' );

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
}
