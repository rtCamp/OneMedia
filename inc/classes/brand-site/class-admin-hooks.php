<?php
/**
 * Class Admin_Hooks initializes the actions and filters for the brand site.
 *
 * @package OneMedia
 */

namespace OneMedia\Brand_Site;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Utils;
use OneMedia\Traits\Singleton;

/**
 * Class Admin_Hooks
 */
class Admin_Hooks {

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

		// Skip if not a brand site.
		if ( Utils::is_governing_site() ) {
			return;
		}

		// Prevent attachment deletion if onemedia_sync_status is set to 'sync'.
		add_filter( 'delete_attachment', array( $this, 'prevent_attachment_deletion' ), 10, 2 );

		// Admin notice for attachment deletion.
		add_action( 'admin_notices', array( $this, 'show_deletion_notice' ) );

		// Prevent attachment edit if onemedia_sync_status is set to 'sync'.
		add_action( 'load-post.php', array( $this, 'prevent_attachment_edit' ) );
		add_action( 'wp_ajax_save-attachment', array( $this, 'prevent_save_attachment_ajax' ), 0 );

		// Remove edit & delete links for synced attachments.
		add_filter( 'media_row_actions', array( $this, 'remove_edit_delete_links' ), 10, 2 );

		// Add column for synced attachments.
		add_filter( 'manage_media_columns', array( $this, 'add_sync_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_sync_column' ), 10, 2 );

		// Add column for source identification.
		add_action( 'manage_media_columns', array( $this, 'add_source_column' ), 10, 2 );
		add_action( 'manage_media_custom_column', array( $this, 'render_source_column' ), 10, 2 );

		// Create media filter for synced attachments.
		add_action( 'restrict_manage_posts', array( $this, 'add_sync_filter' ) );
		add_action( 'parse_query', array( $this, 'filter_sync_attachments' ) );
	}

	/**
	 * Add filter for synced attachments.
	 *
	 * @return void
	 */
	public function add_sync_filter(): void {
		global $pagenow;

		if ( 'upload.php' === $pagenow ) {
			// Nonce verification for filter form.
			$nonce = filter_input( INPUT_GET, 'onemedia_sync_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce = isset( $nonce ) ? sanitize_text_field( wp_unslash( $nonce ) ) : '';

			if ( ! $nonce ) {
				// This means this is the first load of the page, so we don't have onemedia_sync_filter nonce yet.
				echo onemedia_get_template_content( 'brand-site/sync-status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $nonce && wp_verify_nonce( $nonce, 'onemedia_sync_filter' ) ) {
				// This means the form has been submitted, so we have a nonce to verify.
				$sync_status = isset( $_GET[ Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY ] )
					? sanitize_text_field( wp_unslash( $_GET[ Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY ] ) )
					: '';

				// Escaping handled in the template file.
				echo onemedia_get_template_content( 'brand-site/sync-status', array( 'sync_status' => $sync_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Filter attachments based on sync status.
	 *
	 * @param \WP_Query $query Query object.
	 *
	 * @return \WP_Query Modified query object.
	 */
	public function filter_sync_attachments( \WP_Query $query ): \WP_Query {
		global $pagenow;
		$onemedia_sync_status = filter_input( INPUT_GET, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'upload.php' === $pagenow && isset( $onemedia_sync_status ) && ! empty( $onemedia_sync_status ) ) {
			// Nonce verification for filter query.
			$nonce = filter_input( INPUT_GET, 'onemedia_sync_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce = isset( $nonce ) ? sanitize_text_field( wp_unslash( $nonce ) ) : '';

			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'onemedia_sync_filter' ) ) {
				return $query;
			}

			$sync_status = filter_input( INPUT_GET, Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$sync_status = isset( $sync_status ) ? sanitize_text_field( wp_unslash( $sync_status ) ) : '';

			if ( 'sync' === $sync_status ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY,
							'value'   => 'sync',
							'compare' => '=',
						),
					)
				);
			} elseif ( 'no_sync' === $sync_status ) {
				$query->set(
					'meta_query',
					array(
						'relation' => 'OR',
						array(
							'key'     => Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY,
							'value'   => 'no_sync',
							'compare' => '=',
						),
						array(
							'key'     => Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY,
							'compare' => 'NOT EXISTS',
						),
					)
				);
			}
		}

		return $query;
	}

	/**
	 * Prevent attachment deletion.
	 *
	 * @param bool          $check Whether to allow deletion.
	 * @param \WP_Post|null $post  Post object.
	 *
	 * @return bool Whether to allow deletion.
	 */
	public function prevent_attachment_deletion( bool $check, \WP_Post|null $post ): bool {
		// Only check for attachments.
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return $check;
		}

		// Check if attachment is synced.
		$onemedia_sync_status = Utils::get_sync_status_postmeta( $post->ID );
		if ( 'sync' === $onemedia_sync_status ) {
			// Set transient to show admin notice.
			set_transient( 'onemedia_sync_delete_notice', true, 30 );

			// Redirect back to prevent deletion.
			$redirect_url = admin_url( 'upload.php' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		return $check;
	}

	/**
	 * Show admin notice for attachment deletion.
	 *
	 * @return void
	 */
	public function show_deletion_notice(): void {
		// Check for delete notice transient.
		if ( get_transient( 'onemedia_sync_delete_notice' ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'This file is synced from Governing Site, please delete it from there first.', 'onemedia' ); ?></p>
			</div>
			<?php
			// Delete the transient so the notice only shows once.
			delete_transient( 'onemedia_sync_delete_notice' );
		}

		// Check for edit notice transient.
		if ( get_transient( 'onemedia_sync_edit_notice' ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'This file is synced from Governing site, please edit it over there.', 'onemedia' ); ?></p>
			</div>
			<?php
			// Delete the transient so the notice only shows once.
			delete_transient( 'onemedia_sync_edit_notice' );
		}
	}

	/**
	 * Prevent attachment edit.
	 *
	 * @return void
	 */
	public function prevent_attachment_edit(): void {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$post_id = isset( $post_id ) ? intval( $post_id ) : 0;

		// Only check for attachments.
		if ( empty( $post_id ) || 'attachment' !== get_post_type( $post_id ) ) {
			return;
		}

		// Check if we're on the attachment edit screen.
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'attachment' !== $screen->post_type ) {
			return;
		}

		// Check if attachment is synced.
		$onemedia_sync_status = Utils::get_sync_status_postmeta( $post_id );
		if ( 'sync' === $onemedia_sync_status ) {
			// Set transient to show admin notice for edit.
			set_transient( 'onemedia_sync_edit_notice', true, 30 );

			// Redirect back to media library.
			$redirect_url = admin_url( 'upload.php' );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Prevent save_attachment AJAX and block editing for synced attachments.
	 *
	 * @return void
	 */
	public function prevent_save_attachment_ajax(): void {
		$attachment_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

		check_ajax_referer( 'update-post_' . $attachment_id, 'nonce' );

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error();
		}

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || 'save-attachment' !== $action ) {
			return;
		}

		$onemedia_sync_status = Utils::get_sync_status_postmeta( $attachment_id );
		if ( 'sync' === $onemedia_sync_status ) {
			set_transient( 'onemedia_sync_edit_notice', true, 30 );
			wp_send_json_error(
				array(
					'message' => __( 'This file is synced from the Governing Site, please edit it over there.', 'onemedia' ),
				),
				500
			);
			exit;
		}
	}

	/**
	 * Remove edit and delete links for synced attachments.
	 *
	 * @param array|null    $actions Array of action links.
	 * @param \WP_Post|null $post    Post object.
	 *
	 * @return array Modified actions.
	 */
	public function remove_edit_delete_links( array|null $actions, \WP_Post|null $post ): array {
		// Only check for attachments.
		if ( ! $post || ! $actions || 'attachment' !== $post->post_type ) {
			return $actions;
		}

		// Check if attachment is synced.
		$onemedia_sync_status = Utils::get_sync_status_postmeta( $post->ID );
		if ( 'sync' === $onemedia_sync_status ) {
			// Remove edit links.
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}

			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}
		}
		return $actions;
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
		if ( 'onemedia_sync_status' === $column_name ) {
			$onemedia_sync_status = Utils::get_sync_status_postmeta( $post_id );
			if ( 'sync' === $onemedia_sync_status ) {
				echo '<span class="onemedia-sync-badge dashicons dashicons-yes"></span>';
			} else {
				echo '<span class="dashicons dashicons-no"></span>';
			}
		}
	}
	
	/**
	 * Add custom author column in media library.
	 *
	 * @param array $columns Array of columns.
	 *
	 * @return array Modified columns.
	 */
	public function add_source_column( array $columns ): array {
		$columns['onemedia_source'] = __( 'Source', 'onemedia' );
		return $columns;
	}

	/**
	 * Render custom author column in media library.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 *
	 * @return void
	 */
	public function render_source_column( string $column_name, int $post_id ): void {
		if ( 'onemedia_source' === $column_name ) {
			$terms                = Utils::get_onemedia_attachment_post_terms( $post_id, array( 'fields' => 'names' ) );
			$onemedia_sync_status = Utils::get_sync_status_postmeta( $post_id );

			// Add governing_site_url link to the output.
			if ( ! empty( $terms ) && isset( array_flip( $terms )[ ONEMEDIA_PLUGIN_TAXONOMY_TERM ] ) && ! empty( $onemedia_sync_status ) && $onemedia_sync_status ) {
				$saved_governing_site_url = Utils::get_governing_site_url();
				if ( $saved_governing_site_url ) {
					printf(
						/* translators: %1$s is the site URL, %2$s is the link text. */
						'<a href="%1$s">%2$s</a>',
						esc_url( $saved_governing_site_url ),
						esc_html__( 'Governing Site', 'onemedia' )
					);

				} else {
					esc_html_e( 'Governing Site', 'onemedia' );
				}
			} else {
				printf(
					/* translators: %s is the screen reader text. */
					'<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
					esc_html__( '(no author)', 'onemedia' )
				);
			}
		}
	}
}
