<?php
/**
 * Admin class to handle all the admin functionalities related to Media Sharing.
 *
 * @package OneMedia\Modules\Post_Types;
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Core\Assets;
use OneMedia\Modules\Settings\Admin as Settings_Admin;
use OneMedia\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'current_screen', [ $this, 'add_help_tabs' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Register submenu pages.
	 */
	public function add_submenu(): void {
		// Only add plugin-specific submenu pages if sites have been connecting.
		if ( ! Settings::is_governing_site() ) {
			return;
		}

		add_submenu_page(
			Settings_Admin::MENU_SLUG,
			__( 'Media Sharing', 'onemedia' ),
			'<span class="onemedia-media-sharing-page">' . __( 'Media Sharing', 'onemedia' ) . '</span>',
			'manage_options',
			Settings_Admin::MENU_SLUG,
			[ $this, 'screen_callback' ],
			1
		);
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

		wp_enqueue_media();

		wp_localize_script(
			Assets::MEDIA_SHARING_SCRIPT_HANDLE,
			'oneMediaMediaSharing',
			Assets::get_localized_data(),
		);

		wp_localize_script(
			Assets::MEDIA_FRAME_SCRIPT_HANDLE,
			'oneMediaMediaFrame',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::MEDIA_FRAME_SCRIPT_HANDLE );

		wp_enqueue_script( Assets::MEDIA_SHARING_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::MAIN_STYLE_HANDLE );
		wp_enqueue_style( Assets::HIDE_DELETE_BUTTON_STYLE_HANDLE );
	}

	/**
	 * Callback for the screen content.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Media Sharing', 'onemedia' ); ?></h1>
			<div id="onemedia-media-sharing"></div>
		</div>
		<?php
	}

	/**
	 * Add help tabs to the media sharing page.
	 *
	 * @return void
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( $screen->id, 'toplevel_page_onemedia' ) ) {
			return;
		}

		$help_overview_content       = self::onemedia_get_template_content( 'help/overview' );
		$help_how_to_share_content   = self::onemedia_get_template_content( 'help/how-to-share' );
		$help_sharing_modes_content  = self::onemedia_get_template_content( 'help/sharing-modes' );
		$help_best_practices_content = self::onemedia_get_template_content( 'help/best-practices' );

		// Overview tab.
		$screen->add_help_tab(
			[
				'id'      => 'onemedia-overview',
				'title'   => __( 'Overview', 'onemedia' ),
				'content' => $help_overview_content,
			]
		);

		// How to Share tab.
		$screen->add_help_tab(
			[
				'id'      => 'onemedia-how-to-share',
				'title'   => __( 'How to Share', 'onemedia' ),
				'content' => $help_how_to_share_content,
			]
		);

		// Sharing Modes tab.
		$screen->add_help_tab(
			[
				'id'      => 'onemedia-sharing-modes',
				'title'   => __( 'Sharing Modes', 'onemedia' ),
				'content' => $help_sharing_modes_content,
			]
		);

		// Best Practices tab.
		$screen->add_help_tab(
			[
				'id'      => 'onemedia-best-practices',
				'title'   => __( 'Tips & Best Practices', 'onemedia' ),
				'content' => $help_best_practices_content,
			]
		);
	}

	/**
	 * Return onemedia template content.
	 *
	 * @param string $slug Template path.
	 * @param array  $vars Template variables.
	 *
	 * @return string Template markup.
	 */
	public static function onemedia_get_template_content( string $slug, array $vars = [] ): string {
		ob_start();

		$template = sprintf( '%s.php', $slug );

		$located_template = '';
		if ( file_exists( ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template ) ) {
			$located_template = ONEMEDIA_PLUGIN_TEMPLATES_PATH . '/' . $template;
		}

		if ( '' === $located_template ) {
			return '';
		}

		// Ensure vars is an array.
		if ( ! is_array( $vars ) ) {
			$vars = []; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- Variable is used within the template.
		}

		include $located_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

		return ob_get_clean();
	}
}
