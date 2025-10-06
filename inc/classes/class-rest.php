<?php
/**
 * Register All OneMedia related REST API endpoints.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\REST\Basic_Options;
use OneMedia\REST\Media_Sharing;
use OneMedia\Traits\Singleton;

/**
 * Class REST
 */
class REST {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * REST constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		Basic_Options::get_instance();
		Media_Sharing::get_instance();

		// Fix cors headers for REST API requests.
		add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ), PHP_INT_MAX - 20, 4 );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool $served Whether the request has been served.
	 *
	 * @return bool Whether the request has been served.
	 */
	public function add_cors_headers( bool $served ): bool {
		header( 'Access-Control-Allow-Headers: X-OneMedia-Token, Content-Type, Authorization', false );
		return $served;
	}
}
