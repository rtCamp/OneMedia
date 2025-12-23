<?php
/**
 * Term protection and restriction.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Modules\Taxonomies\Media;

/**
 * Class CPT_Restriction
 */
class MediaProtection implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'delete_attachment', [ $this, 'maybe_block_media_delete' ], 10, 1 );
		add_action( 'admin_notices', [ $this, 'show_deletion_notice' ] );
		add_action( 'add_attachment', [ $this, 'add_term_to_attachment' ] );

		if ( ! Settings::is_consumer_site() ) {
			return;
		}
		add_filter( 'map_meta_cap', [ $this, 'prevent_sync_media_editing' ], 10, 4 );
	}

	/**
	 * Add onemedia term to the attachment when added via the "Add Sync Media" button.
	 *
	 * @param int $attachment_id The ID of the attachment being added.
	 *
	 * @return void
	 */
	public function add_term_to_attachment( int $attachment_id ): void {
		$is_onemedia_attachment = metadata_exists( 'post', $attachment_id, Attachment::IS_SYNC_POSTMETA_KEY );
		if ( ! $attachment_id || ! taxonomy_exists( Media::TAXONOMY ) || ! $is_onemedia_attachment ) {
			return;
		}

		// Assign the 'onemedia' term to the attachment.
		$success = wp_set_object_terms( $attachment_id, Media::TAXONOMY_TERM, Media::TAXONOMY, true );

		if ( ! is_wp_error( $success ) ) {
			return;
		}

		wp_send_json_error( [ 'message' => __( 'Failed to assign taxonomy term to attachment.', 'onemedia' ) ], 500 );
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
		$terms = Attachment::get_post_terms( $attachment_id, [ 'fields' => 'slugs' ] );
		if ( ! empty( $terms ) && isset( array_flip( $terms )[ Media::TAXONOMY_TERM ] ) ) {
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
		if ( ! $onemedia_delete_transient ) {
			return;
		}

		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'You cannot delete media that is assigned to the "onemedia" term.', 'onemedia' ); ?></p>
		</div>
		<?php
		// Delete the transient so the notice only shows once.
		delete_transient( 'onemedia_delete_notice' );
	}

	/**
	 * Prevent editing or deleting of synced media attachments on brand sites.
	 *
	 * @param array  $caps Current user's capabilities.
	 * @param string $cap Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args Arguments for the capability check.
	 *
	 * @return array|string[]
	 */
	public function prevent_sync_media_editing( array $caps, string $cap, int $user_id, array $args ): array {

		if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) ) {
			return $caps;
		}

		$post_id = $args[0] ?? 0;

		if ( ! $post_id || 'attachment' !== get_post_type( $post_id ) ) {
			return $caps;
		}

		if ( Attachment::SYNC_STATUS_SYNC === get_post_meta( $post_id, Attachment::SYNC_STATUS_POSTMETA_KEY, true ) ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}
}
