<?php
/**
 * Tests for the Settings\Settings class.
 *
 * @package OneMedia\Tests\Unit\Modules\Settings
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Settings;

use OneMedia\Modules\Settings\Settings;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Settings\Settings class.
 */
#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	/**
	 * Tests register_hooks adds admin_init, rest_api_init, and update_option actions.
	 */
	public function test_register_hooks_adds_actions(): void {
		$settings = new Settings();
		$settings->register_hooks();

		$this->assertNotFalse( has_action( 'admin_init', [ $settings, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $settings, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'update_option_' . Settings::OPTION_SITE_TYPE, [ $settings, 'on_site_type_change' ] ) );
	}

	/**
	 * Tests sanitize_shared_sites returns empty array for invalid input.
	 */
	public function test_sanitize_shared_sites_returns_empty_for_invalid_input(): void {
		$this->assertSame( [], Settings::sanitize_shared_sites( null ) );
		$this->assertSame( [], Settings::sanitize_shared_sites( [] ) );
		$this->assertSame( [], Settings::sanitize_shared_sites( 'string' ) );
	}

	/**
	 * Tests sanitize_shared_sites filters out entries missing required fields.
	 */
	public function test_sanitize_shared_sites_filters_incomplete_entries(): void {
		$input = [
			[ 'name' => 'Site A' ],            // Missing url.
			[ 'url' => 'https://site-b.com/' ], // Missing name.
			[],                                 // Empty.
		];

		$this->assertSame( [], Settings::sanitize_shared_sites( $input ) );
	}

	/**
	 * Tests sanitize_shared_sites returns sanitized data for a valid entry.
	 */
	public function test_sanitize_shared_sites_sanitizes_valid_entry(): void {
		$input = [
			[
				'name'    => 'Brand Site',
				'url'     => 'https://example.com',
				'api_key' => 'test-api-key',
			],
		];

		$result = Settings::sanitize_shared_sites( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Brand Site', $result[0]['name'] );
		$this->assertSame( 'https://example.com/', $result[0]['url'] );
		$this->assertSame( 'test-api-key', $result[0]['api_key'] );
	}
}
