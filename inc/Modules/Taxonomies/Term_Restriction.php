<?php
/**
 * Term protection and restriction.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Taxonomies;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Constants;
use OneMedia\Utils;

/**
 * Class CPT_Restriction
 */
class Term_Restriction implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_default_terms' ) );
		add_action( 'edited_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_delete' ), 10, 4 );
		add_filter( 'pre_insert_term', array( $this, 'maybe_block_term_insert' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'maybe_block_term_delete' ), 10, 2 );
		add_action( 'edit_terms', array( $this, 'maybe_block_term_edit' ), 10, 2 );
		add_filter( 'tag_row_actions', array( $this, 'remove_term_actions' ), 10, 2 );
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
	public function  on_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
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
