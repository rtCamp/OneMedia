<?php
/**
 * Tests for consumer media library restrictions.
 *
 * @package OneMedia\Tests\Unit\Modules\MediaLibrary
 */

declare( strict_types = 1 );

namespace OneMedia\Tests\Unit\Modules\MediaLibrary;

use OneMedia\Modules\MediaLibrary\ConsumerAdmin;
use OneMedia\Modules\MediaSharing\Attachment;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @covers \OneMedia\Modules\MediaLibrary\ConsumerAdmin
 */
#[CoversClass( ConsumerAdmin::class )]
final class ConsumerAdminTest extends TestCase {
	/**
	 * Clean options and transients.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_transient( 'onemedia_sync_delete_notice' );
		delete_transient( 'onemedia_sync_edit_notice' );

		parent::tear_down();
	}

	/**
	 * Tests hook registration for consumer sites.
	 */
	public function test_register_hooks_skips_governing_site_and_registers_consumer_hooks(): void {
		$admin = new ConsumerAdmin();
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );

		$admin->register_hooks();

		$this->assertFalse( has_filter( 'delete_attachment', [ $admin, 'prevent_attachment_deletion' ] ) );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
		$admin->register_hooks();

		$this->assertSame( 10, has_filter( 'delete_attachment', [ $admin, 'prevent_attachment_deletion' ] ) );
		$this->assertSame( 10, has_action( 'admin_notices', [ $admin, 'show_deletion_notice' ] ) );
		$this->assertSame( 10, has_filter( 'media_row_actions', [ $admin, 'remove_edit_delete_links' ] ) );
	}

	/**
	 * Tests transient notices.
	 */
	public function test_show_deletion_notice_outputs_and_clears_notices(): void {
		set_transient( 'onemedia_sync_delete_notice', true, 30 );
		set_transient( 'onemedia_sync_edit_notice', true, 30 );

		ob_start();
		( new ConsumerAdmin() )->show_deletion_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'please delete it from there first', (string) $output );
		$this->assertStringContainsString( 'please edit it over there', (string) $output );
		$this->assertFalse( get_transient( 'onemedia_sync_delete_notice' ) );
		$this->assertFalse( get_transient( 'onemedia_sync_edit_notice' ) );
	}

	/**
	 * Tests synced attachments have edit/delete row actions removed.
	 */
	public function test_remove_edit_delete_links_only_changes_synced_attachments(): void {
		$admin         = new ConsumerAdmin();
		$attachment_id = self::factory()->attachment->create();
		$post          = get_post( $attachment_id );
		$actions       = [
			'edit'   => 'Edit',
			'delete' => 'Delete',
			'view'   => 'View',
		];

		$this->assertSame( $actions, $admin->remove_edit_delete_links( $actions, $post ) );

		Attachment::set_is_synced( $attachment_id, true );
		$result = $admin->remove_edit_delete_links( $actions, $post );

		$this->assertArrayNotHasKey( 'edit', $result );
		$this->assertArrayNotHasKey( 'delete', $result );
		$this->assertSame( 'View', $result['view'] );
		$this->assertSame( 'invalid', $admin->remove_edit_delete_links( 'invalid', $post ) );
	}
}
