<?php
/**
 * Registers attachment post meta used for media sharing.
 *
 * @package OneMedia\Modules\MediaSharing;
 */

namespace OneMedia\Modules\MediaSharing;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Settings\Settings;
use OneMedia\Modules\Taxonomies\Media;

/**
 * Class - Attachment
 */
class Attachment implements Registrable {
	/**
	 * Meta key for sync status.
	 */
	public const SYNC_STATUS_POSTMETA_KEY = 'onemedia_sync_status';
	/**
	 * Meta key for sync sites.
	 */
	public const SYNC_SITES_POSTMETA_KEY = 'onemedia_sync_sites';
	/**
	 * Meta key for indicating if attachment is syncing.
	 */
	public const IS_SYNC_POSTMETA_KEY = 'is_onemedia_sync';

	/**
	 * Sync status values.
	 */
	public const SYNC_STATUS_SYNC    = 'sync';
	public const SYNC_STATUS_NO_SYNC = 'no_sync';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_attachment_post_meta' ] );
	}

	/**
	 * Register attachment post meta for media sharing.
	 */
	public function register_attachment_post_meta(): void {
		$post_meta = [
			self::SYNC_STATUS_POSTMETA_KEY => [
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
						'enum' => [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ],
					],
				],
				'single'            => true,
				'type'              => 'string',
				'default'           => false,
				'revisions_enabled' => false,
				'description'       => __( 'Indicates if the attachment is shared via OneMedia.', 'onemedia' ),
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			],
		];

		foreach ( $post_meta as $meta_key => $args ) {
			register_post_meta( 'attachment', $meta_key, $args );
		}
	}

	/**
	 * Get OneMedia attachment terms.
	 *
	 * @param int|\WP_Post $attachment_id The attachment ID.
	 *
	 * @return array Array of terms.
	 */
	public static function get_terms( int|\WP_Post $attachment_id ): array {
		if ( ! $attachment_id ) {
			return [];
		}

		$terms = get_the_terms( $attachment_id, Media::TAXONOMY );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return [];
		}
		return $terms;
	}

	/**
	 * Get OneMedia attachment terms with args.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $args          Arguments to pass to wp_get_post_terms function.
	 *
	 * @return array Array of terms.
	 */
	public static function get_post_terms( int $attachment_id, array $args = [] ): array {
		$terms = wp_get_post_terms( $attachment_id, Media::TAXONOMY, $args );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return $terms;
	}

	/**
	 * Gets the sync status of an attachment.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return 'sync'|'no_sync'|null The sync status value or null if not set.
	 */
	public static function get_sync_status( int $attachment_id ): ?string {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return null;
		}

		$meta_value = get_post_meta( $attachment_id, self::SYNC_STATUS_POSTMETA_KEY, true );

		if ( in_array( $meta_value, [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ], true ) ) {
			return $meta_value;
		}

		return null;
	}

	/**
	 * Updates the sync status of an attachment.
	 *
	 * @param int              $attachment_id The ID of the attachment.
	 * @param 'sync'|'no_sync' $status        The sync status to set.
	 *
	 * @return int|bool Meta ID if the key didn't exist, false on failure, true on success.
	 */
	public static function update_sync_status( int $attachment_id, string $status ) {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return false;
		}

		if ( ! in_array( $status, [ self::SYNC_STATUS_SYNC, self::SYNC_STATUS_NO_SYNC ], true ) ) {
			return false;
		}

		return update_post_meta( $attachment_id, self::SYNC_STATUS_POSTMETA_KEY, $status );
	}

	/**
	 * Get the sync sites of an attachment.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_sync_sites( int $attachment_id ): array {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return [];
		}

		$meta_value = get_post_meta( $attachment_id, self::SYNC_SITES_POSTMETA_KEY, true );

		return is_array( $meta_value ) ? $meta_value : [];
	}

	/**
	 * Gets whether an attachment is currently syncing.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 */
	public static function is_syncing( int $attachment_id ): bool {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return false;
		}

		return (bool) get_post_meta( $attachment_id, self::IS_SYNC_POSTMETA_KEY, true );
	}

	/**
	 * Updates whether an attachment is currently syncing.
	 *
	 * @param int  $attachment_id The ID of the attachment.
	 * @param bool $is_syncing    True if syncing, false otherwise.
	 *
	 * @return int|bool Meta ID if the key didn't exist, false on failure, true on success.
	 */
	public static function update_is_syncing( int $attachment_id, bool $is_syncing ) {
		if ( ! Settings::is_consumer_site() || ! $attachment_id ) {
			return false;
		}

		return update_post_meta( $attachment_id, self::IS_SYNC_POSTMETA_KEY, $is_syncing );
	}
}
