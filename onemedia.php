<?php
/**
 * Plugin Name: OneMedia
 * Version: 1.0.0
 * Description: A unified, scalable and centralized Media Library that stores brand assets once and automatically propagates them to every connected site.
 * Author: Utsav Patel, Ahmar Zaidi, rtCamp
 * Author URI: https://rtcamp.com
 * Text Domain: onemedia
 * Domain Path: /languages
 * Requires at least: 6.0.0
 * Requires PHP: 7.4
 *
 * @package OneMedia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONEMEDIA_PLUGIN_VERSION', '0.1.0' );
define( 'ONEMEDIA_PLUGIN_FEATURES_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ONEMEDIA_PLUGIN_FEATURES_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ONEMEDIA_PLUGIN_RELATIVE_PATH', dirname( plugin_basename( __FILE__ ) ) );
define( 'ONEMEDIA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ONEMEDIA_PLUGIN_BUILD_PATH', ONEMEDIA_PLUGIN_FEATURES_PATH . '/assets/build' );
define( 'ONEMEDIA_PLUGIN_SRC_PATH', ONEMEDIA_PLUGIN_FEATURES_PATH . '/assets/src' );
define( 'ONEMEDIA_PLUGIN_TEMPLATES_PATH', ONEMEDIA_PLUGIN_FEATURES_PATH . '/inc/templates' );
define( 'ONEMEDIA_PLUGIN_BUILD_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/assets/build' );
define( 'ONEMEDIA_PLUGIN_SLUG', 'onemedia' );
define( 'ONEMEDIA_PLUGIN_GOVERNING_SITE', 'governing' );
define( 'ONEMEDIA_PLUGIN_BRAND_SITE', 'brand' );
define( 'ONEMEDIA_PLUGIN_TAXONOMY', 'onemedia_media_type' );
define( 'ONEMEDIA_PLUGIN_TAXONOMY_TERM', 'onemedia' );
define( 'ONEMEDIA_PLUGIN_TERM_NAME', 'OneMedia' );

/**
 * Load the plugin.
 *
 * @return void
 */
function onemedia_plugin_loader(): void {
	// If autoload file does not exist then show notice that you are running the plugin from github repo so you need to build assets and install composer dependencies.
	if ( ! file_exists( ONEMEDIA_PLUGIN_FEATURES_PATH . '/vendor/autoload.php' ) ) {
		// Add admin notice for missing autoload.
		require_once ONEMEDIA_PLUGIN_FEATURES_PATH . '/inc/helpers/custom-functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
		// Escaping handled in the template file.
		echo onemedia_get_template_content( 'notices/no-build-assets' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	include_once ONEMEDIA_PLUGIN_FEATURES_PATH . '/vendor/autoload.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

	\OneMedia\Plugin::get_instance();

	// Load plugin text domain.
	load_plugin_textdomain( 'onemedia', false, ONEMEDIA_PLUGIN_RELATIVE_PATH . '/languages/' );
}

add_action( 'plugins_loaded', 'onemedia_plugin_loader' );

/**
 * Activate the OneMedia plugin.
 *
 * This function initializes the plugin options and adds it to the addons list.
 *
 * @return void
 */
function onemedia_activate(): void { }

// Activation hook.
register_activation_hook( __FILE__, 'onemedia_activate' );

/**
 * Deactivate the OneMedia plugin.
 *
 * This function runs when plugin deactivates.
 *
 * @return void
 */
function onemedia_deactivate(): void { }

// Deactivation hook.
register_deactivation_hook( __FILE__, 'onemedia_deactivate' );
