<?php
/**
 * Tests for the Core\Assets class.
 *
 * @package OneMedia\Tests\Unit\Modules\Core
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\Core;

use OneMedia\Modules\Core\Assets;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Core\Assets class.
 */
#[CoversClass( Assets::class )]
final class AssetsTest extends TestCase {
	/**
	 * Tests register_hooks adds both enqueue actions and the script tag filter.
	 */
	public function test_register_hooks_adds_enqueue_actions(): void {
		( new Assets() )->register_hooks();

		$this->assertNotFalse( has_action( 'admin_enqueue_scripts' ) );
		$this->assertNotFalse( has_filter( 'script_loader_tag' ) );
	}

	/**
	 * Tests defer_scripts inserts defer into the tag for the settings script handle.
	 */
	public function test_defer_scripts_adds_defer_to_settings_handle(): void {
		$tag    = '<script src="settings.js"></script>';
		$result = ( new Assets() )->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE );

		$this->assertStringContainsString( ' defer src', $result );
	}

	/**
	 * Tests defer_scripts returns the tag unchanged for a non-deferred handle.
	 */
	public function test_defer_scripts_skips_non_deferred_handles(): void {
		$tag    = '<script src="other.js"></script>';
		$result = ( new Assets() )->defer_scripts( $tag, Assets::MEDIA_FRAME_SCRIPT_HANDLE );

		$this->assertSame( $tag, $result );
	}

	/**
	 * Tests defer_scripts returns the tag unchanged when defer is already present.
	 */
	public function test_defer_scripts_skips_already_deferred_tag(): void {
		$tag    = '<script defer src="settings.js"></script>';
		$result = ( new Assets() )->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE );

		$this->assertSame( $tag, $result );
	}
}
