<?php
/**
 * Enqueue assets for OneMedia plugin.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Traits\Singleton;

/**
 * Assets Class
 */
class Assets {

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
		// Enqueue Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Admin page name.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( false !== strpos( $hook_suffix, 'toplevel_page_onemedia' ) ) {
			// Remove all 3rd party notices.
			remove_all_actions( 'admin_notices' );
			wp_enqueue_media();

			// Enqueue admin scripts and styles.
			$this->register_script( 'onemedia-media-sharing-script', 'js/media-sharing.js' );
			$this->register_style( 'onemedia-main-style', 'css/main.css' );
			$this->register_style( 'onemedia-media-hide-delete-button-style', 'css/hide-delete-button.css' );

			// Localize script.
			wp_localize_script(
				'onemedia-media-sharing-script',
				'oneMediaMediaSharing',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'restUrl'          => esc_url( home_url( '/wp-json/' . Constants::NAMESPACE ) ),
					'uploadNonce'      => wp_create_nonce( 'onemedia_upload_media' ),
					'apiKey'           => Utils::get_onemedia_api_key( 'default_api_key' ),
					'allowedMimeTypes' => Utils::get_supported_mime_types(),
				)
			);

			wp_enqueue_script( 'onemedia-media-sharing-script' );
			wp_enqueue_style( 'onemedia-main-style' );
			wp_enqueue_style( 'onemedia-media-hide-delete-button-style' );
		}

		if ( false !== strpos( $hook_suffix, 'onemedia-settings' ) ) {
			// Remove 3rd party notice.
			remove_all_actions( 'admin_notices' );

			// Add setup page script & style.
			$this->register_script( 'onemedia-settings', 'js/settings.js' );
			$this->register_style( 'onemedia-admin-setting-style', 'css/admin.css' );

			// Localize nonce, rest url.
			wp_localize_script(
				'onemedia-settings',
				'oneMediaSettings',
				array(
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'restUrl'  => esc_url( home_url( '/wp-json/' . Constants::NAMESPACE ) ),
					'setupUrl' => admin_url( 'admin.php?page=onemedia-settings' ),
					'apiKey'   => Utils::get_onemedia_api_key( 'default_api_key' ),
				)
			);
			wp_enqueue_script( 'onemedia-settings' );
			wp_enqueue_style( 'onemedia-admin-setting-style' );
		}

		if ( false !== strpos( $hook_suffix, 'plugins' ) ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script( 'onemedia-setup', 'js/plugin.js' );
			$this->register_style( 'onemedia-plugin-setting-style', 'css/admin.css' );

			wp_localize_script(
				'onemedia-setup',
				'oneMediaSetupSettings',
				array(
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'restUrl'  => esc_url( home_url( '/wp-json/' . Constants::NAMESPACE ) ),
					'setupUrl' => admin_url( 'admin.php?page=onemedia-settings' ),
					'apiKey'   => Utils::get_onemedia_api_key( 'default_api_key' ),
				)
			);

			wp_enqueue_script( 'onemedia-setup' );
			wp_enqueue_style( 'onemedia-plugin-setting-style' );
		}

		$this->register_style( 'onemedia-admin-style', 'css/admin.css' );
		wp_enqueue_style( 'onemedia-admin-style' );

		// Add script for media frame on all sites.
		$this->register_script( 'onemedia-media-frame', 'js/media-frame.js' );

		// Localize script.
		wp_localize_script(
			'onemedia-media-frame',
			'oneMediaMediaFrame',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'restUrl'          => esc_url( home_url( '/wp-json/' . Constants::NAMESPACE ) ),
				'uploadNonce'      => wp_create_nonce( 'onemedia_upload_media' ),
				'apiKey'           => Utils::get_onemedia_api_key( 'default_api_key' ),
				'allowedMimeTypes' => Utils::get_supported_mime_types(),
			)
		);
		wp_enqueue_script( 'onemedia-media-frame' );

		// Load upload media page specific scripts and styles.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( $screen && 'upload' === $screen->id && Utils::is_brand_site() ) {
			$this->register_script( 'onemedia-media-upload-script', 'js/media-sync-filter.js' );

			// Localize script.
			wp_localize_script(
				'onemedia-media-upload-script',
				'oneMediaMediaUpload',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'onemedia_check_sync_status' ),
					'allLabel'     => __( 'All media', 'onemedia' ),
					'syncLabel'    => __( 'Synced', 'onemedia' ),
					'notSyncLabel' => __( 'Not Synced', 'onemedia' ),
					'filterLabel'  => __( 'Sync Status', 'onemedia' ),
					'syncStatus'   => Constants::ONEMEDIA_SYNC_STATUS_POSTMETA_KEY,
				)
			);

			wp_enqueue_script( 'onemedia-media-upload-script' );
		}

		if ( in_array( $screen->id, array( 'upload', 'edit-onemedia_media_type' ), true ) ) {
			$this->register_style(
				'onemedia-media-taxonomy-style',
				'css/media-taxonomy.css',
			);
			wp_enqueue_style( 'onemedia-media-taxonomy-style' );
		}
	}

	/**
	 * Get asset dependencies and version info from {handle}.asset.php if exists.
	 *
	 * @param string               $file File name.
	 * @param array                $deps Script dependencies to merge with.
	 * @param int|string|bool|null $ver  Asset version string.
	 *
	 * @return array Asset meta (dependencies and version).
	 */
	public function get_asset_meta( string $file, array $deps = array(), int|string|bool|null $ver = false ): array {
		/* translators: %1$s is the assets build directory path, %2$s is the asset file name. */
		$asset_meta_file = sprintf( '%1$s/js/%2$s.asset.php', untrailingslashit( ONEMEDIA_DIR . '/assets/build' ), basename( $file, '.' . pathinfo( $file )['extension'] ) );
		$asset_meta      = is_readable( $asset_meta_file )
		? include $asset_meta_file // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		: array(
			'dependencies' => array(),
			'version'      => $this->get_file_version( $file, $ver ),
		);

		$asset_meta['dependencies'] = array_merge( $deps, $asset_meta['dependencies'] );

		return $asset_meta;
	}

	/**
	 * Register a new script.
	 *
	 * @param string               $handle    Name of the script. Should be unique.
	 * @param string|bool          $file      script file, path of the script relative to the assets/build/ directory.
	 * @param array                $deps      Optional. An array of registered script handles this script depends on. Default empty array.
	 * @param int|string|bool|null $ver       Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param bool                 $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *                                     Default 'false'.
	 *
	 * @return bool Whether the script has been registered. True on success, false on failure.
	 */
	public function register_script( string $handle, string|bool $file, array $deps = array(), int|string|bool|null $ver = false, bool $in_footer = true ): bool {
		/* translators: %1$s is the assets build directory path, %2$s is the asset file name. */
		$file_path = sprintf( '%1$s/%2$s', ONEMEDIA_DIR . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$asset_meta = $this->get_asset_meta( $file, $deps );

		// Register each dependency styles.
		if ( ! empty( $asset_meta['dependencies'] ) ) {
			foreach ( $asset_meta['dependencies'] as $dependency ) {
				wp_enqueue_style( $dependency );
			}
		}

		if ( ! $asset_meta['version'] ) {
			$asset_meta['version'] = $this->get_file_version( $file, $ver );
		}

		/* translators: %s is the filename. */
		$src = sprintf( ONEMEDIA_URL . '/assets/build/%s', $file );

		return wp_register_script( $handle, $src, $asset_meta['dependencies'], $asset_meta['version'], $in_footer );
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string               $handle Name of the stylesheet. Should be unique.
	 * @param string|bool          $file   style file, path of the script relative to the assets/build/ directory.
	 * @param array                $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param int|string|bool|null $ver    Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param string               $media  Optional. The media for which this stylesheet has been defined.
	 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
	 *
	 * @return bool Whether the style has been registered. True on success, false on failure.
	 */
	public function register_style( string $handle, string|bool $file, array $deps = array(), int|string|bool|null $ver = false, string $media = 'all' ): bool {
		/* translators: %1$s is the assets build directory path, %2$s is the asset file name. */
		$file_path = sprintf( '%1$s/%2$s', ONEMEDIA_DIR . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		/* translators: %s is the filename. */
		$src     = sprintf( ONEMEDIA_URL . '/assets/build/%s', $file );
		$version = $this->get_file_version( $file, $ver );

		return wp_register_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Get file version.
	 *
	 * @param string               $file File path.
	 * @param int|string|bool|null $ver  File version.
	 *
	 * @return int|bool File modification time or false if file doesn't exist.
	 */
	public function get_file_version( string $file, int|string|bool|null $ver = false ): int|bool {
		if ( ! empty( $ver ) ) {
			return $ver;
		}

		/* translators: %1$s is the assets build directory path, %2$s is the asset file name. */
		$file_path = sprintf( '%1$s/%2$s', ONEMEDIA_DIR . '/assets/build', $file );

		return file_exists( $file_path ) ? filemtime( $file_path ) : false;
	}
}
