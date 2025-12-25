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
		if ( ! wp_doing_ajax() ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'media-form' ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['action'] ) || 'upload-attachment' !== sanitize_text_field( $_REQUEST['action'] ) ) {
			return;
		}

		// Check if is_onemedia_sync is set and true.
		$is_onemedia_sync = isset( $_POST['is_onemedia_sync'] ) && filter_var( wp_unslash( $_POST['is_onemedia_sync'] ), FILTER_VALIDATE_BOOLEAN );

		update_post_meta( $attachment_id, Attachment::IS_SYNC_POSTMETA_KEY, $is_onemedia_sync ? 1 : 0 );

		if ( true !== $is_onemedia_sync ) {
			return;
		}

		wp_set_object_terms( $attachment_id, Media::TAXONOMY_TERM, Media::TAXONOMY, true );
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
	 * @param array  $caps    Current user's capabilities.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Arguments for the capability check.
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
