<?php
/**
 * Tests for main plugin bootstrap class.
 *
 * @package OneMedia\Tests\Unit
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit;

use OneMedia\Main;
use OneMedia\Modules\Core\Assets;
use OneMedia\Modules\Core\Rest;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @covers \OneMedia\Main
 */
#[CoversClass( Main::class )]
final class MainTest extends TestCase {
	/**
	 * Reset singleton instance before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$property = new \ReflectionProperty( Main::class, 'instance' );
		$property->setValue( null, null );
	}

	/**
	 * Check if a hook contains a callback by class and method.
	 *
	 * @param string $hook_name Hook name.
	 * @param string $class_name Callback class name.
	 * @param string $method_name Callback method name.
	 */
	private function hook_has_class_callback( string $hook_name, string $class_name, string $method_name ): bool {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) ) {
			return false;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && is_object( $function[0] ) && $function[0] instanceof $class_name && $method_name === $function[1] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Tests instance bootstraps registrable classes.
	 */
	public function test_instance_loads_registrable_classes(): void {
		$instance = Main::instance();

		$this->assertInstanceOf( Main::class, $instance );
		$this->assertInstanceOf( Main::class, Main::instance() );
		$this->assertTrue( $this->hook_has_class_callback( 'rest_allowed_cors_headers', Rest::class, 'allowed_cors_headers' ) );
		$this->assertTrue( $this->hook_has_class_callback( 'admin_enqueue_scripts', Assets::class, 'register_assets' ) );
	}
}
