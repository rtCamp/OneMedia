<?php
/**
 * Register template post type.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Taxonomies;

/**
 * Class Template
 */
class Media extends Abstract_Taxonomy {

	public const TAXONOMY      = 'onemedia_media_type';
	public const TAXONOMY_TERM = 'onemedia';
	public const TERM_NAME     = 'OneMedia';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		parent::register_hooks();

		add_action( 'init', [ $this, 'add_media_taxonomy_to_post_type' ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return self::TAXONOMY;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_taxonomy(): void {
		// phpcs:ignore WordPress.NamingConventions.ValidTaxonomyName.NotStringLiteral -- Slug is defined in get_slug method.
		register_taxonomy(
			self::get_slug(),
			'attachment',
			[
				'labels'       => [
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
				],
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => true,
				'show_in_menu' => false,
				'show_in_rest' => true,
				'meta_box_cb'  => false,
				'rewrite'      => [
					'slug'       => self::get_slug(),
					'with_front' => false,
				],
				'capabilities' => [
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				],
			]
		);
	}

	/**
	 * Add the custom media taxonomy to the attachment post type.
	 */
	public function add_media_taxonomy_to_post_type(): void {
		register_taxonomy_for_object_type( self::TAXONOMY, 'attachment' );
	}
}
