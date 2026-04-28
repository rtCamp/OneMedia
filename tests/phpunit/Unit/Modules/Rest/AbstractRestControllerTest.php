<?php
/**
 * Tests for shared REST controller behavior.
 *
 * @package OneMedia\Tests\Unit\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Rest;

use OneMedia\Modules\Rest\Abstract_REST_Controller;
use OneMedia\Modules\Rest\Basic_Options_Controller;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * @covers \OneMedia\Modules\Rest\Abstract_REST_Controller
 */
#[CoversClass( Abstract_REST_Controller::class )]
final class AbstractRestControllerTest extends TestCase {
	/**
	 * Clean options and current user.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_CONSUMER_API_KEY );
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Tests register hook behavior inherited by controllers.
	 */
	public function test_register_hooks_adds_rest_api_init_callback(): void {
		$controller = new Basic_Options_Controller();

		$controller->register_hooks();

		$this->assertSame( 10, has_action( 'rest_api_init', [ $controller, 'register_routes' ] ) );
	}

	/**
	 * Tests same-origin requests require manage_options.
	 */
	public function test_check_api_permissions_for_same_origin_uses_current_user_capability(): void {
		$controller = new Basic_Options_Controller();
		$request    = new WP_REST_Request( 'GET', '/onemedia/v1/health-check' );
		$request->set_header( 'Origin', get_site_url() );

		$this->assertFalse( $controller->check_api_permissions( $request ) );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->assertTrue( $controller->check_api_permissions( $request ) );
	}

	/**
	 * Tests governing site remote token validation.
	 */
	public function test_check_api_permissions_validates_governing_site_shared_site_token(): void {
		$controller = new Basic_Options_Controller();
		$request    = new WP_REST_Request( 'GET', '/onemedia/v1/health-check' );
		$request->set_header( 'Origin', 'https://brand.test' );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand',
					'url'     => 'https://brand.test/',
					'api_key' => 'token',
				],
			]
		);

		$this->assertFalse( $controller->check_api_permissions( $request ) );

		$request->set_header( 'X-OneMedia-Token', 'wrong-token' );
		$this->assertFalse( $controller->check_api_permissions( $request ) );

		$request->set_header( 'X-OneMedia-Token', 'token' );
		$this->assertTrue( $controller->check_api_permissions( $request ) );
	}

	/**
	 * Tests consumer site health-check stores the governing site URL.
	 */
	public function test_check_api_permissions_for_consumer_health_check_stores_parent_site(): void {
		$controller = new Basic_Options_Controller();
		$request    = new WP_REST_Request( 'GET', '/onemedia/v1/health-check' );
		$request->set_header( 'Origin', 'https://governing.test' );
		$request->set_header( 'X-OneMedia-Token', 'token' );
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
		update_option( Settings::OPTION_CONSUMER_API_KEY, 'token', false );

		$this->assertTrue( $controller->check_api_permissions( $request ) );
		$this->assertSame( 'https://governing.test/', Settings::get_parent_site_url() );
	}

	/**
	 * Tests consumer non-health requests must come from stored governing site.
	 */
	public function test_check_api_permissions_for_consumer_non_health_request_validates_stored_parent_site(): void {
		$controller = new Basic_Options_Controller();
		$request    = new WP_REST_Request( 'POST', '/onemedia/v1/add-media' );
		$request->set_header( 'Origin', 'https://other.test' );
		$request->set_header( 'X-OneMedia-Token', 'token' );
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
		update_option( Settings::OPTION_CONSUMER_API_KEY, 'token', false );
		Settings::set_parent_site_url( 'https://governing.test' );

		$this->assertFalse( $controller->check_api_permissions( $request ) );

		$request->set_header( 'Origin', 'https://governing.test' );

		$this->assertTrue( $controller->check_api_permissions( $request ) );
	}
}
