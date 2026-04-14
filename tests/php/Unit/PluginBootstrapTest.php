<?php
/**
 * Smoke tests for plugin bootstrap.
 *
 * @package OneMedia\Tests
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit;

use OneMedia\Tests\TestCase;

/**
 * Class PluginBootstrapTest
 */
final class PluginBootstrapTest extends TestCase {
	/**
	 * Ensure the plugin bootstrap defined expected constants.
	 */
	public function test_plugin_constants_are_defined(): void {
		self::assertTrue( defined( 'ONEMEDIA_VERSION' ) );
		self::assertTrue( defined( 'ONEMEDIA_DIR' ) );
		self::assertTrue( defined( 'ONEMEDIA_URL' ) );
		self::assertTrue( defined( 'ONEMEDIA_PLUGIN_BASENAME' ) );
	}

	/**
	 * Ensure the main plugin class is available after bootstrap.
	 */
	public function test_main_class_is_available(): void {
		self::assertTrue( class_exists( '\\OneMedia\\Main' ) );
	}
}