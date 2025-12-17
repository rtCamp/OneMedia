<?php
/**
 * Abstract class to register taxonomy.
 *
 * @package OneMedia
 */

namespace OneMedia\Modules\Taxonomies;

use OneMedia\Contracts\Interfaces\Registrable;

/**
 * Base class to register post types.
 */
abstract class Abstract_Taxonomy implements Registrable {

	/**
	 * Get slug of post type.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
	}

	/**
	 * To register post type.
	 */
	abstract public function register_taxonomy(): void;

	/**
	 * To get argument to register custom post type.
	 *
	 * To override arguments, define this method in a child class and override args.
	 *
	 * @return array{
	 *   show_in_rest: bool,
	 *   public: bool,
	 *   has_archive: bool,
	 *   menu_position: int,
	 *   supports: list<string>,
	 * }
	 */
	public function default_args(): array {
		return [
			'show_in_rest'  => true,
			'public'        => true,
		];
	}
}
