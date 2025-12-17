<?php
/**
 * Class Constants -- this is to define plugin constants.
 *
 * @package OneMedia
 */

namespace OneMedia;


/**
 * Class Constants
 */
class Constants {

	/**
	 * Plugin constant variables.
	 *
	 * @var array $constants
	 */
	public static $constants;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'onemedia/v1';

	/**
	 * Brand sites synced media option.
	 *
	 * @var string
	 */
	public const BRAND_SITES_SYNCED_MEDIA_OPTION = 'onemedia_brand_sites_synced_media';

	/**
	 * OneMedia brand site to governing site attachment key map.
	 *
	 * @var string
	 */
	public const ONEMEDIA_ATTACHMENT_KEY_MAP_OPTION = 'onemedia_attachment_key_map';

	/**
	 * Is OneMedia sync postmeta key.
	 *
	 * @var string
	 */
	public const IS_ONEMEDIA_SYNC_POSTMETA_KEY = 'is_onemedia_sync';

	/**
	 * OneMedia sync sites postmeta key.
	 *
	 * @var string
	 */
	public const ONEMEDIA_SYNC_SITES_POSTMETA_KEY = 'onemedia_sync_sites';

	/**
	 * OneMedia sync status postmeta key.
	 *
	 * @var string
	 */
	public const ONEMEDIA_SYNC_STATUS_POSTMETA_KEY = 'onemedia_sync_status';

	/**
	 * OneMedia sync versions postmeta key.
	 *
	 * @var string
	 */
	public const ONEMEDIA_SYNC_VERSIONS_POSTMETA_KEY = 'onemedia_sync_versions';

	/**
	 * Health check request timeout.
	 *
	 * @var number
	 */
	public const HEALTH_CHECK_REQUEST_TIMEOUT = 15;

	/**
	 * Sync request timeout.
	 *
	 * @var number
	 */
	public const SYNC_REQUEST_TIMEOUT = 25;

	/**
	 * Fetch media request timeout.
	 *
	 * @var number
	 */
	public const FETCH_MEDIA_REQUEST_TIMEOUT = 30;

	/**
	 * Sync media request timeout.
	 *
	 * @var number
	 */
	public const SYNC_MEDIA_REQUEST_TIMEOUT = 15;

	/**
	 * Allowed mime types array.
	 *
	 * This is a list of potentially supported mime types, any unsupported mime types will
	 * be removed during usage, on that particular server.
	 *
	 * @var array
	 */
	public const ALLOWED_MIME_TYPES = array(
		'image/jpg',
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/bmp',
		'image/svg+xml',
	);

}
