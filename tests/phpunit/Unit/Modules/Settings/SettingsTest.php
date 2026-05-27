<?php
/**
 * Tests for the Settings\Settings class.
 *
 * @package OneMedia\Tests\Unit\Modules\Settings
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Settings;

use OneMedia\Encryptor;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Settings\Settings class.
 */
#[CoversClass( Settings::class )]
final class SettingsTest extends TestCase {
	/**
	 * @var \OneMedia\Modules\Settings\Settings
	 */
	private Settings $settings;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->settings = new Settings();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_CONSUMER_API_KEY );
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		parent::tearDown();
	}

	/**
	 * Tests no errors on class instantiation.
	 */
	public function test_register_hooks_adds_actions(): void {
		$this->settings->register_hooks();
		$this->settings->register_settings();

		$this->assertTrue( true );
	}

	/**
	 * Tests register_settings registers the site type setting.
	 */
	public function test_register_settings_registers_site_type(): void {
		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_SITE_TYPE );
	}

	/**
	 * Tests register_settings registers consumer options.
	 */
	public function test_register_settings_registers_consumer_options(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_CONSUMER_API_KEY );
		$this->assertSettingRegistered( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
	}

	/**
	 * Tests register_settings registers governing options.
	 */
	public function test_register_settings_registers_governing_options(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->settings->register_settings();

		$this->assertSettingRegistered( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/**
	 * Tests get_site_type returns null when not set.
	 */
	public function test_get_site_type_returns_null_when_not_set(): void {
		delete_option( Settings::OPTION_SITE_TYPE );

		$this->assertNull( Settings::get_site_type() );
	}

	/**
	 * Tests get_site_type returns the stored value.
	 */
	public function test_get_site_type_returns_value_when_set(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->assertSame( Settings::SITE_TYPE_CONSUMER, Settings::get_site_type() );
	}

	/**
	 * Tests governing site detection.
	 */
	public function test_is_governing_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->assertTrue( Settings::is_governing_site() );
		$this->assertFalse( Settings::is_consumer_site() );
	}

	/**
	 * Tests consumer site detection.
	 */
	public function test_is_consumer_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		$this->assertTrue( Settings::is_consumer_site() );
		$this->assertFalse( Settings::is_governing_site() );
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
			[ 'name' => 'Site A' ],             // Missing url.
			[ 'url' => 'https://site-b.com/' ], // Missing name.
			[],                                  // Empty.
		];

		$this->assertSame( [], Settings::sanitize_shared_sites( $input ) );
	}

	/**
	 * Tests sanitize_shared_sites returns sanitized data for a valid entry.
	 */
	public function test_sanitize_shared_sites_sanitizes_valid_entry(): void {
		$result = Settings::sanitize_shared_sites(
			[
				[
					'name'    => 'Brand Site',
					'url'     => 'https://example.com',
					'api_key' => 'test-api-key',
				],
			]
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'Brand Site', $result[0]['name'] );
		$this->assertSame( 'https://example.com/', $result[0]['url'] );
		$this->assertSame( 'test-api-key', $result[0]['api_key'] );
	}

	/**
	 * Tests sanitize_shared_sites generates a UUID when id is missing.
	 */
	public function test_sanitize_shared_sites_generates_uuid_for_missing_id(): void {
		$result = Settings::sanitize_shared_sites(
			[
				[
					'name' => 'Brand Site',
					'url'  => 'https://example.com',
				],
			]
		);

		$this->assertCount( 1, $result );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/i', $result[0]['id'] );
	}

	/**
	 * Tests get_shared_sites returns empty array when not set.
	 */
	public function test_get_shared_sites_returns_empty_when_not_set(): void {
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Tests shared sites round-trip through storage with encrypted api_key.
	 */
	public function test_set_and_get_shared_sites_roundtrip(): void {
		$sites = [
			[
				'id'      => 'brand-1',
				'name'    => 'Brand One',
				'url'     => 'https://brand-one.example',
				'api_key' => 'brand-one-key',
			],
		];

		$this->assertTrue( Settings::set_shared_sites( $sites ) );

		$stored = get_option( Settings::OPTION_GOVERNING_SHARED_SITES, [] );
		$this->assertNotSame( 'brand-one-key', $stored[0]['api_key'] );

		$this->assertSame(
			[
				'https://brand-one.example/' => [
					'api_key' => 'brand-one-key',
					'id'      => 'brand-1',
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example/',
				],
			],
			Settings::get_shared_sites()
		);
	}

	/**
	 * Tests get_shared_site_by_url returns the matching site.
	 */
	public function test_get_shared_site_by_url(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'brand-1',
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example',
					'api_key' => 'brand-one-key',
				],
			]
		);

		$this->assertSame( 'Brand One', Settings::get_shared_site_by_url( 'https://brand-one.example' )['name'] );
	}

	/**
	 * Tests get_shared_site_by_url returns null for unknown URLs.
	 */
	public function test_get_shared_site_by_url_returns_null_for_unknown(): void {
		$this->assertNull( Settings::get_shared_site_by_url( 'https://unknown.example' ) );
	}

	/**
	 * Tests parent site URL can be stored and retrieved.
	 */
	public function test_set_parent_site_url_and_get(): void {
		$this->assertTrue( Settings::set_parent_site_url( 'https://governing.example/' ) );
		$this->assertSame( 'https://governing.example/', Settings::get_parent_site_url() );
	}

	/**
	 * Tests get_api_key generates and caches a key when none is set.
	 */
	public function test_get_api_key_generates_if_not_set(): void {
		$api_key = Settings::get_api_key();

		$this->assertNotSame( '', $api_key );
		$this->assertNotSame( $api_key, get_option( Settings::OPTION_CONSUMER_API_KEY, '' ) );
		$this->assertSame( $api_key, Settings::get_api_key() );
	}

	/**
	 * Tests regenerate_api_key returns a different key each time.
	 */
	public function test_regenerate_api_key_returns_new_key(): void {
		$initial_api_key = Settings::get_api_key();
		$new_api_key     = Settings::regenerate_api_key();

		$this->assertNotSame( '', $new_api_key );
		$this->assertNotSame( $initial_api_key, $new_api_key );
		$this->assertSame( $new_api_key, Settings::get_api_key() );
	}

	/**
	 * Tests on_site_type_change generates an API key when switching to consumer.
	 */
	public function test_on_site_type_change_generates_api_key_for_consumer(): void {
		$this->settings->on_site_type_change( '', Settings::SITE_TYPE_CONSUMER );

		$stored_api_key = get_option( Settings::OPTION_CONSUMER_API_KEY, '' );

		$this->assertIsString( $stored_api_key );
		$this->assertNotSame( '', $stored_api_key );
		$this->assertSame( Settings::get_api_key(), Encryptor::decrypt( $stored_api_key ) );
	}

	/**
	 * Asserts a setting is registered in WordPress.
	 *
	 * @param string $setting_name Setting name to check.
	 */
	private function assertSettingRegistered( string $setting_name ): void {
		$registered_settings = get_registered_settings();

		$this->assertArrayHasKey( $setting_name, $registered_settings );

		global $new_allowed_options;

		$this->assertContains( $setting_name, $new_allowed_options[ Settings::SETTING_GROUP ] ?? [] );
	}
}
