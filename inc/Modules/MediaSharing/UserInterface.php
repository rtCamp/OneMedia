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
class UserInterface implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'display_media_column_content' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'filter_media_row_actions' ), 10, 2 );
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
			$terms    = self::get_onemedia_attachment_post_terms( $attachment_id, array( 'fields' => 'names' ) );
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
	 * Filter media row actions to remove the delete action for attachments with the 'onemedia' term.
	 *
	 * @param array    $actions Array of action links.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return array Modified actions.
	 */
	public function filter_media_row_actions( array $actions, \WP_Post $post ): array {
		if ( 'attachment' === $post->post_type ) {
			$terms = self::get_onemedia_attachment_post_terms( $post->ID, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) && isset( array_flip( $terms )[ ONEMEDIA_PLUGIN_TAXONOMY_TERM ] ) ) {
				if ( isset( $actions['delete'] ) ) {
					unset( $actions['delete'] );
				}
			}
		}
		return $actions;
	}

	/**
	 * Get OneMedia attachment terms with args.
	 *
	 * @param int|\WP_Post $attachment_id The attachment ID.
	 * @param array        $args          Arguments to pass to wp_get_post_terms function.
	 *
	 * @return array Array of terms.
	 */
	public static function get_onemedia_attachment_post_terms( int|\WP_Post $attachment_id, array $args = array() ): array {
		if ( ! $attachment_id ) {
			return array();
		}

		$terms = wp_get_post_terms( $attachment_id, ONEMEDIA_PLUGIN_TAXONOMY, $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return $terms;
	}
}
