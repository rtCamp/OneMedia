<?php
/**
 * The main plugin file.
 *
 * @package OneMedia
 */

declare( strict_types = 1 );

namespace OneMedia;

use OneMedia\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\OneMedia\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Modules\Core\Rest::class,
		Modules\Core\Assets::class,
		Modules\Settings\Admin::class,
		Modules\Settings\Settings::class,
		Modules\Rest\Basic_Options_Controller::class,
		Modules\Rest\Media_Sharing_Controller::class,
		Modules\MediaLibrary\Admin::class,
		Modules\MediaLibrary\ConsumerAdmin::class,
		Modules\MediaSharing\Admin::class,
		Modules\MediaSharing\Attachment::class,
		Modules\MediaSharing\UserInterface::class,
		Modules\MediaSharing\MediaProtection::class,
		Modules\MediaSharing\MediaActions::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Load the plugin classes.
		$this->load();

		// Do other stuff here like dep-checking, telemetry, etc.
	}

	/**
	 * Load the plugin classes.
	 */
	private function load(): void {
		// Loop through all the classes, instantiate them, and register any hooks.
		$instances = [];
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			/**
			 * If it's a singleton, we can use the instance method. Otherwise we instantiate it directly.
			 *
			 * @todo reduce use of singletons where possible.
			 */
			$instances[ $class_name ] = new $class_name();
			$instances[ $class_name ]->register_hooks();
		}

		// Do other generalizable stuff here.
	}
}
