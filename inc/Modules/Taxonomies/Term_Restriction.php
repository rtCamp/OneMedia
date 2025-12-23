<?php
/**
 * Term protection and restriction.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Taxonomies;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Utils;

/**
 * Class CPT_Restriction
 */
class Term_Restriction implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_default_terms' ] );
		add_action( 'init', [ $this,'hide_term_on_brand_site' ], 10 );
		add_action( 'edited_term', [ $this, 'on_term_change' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'on_term_delete' ], 10, 4 );
		add_filter( 'pre_insert_term', [ $this, 'maybe_block_term_insert' ], 10, 2 );
		add_action( 'pre_delete_term', [ $this, 'maybe_block_term_delete' ], 10, 2 );
		add_action( 'edit_terms', [ $this, 'maybe_block_term_edit' ], 10, 2 );
		add_filter( 'tag_row_actions', [ $this, 'remove_term_actions' ], 10, 2 );
	}

	/**
	 * Add default terms to the custom media taxonomy.
	 *
	 * This method ensures that the 'onemedia' term is always present in the taxonomy.
	 * If it does not exist, it will be created with a specific description and slug.
	 */
	public function add_default_terms(): void {
		if ( Utils::term_exists( Media::TAXONOMY_TERM, Media::TAXONOMY ) ) {
			return;
		}

		wp_insert_term(
			Media::TERM_NAME,
			Media::TAXONOMY,
			[
				'description' => __( 'To indicate media type which can not be deleted.', 'onemedia' ),
				'slug'        => Media::TAXONOMY_TERM,
			]
		);
	}

	/**
	 * Make onemedia_media_type taxonomy hidden on brand sites.
	 */
	public function hide_term_on_brand_site(): void {
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		// Get onemedia_media_type.
		$taxonomy = get_taxonomy( Media::TAXONOMY );
		if ( ! $taxonomy || ! $taxonomy->show_ui ) {
			return;
		}

		// Set onemedia_media_type to private.
		$taxonomy->show_ui = false;
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
		$this->show_term_change_message( $term_id, $taxonomy, __( 'modify', 'onemedia' ) );
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
		if ( Media::TAXONOMY !== $taxonomy || Media::TAXONOMY_TERM !== $deleted_term->slug ) {
			return;
		}

		$this->add_default_terms();
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
		if ( Media::TAXONOMY === $term->taxonomy && Media::TAXONOMY_TERM === $term->slug ) {
			$actions_to_unset = [ 'edit', 'inline hide-if-no-js', 'delete' ];
			foreach ( $actions_to_unset as $action ) {
				if ( ! isset( $actions[ $action ] ) ) {
					continue;
				}

				unset( $actions[ $action ] );
			}
		}
		return $actions;
	}

	/**
	 * Block insertion of new terms in the plugin taxonomy.
	 *
	 * This method prevents the addition of new terms to the plugin taxonomy, except for the 'onemedia' term.
	 *
	 * @param string|\WP_Error $term     The term being inserted.
	 * @param string           $taxonomy The taxonomy slug.
	 *
	 * @return string|\WP_Error The term if valid, or a WP_Error if blocked.
	 */
	public function maybe_block_term_insert( $term, $taxonomy ) {
		if ( Media::TAXONOMY !== $taxonomy ) {
			return $term;
		}

		if ( is_string( $term ) && Media::TAXONOMY_TERM === $term ) {
			return $term;
		}

		return new \WP_Error(
			'term_insertion_blocked',
			__( 'Adding new terms to this taxonomy is not allowed.', 'onemedia' )
		);
	}

	/**
	 * Block deletion and editing of the 'onemedia' term in the taxonomy.
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
		$this->show_term_change_message( $term_id, $taxonomy, __( 'delete', 'onemedia' ) );
	}

	/**
	 * Block editing of the 'onemedia' term in the taxonomy.
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
		$this->show_term_change_message( $term_id, $taxonomy, __( 'edit', 'onemedia' ) );
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
	private function show_term_change_message( int $term_id, string $taxonomy, string $action ): void {
		if ( Media::TAXONOMY !== $taxonomy ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term instanceof \WP_Term || Media::TAXONOMY_TERM !== $term->slug ) {
			return;
		}

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
			[ 'response' => 403 ]
		);
	}
}
