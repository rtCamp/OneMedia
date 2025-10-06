<?php
/**
 * Rudimentary plugin file.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Brand_Site\Admin_Hooks;
use OneMedia\Traits\Singleton;
use OneMedia\Plugin_Configs\Secret_Key;
use OneMedia\Plugin_Configs\Constants;

/**
 * Main plugin class which initializes the plugin.
 */
class Plugin {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor.
	 */
	protected function __construct() {
		$this->load_plugin_classes();
		$this->load_plugin_configs();
		$this->load_taxonomies();
		$this->load_brand_site_configs();
	}

	/**
	 * Load plugin classes.
	 *
	 * @return void
	 */
	public function load_plugin_classes(): void {
		Assets::get_instance();
		Hooks::get_instance();
		Settings::get_instance();
		REST::get_instance();
		Admin::get_instance();
	}

	/**
	 * Load plugin configs.
	 *
	 * @return void
	 */
	public function load_plugin_configs(): void {
		Secret_Key::get_instance();
		Constants::get_instance();
	}

	/**
	 * Load taxonomies.
	 *
	 * @return void
	 */
	public function load_taxonomies(): void {
	}

	/**
	 * Load brand site configs.
	 *
	 * @return void
	 */
	public function load_brand_site_configs(): void {
		Admin_Hooks::get_instance();
	}
}
