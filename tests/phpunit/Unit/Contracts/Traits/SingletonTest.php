<?php
/**
 * Tests for the Singleton trait.
 *
 * @package OneMedia\Tests\Unit\Contracts\Traits
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Contracts\Traits;

use OneMedia\Contracts\Traits\Singleton;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Minimal test double for the Singleton trait.
 */
final class SingletonTestDouble {
	use Singleton;

	/**
	 * Resets the singleton instance.
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}
}

/**
 * Tests for the Singleton trait.
 */
#[CoversClass( Singleton::class )]
final class SingletonTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		SingletonTestDouble::reset_instance();

		parent::tearDown();
	}

	/**
	 * Tests instance returns the same object on repeat calls.
	 */
	public function test_instance_returns_singleton(): void {
		$this->assertSame( SingletonTestDouble::instance(), SingletonTestDouble::instance() );
	}

	/**
	 * Tests cloning triggers a _doing_it_wrong notice.
	 *
	 * @expectedIncorrectUsage __clone
	 */
	public function test_clone_triggers_doing_it_wrong(): void {
		SingletonTestDouble::instance()->__clone();

		$this->assertTrue( true );
	}

	/**
	 * Tests wakeup triggers a _doing_it_wrong notice.
	 *
	 * @expectedIncorrectUsage __wakeup
	 */
	public function test_wakeup_triggers_doing_it_wrong(): void {
		SingletonTestDouble::instance()->__wakeup();

		$this->assertTrue( true );
	}
}
