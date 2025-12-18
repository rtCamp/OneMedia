<?php
/**
 * Plugin Name:         OneMedia
 * Description:         A unified, scalable and centralized Media Library that stores brand assets once and automatically propagates them to every connected site.
 * Author:              rtCamp
 * Author URI:          https://rtcamp.com
 * Plugin URI:          https://github.com/rtCamp/OneMedia/
 * Update URI:          https://github.com/rtCamp/OneMedia/
 * License:             GPL2+
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:         onemedia
 * Domain Path:         /languages
 * Version:             1.0.0
 * Requires PHP:        8.0
 * Requires at least:   6.8
 * Tested up to:        6.8.2
 *
 * @package OneMedia
 */

declare ( strict_types=1 );

namespace OneMedia;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Version of the plugin.
	 */
	define( 'ONEMEDIA_VERSION', '0.1.0' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONEMEDIA_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONEMEDIA_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * Plugin basename.
	 */
	define( 'ONEMEDIA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

	/**
	 * Other constants.
	 */
	define( 'ONEMEDIA_PLUGIN_BUILD_PATH', ONEMEDIA_DIR . '/assets/build' );
	define( 'ONEMEDIA_PLUGIN_SRC_PATH', ONEMEDIA_DIR . '/assets/src' );
	define( 'ONEMEDIA_PLUGIN_TEMPLATES_PATH', ONEMEDIA_DIR . '/inc/Modules/MediaSharing/templates' );
	define( 'ONEMEDIA_PLUGIN_BUILD_URI', ONEMEDIA_URL . '/assets/build' );
	define( 'ONEMEDIA_PLUGIN_SLUG', 'onemedia' );
	define( 'ONEMEDIA_PLUGIN_GOVERNING_SITE', 'governing-site' );
	define( 'ONEMEDIA_PLUGIN_BRAND_SITE', 'brand-site' );
	define( 'ONEMEDIA_PLUGIN_TAXONOMY', 'onemedia_media_type' );
	define( 'ONEMEDIA_PLUGIN_TAXONOMY_TERM', 'onemedia' );
	define( 'ONEMEDIA_PLUGIN_TERM_NAME', 'OneMedia' );
}

constants();

// If autoloader failed, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneMedia\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( 'OneMedia\Main' ) ) {
	add_action(
		'plugins_loaded',
		static function (): void {
			\OneMedia\Main::instance();

			//phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- @todo remove before submitting to .org.
			load_plugin_textdomain( 'onemedia', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	);
}

/**
 * Activate the OneMedia plugin.
 *
 * This function initializes the plugin options and adds it to the addons list.
 *
 * @return void
 */
function onemedia_activate(): void { }

// Activation hook.
register_activation_hook( __FILE__, '\OneMedia\onemedia_activate' );

/**
 * Deactivate the OneMedia plugin.
 *
 * This function runs when plugin deactivates.
 *
 * @return void
 */
function onemedia_deactivate(): void { }

// Deactivation hook.
register_deactivation_hook( __FILE__, '\OneMedia\onemedia_deactivate' );
