<?php
/**
 * This class handles the settings page for the OneMedia plugin.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Traits\Singleton;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 5 );
		add_action( 'current_screen', array( $this, 'add_help_tabs' ) );
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

		$help_overview_content       = onemedia_get_template_content( 'help/overview' );
		$help_how_to_share_content   = onemedia_get_template_content( 'help/how-to-share' );
		$help_sharing_modes_content  = onemedia_get_template_content( 'help/sharing-modes' );
		$help_best_practices_content = onemedia_get_template_content( 'help/best-practices' );

		// Overview tab.
		$screen->add_help_tab(
			array(
				'id'      => 'onemedia-overview',
				'title'   => __( 'Overview', 'onemedia' ),
				'content' => $help_overview_content,
			)
		);

		// How to Share tab.
		$screen->add_help_tab(
			array(
				'id'      => 'onemedia-how-to-share',
				'title'   => __( 'How to Share', 'onemedia' ),
				'content' => $help_how_to_share_content,
			)
		);

		// Sharing Modes tab.
		$screen->add_help_tab(
			array(
				'id'      => 'onemedia-sharing-modes',
				'title'   => __( 'Sharing Modes', 'onemedia' ),
				'content' => $help_sharing_modes_content,
			)
		);

		// Best Practices tab.
		$screen->add_help_tab(
			array(
				'id'      => 'onemedia-best-practices',
				'title'   => __( 'Tips & Best Practices', 'onemedia' ),
				'content' => $help_best_practices_content,
			)
		);
	}

	/**
	 * Adds the OneMedia settings page.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {

		add_menu_page(
			__( 'OneMedia', 'onemedia' ),
			__( 'OneMedia', 'onemedia' ),
			'manage_options',
			Constants::SETTINGS_PAGE_SLUG,
			'__return_null',
			'',
			2
		);

		if ( Utils::is_governing_site() ) {
			add_submenu_page(
				Constants::SETTINGS_PAGE_SLUG,
				__( 'Media Sharing', 'onemedia' ),
				'<span class="onemedia-media-sharing-page">' . __( 'Media Sharing', 'onemedia' ) . '</span>',
				'manage_options',
				Constants::SETTINGS_PAGE_SLUG,
				array( $this, 'render_media_sharing_page' ),
			);
		}

		add_submenu_page(
			Constants::SETTINGS_PAGE_SLUG,
			__( 'Settings', 'onemedia' ),
			__( 'Settings', 'onemedia' ),
			'manage_options',
			Constants::SETTINGS_PAGE_SLUG . '-settings',
			array( $this, 'render_settings_page_content' )
		);

		if ( ! Utils::is_governing_site() ) {
			remove_submenu_page( Constants::SETTINGS_PAGE_SLUG, Constants::SETTINGS_PAGE_SLUG );
		}
	}

	/**
	 * Render media sharing page
	 *
	 * @return void
	 */
	public function render_media_sharing_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Media Sharing', 'onemedia' ); ?></h1>
			<div id="onemedia-media-sharing"></div>
		</div>
		<?php
	}

	/**
	 * Render settings page content.
	 *
	 * @return void
	 */
	public function render_settings_page_content(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onemedia' ); ?></h1>
			<div id="onemedia-settings"></div>
		</div>
		<?php
	}
}
