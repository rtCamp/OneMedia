<?php
/**
 * Term protection and restriction.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Constants;
use OneMedia\Utils;

/**
 * Class CPT_Restriction
 */
class MediaProtection implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'delete_attachment', array( $this, 'maybe_block_media_delete' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'show_deletion_notice' ) );
		add_action( 'add_attachment', array( $this, 'add_onemedia_term_to_attachment' ) );
	}

	/**
	 * Add onemedia term to the attachment when added via the "Add Sync Media" button.
	 *
	 * @param int $attachment_id The ID of the attachment being added.
	 *
	 * @return void
	 */
	public function add_onemedia_term_to_attachment( int $attachment_id ): void {
		$is_onemedia_attachment = metadata_exists( 'post', $attachment_id, Media_Sharing_Controller::IS_ONEMEDIA_SYNC_POSTMETA_KEY );
		if ( $attachment_id && taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) && $is_onemedia_attachment ) {
			// Assign the 'onemedia' term to the attachment.
			$success = wp_set_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_PLUGIN_TAXONOMY, true );

			if ( is_wp_error( $success ) ) {
				wp_send_json_error( array( 'message' => __( 'Failed to assign taxonomy term to attachment.', 'onemedia' ) ), 500 );
			}
		}
	}

	/**
	 * Block deletion of media attachments assigned to the 'onemedia' term.
	 *
	 * This method prevents the deletion of media attachments that are assigned to the 'onemedia' term,
	 * ensuring that important media cannot be removed unintentionally.
	 *
	 * @param int $attachment_id The ID of the attachment being deleted.
	 *
	 * @return int|\WP_Error The attachment ID if deletion is allowed, or a WP_Error if blocked.
	 */
	public function maybe_block_media_delete( int $attachment_id ): int|\WP_Error {
		$terms = UserInterface::get_onemedia_attachment_post_terms( $attachment_id, array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) && isset( array_flip( $terms )[ ONEMEDIA_PLUGIN_TAXONOMY_TERM ] ) ) {
			// Set a transient to show a notice on the next admin page load.
			set_transient( 'onemedia_delete_notice', true, 30 );
			// Redirect back to prevent deletion.
			$redirect_url = admin_url( 'upload.php' );
			wp_safe_redirect( $redirect_url );
			exit;
		}
		return $attachment_id;
	}


	/**
	 * Show a notice when trying to delete media that is assigned to the 'onemedia' term.
	 *
	 * This method displays an admin notice when a user attempts to delete media
	 * that is assigned to the 'onemedia' term, informing them that deletion is not allowed.
	 *
	 * @return void
	 */
	public function show_deletion_notice(): void {
		$onemedia_delete_transient = get_transient( 'onemedia_delete_notice' );
		if ( $onemedia_delete_transient ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'You cannot delete media that is assigned to the "onemedia" term.', 'onemedia' ); ?></p>
			</div>
			<?php
			// Delete the transient so the notice only shows once.
			delete_transient( 'onemedia_delete_notice' );
		}
	}
}
