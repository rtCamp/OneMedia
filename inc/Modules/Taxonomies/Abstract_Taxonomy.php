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
}
