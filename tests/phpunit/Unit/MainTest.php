<?php
/**
 * Tests for the Main bootstrap class.
 *
 * @package OneMedia\Tests\Unit
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit;

use OneMedia\Main;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Main bootstrap class.
 */
#[CoversClass( Main::class )]
final class MainTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_main_singleton();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		$this->reset_main_singleton();

		parent::tearDown();
	}

	/**
	 * Tests instance returns the same object on repeat calls.
	 */
	public function test_instance_returns_singleton(): void {
		$this->assertSame( Main::instance(), Main::instance() );
	}

	/**
	 * Tests all registrable classes are instantiated and hooks are registered during setup.
	 */
	public function test_setup_registers_hooks(): void {
		Main::instance();

		$this->assertNotFalse( has_action( 'admin_menu' ) );
		$this->assertNotFalse( has_action( 'rest_api_init' ) );
	}

	/**
	 * Reset the Main singleton between tests.
	 */
	private function reset_main_singleton(): void {
		$property = new \ReflectionProperty( Main::class, 'instance' );
		$property->setValue( null, null );
	}
}
