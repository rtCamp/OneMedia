<?php
/**
 * Media Taxonomy class for OneMedia plugin.
 *
 * @package OneMedia
 */

namespace OneMedia\Admin;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Utils;
use OneMedia\Traits\Singleton;

/**
 * Class Media_Taxonomy
 */
class Media_Taxonomy {

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
		add_action( 'init', array( $this, 'register_media_taxonomy' ) );
		add_action( 'init', array( $this, 'add_media_taxonomy_to_post_type' ) );
		add_action( 'init', array( $this, 'add_default_terms' ) );

		// Term protection and restriction.
		add_action( 'edited_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_delete' ), 10, 4 );
		add_filter( 'pre_insert_term', array( $this, 'maybe_block_term_insert' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'maybe_block_term_delete' ), 10, 2 );
		add_action( 'edit_terms', array( $this, 'maybe_block_term_edit' ), 10, 2 );

		// Admin UI.
		add_filter( 'tag_row_actions', array( $this, 'remove_term_actions' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'display_media_column_content' ), 10, 2 );

		// Media deletion protection.
		add_filter( 'delete_attachment', array( $this, 'maybe_block_media_delete' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'show_deletion_notice' ) );
		add_filter( 'media_row_actions', array( $this, 'filter_media_row_actions' ), 10, 2 );

		// Add onemedia term to media attachment when added via add onemedia_sync button.
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
		$is_onemedia_attachment = metadata_exists( 'post', $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY );
		if ( $attachment_id && taxonomy_exists( ONEMEDIA_PLUGIN_TAXONOMY ) && $is_onemedia_attachment ) {
			// Assign the 'onemedia' term to the attachment.
			$success = wp_set_object_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_PLUGIN_TAXONOMY, true );

			if ( is_wp_error( $success ) ) {
				wp_send_json_error( array( 'message' => __( 'Failed to assign taxonomy term to attachment.', 'onemedia' ) ), 500 );
			}
		}
	}

	/**
	 * Add a custom column to the media library for displaying image types.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 */
	public function add_media_column( array $columns ): array {
		$columns['image_type'] = __( 'Image Type', 'onemedia' );
		return $columns;
	}

	/**
	 * Display content for the custom media column.
	 *
	 * @param string $column_name   The name of the column.
	 * @param int    $attachment_id The ID of the attachment.
	 *
	 * @return void
	 */
	public function display_media_column_content( string $column_name, int $attachment_id ): void {
		if ( 'image_type' === $column_name ) {
			$terms    = Utils::get_onemedia_attachment_post_terms( $attachment_id, array( 'fields' => 'names' ) );
			$is_empty = empty( $terms ) || is_wp_error( $terms );

			if ( $is_empty ) {
				$label = __( 'Not assigned', 'onemedia' );
			} else {
				$labels = array();
				foreach ( $terms as $term ) {
					if ( ONEMEDIA_PLUGIN_TAXONOMY_TERM === $term ) {
						$labels[] = ONEMEDIA_PLUGIN_TERM_NAME;
					} else {
						$labels[] = esc_html( $term );
					}
				}
				$label = implode( ', ', $labels );
			}

			$classes = 'onemedia-media-term-label' . ( $is_empty ? ' empty' : '' );

			printf(
				/* translators: %1$s is the class attribute, %2$s is the label text. */
				'<span class="%1$s">%2$s</span>',
				esc_attr( $classes ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Remove edit, inline, and delete actions for the 'onemedia' term in the media taxonomy.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Term $term    The term object.
	 *
	 * @return array Modified actions.
	 */
	public function remove_term_actions( array $actions, \WP_Term $term ): array {
		if ( ONEMEDIA_PLUGIN_TAXONOMY === $term->taxonomy && ONEMEDIA_PLUGIN_TAXONOMY_TERM === $term->slug ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}
			if ( isset( $actions['inline hide-if-no-js'] ) ) {
				unset( $actions['inline hide-if-no-js'] );
			}
			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}
		}
		return $actions;
	}

	/**
	 * Register the custom media taxonomy.
	 *
	 * @return void
	 */
	public function register_media_taxonomy(): void {
		register_taxonomy(
			ONEMEDIA_PLUGIN_TAXONOMY,
			'attachment',
			array(
				'labels'       => array(
					'name'          => __( 'Image Type', 'onemedia' ),
					'singular_name' => __( 'Image Type', 'onemedia' ),
					'all_items'     => __( 'All Image Types', 'onemedia' ),
					'edit_item'     => __( 'Edit Image Type', 'onemedia' ),
					'view_item'     => __( 'View Image Type', 'onemedia' ),
					'update_item'   => __( 'Update Image Type', 'onemedia' ),
					'add_new_item'  => __( 'Add New Image Type', 'onemedia' ),
					'new_item_name' => __( 'New Image Type Name', 'onemedia' ),
					'search_items'  => __( 'Search Image Types', 'onemedia' ),
					'not_found'     => __( 'No image types found', 'onemedia' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'meta_box_cb'  => false,
				'rewrite'      => array(
					'slug'       => ONEMEDIA_PLUGIN_TAXONOMY,
					'with_front' => false,
				),
				'capabilities' => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Add the custom media taxonomy to the attachment post type.
	 *
	 * @return void
	 */
	public function add_media_taxonomy_to_post_type(): void {
		register_taxonomy_for_object_type( ONEMEDIA_PLUGIN_TAXONOMY, 'attachment' );
	}

	/**
	 * Add default terms to the custom media taxonomy.
	 *
	 * This method ensures that the 'onemedia' term is always present in the taxonomy.
	 * If it does not exist, it will be created with a specific description and slug.
	 *
	 * @return void
	 */
	public function add_default_terms(): void {
		$term_exists_fn = function_exists( 'wpcom_vip_term_exists' ) ? 'wpcom_vip_term_exists' : 'term_exists';

		if ( ! $term_exists_fn( ONEMEDIA_PLUGIN_TAXONOMY_TERM, ONEMEDIA_PLUGIN_TAXONOMY ) ) {
			wp_insert_term(
				ONEMEDIA_PLUGIN_TERM_NAME,
				ONEMEDIA_PLUGIN_TAXONOMY,
				array(
					'description' => __( 'To indicate media type which can not be deleted.', 'onemedia' ),
					'slug'        => ONEMEDIA_PLUGIN_TAXONOMY_TERM,
				)
			);
		}
	}

	/**
	 * Block changes to the 'onemedia' term in the custom media taxonomy.
	 *
	 * This method prevents modifications to the 'onemedia' term, including edits and deletions.
	 *
	 * @param int    $term_id  The ID of the term being modified.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return void
	 */
	public function on_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->show_onemedia_term_change_message( $term_id, $taxonomy, __( 'modify', 'onemedia' ) );
	}

	/**
	 * Handle term deletion for the 'onemedia' term in the custom media taxonomy.
	 *
	 * This method ensures that the 'onemedia' term cannot be deleted and re-adds it if it is removed.
	 *
	 * @param \WP_Term $term         The term being deleted.
	 * @param int      $tt_id        The term taxonomy ID.
	 * @param string   $taxonomy     The taxonomy slug.
	 * @param \WP_Term $deleted_term The term object that was deleted.
	 *
	 * @return void 
	 */
	public function on_term_delete( \WP_Term $term, int $tt_id, string $taxonomy, \WP_Term $deleted_term ): void {
		if ( ONEMEDIA_PLUGIN_TAXONOMY === $taxonomy && ONEMEDIA_PLUGIN_TAXONOMY_TERM === $deleted_term->slug ) {
			$this->add_default_terms();
		}
	}

	/**
	 * Block insertion of new terms in the ONEMEDIA_PLUGIN_TAXONOMY taxonomy.
	 *
	 * This method prevents the addition of new terms to the ONEMEDIA_PLUGIN_TAXONOMY taxonomy,
	 * except for the 'onemedia' term.
	 *
	 * @param string|array|WP_Error $term     The term being inserted.
	 * @param string                $taxonomy The taxonomy slug.
	 *
	 * @return string|array|\WP_Error The term if valid, or a WP_Error if blocked.
	 */
	public function maybe_block_term_insert( string|array|\WP_Error $term, string $taxonomy ): string|array|\WP_Error {
		if ( ONEMEDIA_PLUGIN_TAXONOMY !== $taxonomy ) {
			return $term;
		}

		if ( is_string( $term ) && ONEMEDIA_PLUGIN_TAXONOMY_TERM === $term ) {
			return $term;
		}

		if ( is_array( $term ) && ONEMEDIA_PLUGIN_TAXONOMY_TERM === ( $term['slug'] ?? '' ) ) {
			return $term;
		}

		return new \WP_Error(
			'term_insertion_blocked',
			__( 'Adding new terms to this taxonomy is not allowed.', 'onemedia' )
		);
	}

	/**
	 * Block deletion and editing of the 'onemedia' term in the ONEMEDIA_PLUGIN_TAXONOMY.
	 *
	 * This method prevents the deletion and editing of the 'onemedia' term,
	 * ensuring it remains intact for media management purposes.
	 *
	 * @param int    $term_id  The ID of the term being deleted or edited.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return void
	 */
	public function maybe_block_term_delete( int $term_id, string $taxonomy ): void {
		$this->show_onemedia_term_change_message( $term_id, $taxonomy, __( 'delete', 'onemedia' ) );
	}

	/**
	 * Block editing of the 'onemedia' term in the ONEMEDIA_PLUGIN_TAXONOMY.
	 *
	 * This method prevents the editing of the 'onemedia' term,
	 * ensuring it remains unchanged for media management purposes.
	 *
	 * @param int    $term_id  The ID of the term being edited.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return void
	 */
	public function maybe_block_term_edit( int $term_id, string $taxonomy ): void {
		$this->show_onemedia_term_change_message( $term_id, $taxonomy, __( 'edit', 'onemedia' ) );
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
		$terms = Utils::get_onemedia_attachment_post_terms( $attachment_id, array( 'fields' => 'slugs' ) );
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
	 * Filter media row actions to remove the delete action for attachments with the 'onemedia' term.
	 *
	 * @param array    $actions Array of action links.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return array Modified actions.
	 */
	public function filter_media_row_actions( array $actions, \WP_Post $post ): array {
		if ( 'attachment' === $post->post_type ) {
			$terms = Utils::get_onemedia_attachment_post_terms( $post->ID, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) && isset( array_flip( $terms )[ ONEMEDIA_PLUGIN_TAXONOMY_TERM ] ) ) {
				if ( isset( $actions['delete'] ) ) {
					unset( $actions['delete'] );
				}
			}
		}
		return $actions;
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

	/**
	 * Show wp die message on editing, modifying or deleting the 'onemedia' term in the
	 * onemedia_media_type taxonomy.
	 *
	 * @param int    $term_id  The ID of the term being edited.
	 * @param string $taxonomy The taxonomy slug.
	 * @param string $action   The action being performed: 'edit', 'delete', or 'modify'.
	 *
	 * @return void
	 */
	public function show_onemedia_term_change_message( int $term_id, string $taxonomy, string $action ): void {
		if ( ONEMEDIA_PLUGIN_TAXONOMY === $taxonomy ) {
			$term = get_term( $term_id, $taxonomy );

			if ( ! is_wp_error( $term ) && $term && ! empty( $term ) && ONEMEDIA_PLUGIN_TAXONOMY_TERM === $term->slug ) {
				switch ( $action ) {
					case 'edit':
						$die_title = __( 'Editing', 'onemedia' );
						break;
					case 'delete':
						$die_title = __( 'Deletion', 'onemedia' );
						break;
					case 'modify':
						$die_title = __( 'Modification', 'onemedia' );
						break;
					default:
						$die_title = __( 'Action', 'onemedia' );
				}

				wp_die(
					sprintf(
						/* translators: %s: action being performed (edit, delete, or modify) */
						esc_html__( 'You cannot %s the default "onemedia" term.', 'onemedia' ),
						esc_html( $action )
					),
					sprintf(
						/* translators: %s: action being performed (edit, delete, or modify) */
						esc_html__( 'Term %s Blocked', 'onemedia' ),
						esc_html( $die_title )
					),
					array( 'response' => 403 )
				);
			}
		}
	}
}
