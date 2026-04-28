<?php
/**
 * Tests for asset registration helpers.
 *
 * @package OneMedia\Tests\Unit\Modules\Core
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Core;

use OneMedia\Modules\Core\Assets;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @covers \OneMedia\Modules\Core\Assets
 */
#[CoversClass( Assets::class )]
final class AssetsTest extends TestCase {
	/**
	 * Resets static localized data between tests.
	 */
	public function tear_down(): void {
		$property = new \ReflectionProperty( Assets::class, 'localized_data' );
		$property->setValue( null, [] );

		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_CONSUMER_API_KEY );

		parent::tear_down();
	}

	/**
	 * Tests localized data is prepared and cached.
	 */
	public function test_get_localized_data_returns_expected_script_data(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );

		$data = Assets::get_localized_data();

		$this->assertArrayHasKey( 'restUrl', $data );
		$this->assertArrayHasKey( 'restNonce', $data );
		$this->assertArrayHasKey( 'apiKey', $data );
		$this->assertSame( Settings::SITE_TYPE_CONSUMER, $data['siteType'] );
		$this->assertSame( 'onemedia_sync_status', $data['syncStatus'] );
		$this->assertSame( $data, Assets::get_localized_data() );
	}

	/**
	 * Tests hook registration.
	 */
	public function test_register_hooks_registers_asset_hooks(): void {
		$assets = new Assets();

		$assets->register_hooks();

		$this->assertSame( 20, has_action( 'admin_enqueue_scripts', [ $assets, 'register_assets' ] ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', [ $assets, 'enqueue_scripts' ] ) );
		$this->assertSame( 10, has_filter( 'script_loader_tag', [ $assets, 'defer_scripts' ] ) );
	}

	/**
	 * Tests asset registration using built files.
	 */
	public function test_register_assets_registers_expected_scripts_and_styles(): void {
		$assets = new Assets();

		$assets->register_assets();

		$this->assertTrue( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( Assets::ONBOARDING_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( Assets::MEDIA_SHARING_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( Assets::MEDIA_FRAME_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( Assets::MEDIA_SYNC_FILTER_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( Assets::ADMIN_STYLES_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( Assets::ONBOARDING_SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( Assets::MAIN_STYLE_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( Assets::MEDIA_TAXONOMY_STYLE_HANDLE, 'registered' ) );
	}

	/**
	 * Tests enqueueing shared admin styles.
	 */
	public function test_enqueue_scripts_enqueues_admin_styles(): void {
		$assets = new Assets();
		wp_register_style( Assets::ADMIN_STYLES_HANDLE, false, [], 'test-version' );

		$assets->enqueue_scripts();

		$this->assertTrue( wp_style_is( Assets::ADMIN_STYLES_HANDLE, 'enqueued' ) );
	}

	/**
	 * Tests defer script filtering.
	 */
	public function test_defer_scripts_adds_defer_to_settings_script_only_once(): void {
		$assets = new Assets();
		$tag    = '<script src="settings.js"></script>';

		$this->assertSame( '<script defer src="settings.js"></script>', $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE ) );
		$this->assertSame( '<script defer src="settings.js"></script>', $assets->defer_scripts( '<script defer src="settings.js"></script>', Assets::SETTINGS_SCRIPT_HANDLE ) );
		$this->assertSame( $tag, $assets->defer_scripts( $tag, Assets::MEDIA_FRAME_SCRIPT_HANDLE ) );
	}

	/**
	 * Tests private asset registration failure branches for missing files.
	 */
	public function test_private_registration_helpers_return_false_for_missing_assets(): void {
		$assets   = new Assets();
		$dir_prop = new \ReflectionProperty( Assets::class, 'plugin_dir' );
		$dir_prop->setValue( $assets, sys_get_temp_dir() . '/onemedia-missing-assets/' );

		$script_method = new \ReflectionMethod( Assets::class, 'register_script' );
		$style_method  = new \ReflectionMethod( Assets::class, 'register_style' );

		$this->assertFalse( $script_method->invoke( $assets, 'missing-script', 'missing' ) );
		$this->assertFalse( $style_method->invoke( $assets, 'missing-style', 'missing' ) );
	}
}
