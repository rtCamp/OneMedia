<?php
/**
 * Term protection and restriction.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;

/**
 * Class CPT_Restriction
 */
class UserInterface implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {

		// Add column for synced attachments.
		add_filter( 'manage_media_columns', [ $this, 'add_sync_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_sync_column' ], 10, 2 );

		// Remove delete action from sync media.
		add_filter( 'media_row_actions', [ $this, 'filter_media_row_actions' ], 10, 2 );
	}

	/**
	 * Add sync column to media library.
	 *
	 * @param array $columns Array of columns.
	 *
	 * @return array Modified columns.
	 */
	public function add_sync_column( array $columns ): array {
		$columns['onemedia_sync_status'] = __( 'Sync Status', 'onemedia' );
		return $columns;
	}

	/**
	 * Render sync column in media library.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 *
	 * @return void
	 */
	public function render_sync_column( string $column_name, int $post_id ): void {
		if ( 'onemedia_sync_status' !== $column_name ) {
			return;
		}

		$is_sync = Attachment::is_sync_attachment( $post_id );
		$title   = $is_sync ? __( 'Synced', 'onemedia' ) : __( 'Not synced', 'onemedia' );
		$class   = $is_sync ? 'onemedia-sync-badge dashicons dashicons-yes' : 'dashicons dashicons-no';

		printf(
			'<span class="%s" title="%s"></span>',
			esc_attr( $class ),
			esc_attr( $title )
		);
	}

	/**
	 * Filter media row actions to remove the delete action for attachments with the 'onemedia' term.
	 *
	 * @param array    $actions Array of action links.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return array Modified actions.
	 */
	public function filter_media_row_actions( array $actions, \WP_Post $post ): array {
		if ( 'attachment' !== $post->post_type ) {
			return $actions;
		}

		$is_sync = Attachment::is_sync_attachment( $post->ID );
		if ( ! $is_sync ) {
			return $actions;
		}

		// Remove delete action for sync media.
		unset( $actions['delete'] );

		return $actions;
	}
}
