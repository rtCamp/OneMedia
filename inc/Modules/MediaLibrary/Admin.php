<?php
/**
 * Admin class to handle all the admin functionalities related to logs.
 *
 * @package OneMedia\Modules\Post_Types;
 */

namespace OneMedia\Modules\MediaLibrary;

use OneMedia\Constants;
use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Core\Assets;
use OneMedia\Modules\Rest\Abstract_REST_Controller;
use OneMedia\Modules\Settings\Admin as Settings_Admin;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Utils;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The screen ID for the settings page.
	 *
	 * We use the settings menu slug, so it's the default screen.
	 */
	public const SCREEN_ID = Settings_Admin::MENU_SLUG;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( str_contains( $hook, 'onemedia' ) === false ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || str_contains( $current_screen->id, Settings_Admin::MENU_SLUG ) === false ) {
			return;
		}

		if ( in_array( $current_screen->id, array( 'upload', 'edit-onemedia_media_type' ), true ) ) {
			wp_enqueue_style( Assets::MEDIA_TAXONOMY_STYLE_HANDLE );
		}

		if ( 'upload' !== $current_screen->id && !Settings::is_consumer_site() ) {
			return;
		}

		wp_localize_script(
			Assets::MEDIA_SYNC_FILTER_SCRIPT_HANDLE,
			'oneMediaMediaUpload',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::MEDIA_SYNC_FILTER_SCRIPT_HANDLE );
	}
}
