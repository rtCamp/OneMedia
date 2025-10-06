<?php
/**
 * Initializes actions and filters for OneMedia plugin.
 *
 * @package OneMedia
 */

namespace OneMedia;

use OneMedia\Plugin_Configs\Constants;
use OneMedia\Traits\Singleton;

/**
 * Class Hooks
 */
class Hooks {

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
		// Handle syncing the attachment metadata to brand sites for a sync media file.
		add_action( 'attachment_updated', array( $this, 'update_sync_attachments' ), 10, 1 );

		// Remove sync option if attachment is deleted.
		add_action( 'delete_attachment', array( $this, 'remove_sync_meta' ), 10, 1 );

		// Make sure onemedia_media_type is private on brand sites.
		add_action(
			'init',
			function () {
				if ( Utils::is_brand_site() ) {
					// Get onemedia_media_type.
					$taxonomy = get_taxonomy( ONEMEDIA_PLUGIN_TAXONOMY );
					if ( $taxonomy && $taxonomy->show_ui ) {
						// Set onemedia_media_type to private.
						$taxonomy->show_ui = false;
					}
				}
			},
			PHP_INT_MAX
		);

		// Add replace media button to media library react view.
		add_action( 'attachment_fields_to_edit', array( $this, 'add_replace_media_button' ), 10, 2 );

		// Add container for modal for site selection on activation.
		add_action( 'admin_footer', array( $this, 'add_site_selection_modal' ) );

		// Add body class for site selection modal.
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_modal' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_missing_sites' ) );
	}

	/**
	 * Create global variable onemedia_sites with site info.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string Modified body classes.
	 */
	public function add_body_class_for_modal( string $classes ): string {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// Check if site type is set.
		$is_site_type_set = Utils::is_site_type_set();
		if ( $is_site_type_set ) {
			// If site type is already set, do not show the modal.
			return $classes;
		}

		// Add onemedia-site-selection-modal class to body.
		$classes .= ' onemedia-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add site selection modal to admin footer.
	 *
	 * @return void
	 */
	public function add_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}
		if ( ! defined( 'ONEMEDIA_PLUGIN_SLUG' ) ) {
			return;
		}

		// Check if site type is set.
		if ( Utils::is_site_type_set() ) {
			// If site type is already set, do not show the modal.
			return;
		}

		?>
		<div class="wrap">
			<div id="onemedia-site-selection-modal" class="onemedia-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add replace media button to media library react view.
	 *
	 * @param array    $form_fields Form fields.
	 * @param \WP_Post $post        The WP_Post attachment object.
	 *
	 * @return array Modified form fields.
	 */
	public function add_replace_media_button( array $form_fields, \WP_Post $post ): array {
		if ( Utils::is_brand_site() ) {
			// Don't show replace media button on brand sites.
			return $form_fields;
		}

		// Don't show replace media button for non sync media.
		$show_replace_media = Utils::is_sync_attachment( $post->ID );
		if ( ! $show_replace_media ) {
			return $form_fields;
		}

		$form_fields['replace_media'] = array(
			'label' => __( 'Replace Media', 'onemedia' ),
			'input' => 'html',
			'html'  => sprintf(
				/* translators: %d is the post ID. */
				'<div class="replace-media-react-container" data-attachment-id="%d"></div>',
				$post->ID
			),
		);

		return $form_fields;
	}

	/**
	 * Remove sync meta when attachment is deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function remove_sync_meta( int $attachment_id ): void {
		// On Governing Site.

		// Check post is_onemedia_sync is set to be true.
		$synced_brand_site_media = Utils::get_brand_sites_synced_media();
		if ( ! $synced_brand_site_media || ! isset( $synced_brand_site_media[ $attachment_id ] ) ) {
			return;
		}

		// Delete onemedia_sync_sites meta.
		delete_post_meta( $attachment_id, Constants::ONEMEDIA_SYNC_SITES_POSTMETA_KEY );

		// Delete is_onemedia_sync meta.
		delete_post_meta( $attachment_id, Constants::IS_ONEMEDIA_SYNC_POSTMETA_KEY );

		// Delete onemedia_sync_status from remote sites.
		$brand_sites = Utils::get_all_brand_sites();

		if ( ! is_array( $brand_sites ) ) {
			return;
		}
		$synced_sites = $synced_brand_site_media[ $attachment_id ] ?? array();

		foreach ( $synced_sites as $site => $site_media_id ) {
			$site_url      = rtrim( $site, '/' );
			$site_media_id = (int) $site_media_id;

			// Get site api key from options.
			$site_api_key = '';
			foreach ( $brand_sites as $brand_site ) {
				if ( rtrim( $brand_site['siteUrl'], '/' ) === $site_url ) {
					$site_api_key = $brand_site['apiKey'];
					break;
				}
			}

			// Check if site api key is empty.
			if ( empty( $site_api_key ) ) {
				continue;
			}

			// Make POST request to delete attachment on brand sites.
			$response = wp_remote_post(
				$site_url . '/wp-json/' . Constants::NAMESPACE . '/delete-media-metadata',
				array(
					'body'      => wp_json_encode(
						array(
							'attachment_id' => (int) $site_media_id,
						)
					),
					'timeout'   => Constants::SYNC_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'headers'   => array(
						'X-OneMedia-Token' => ( $site_api_key ),
						'Cache-Control'    => 'no-cache, no-store, must-revalidate',
					),
					'sslverify' => false,
				)
			);

			// Check if response is successful.
			if ( is_wp_error( $response ) ) {
				// Show notice in admin that media metadata deletion failed.
				add_action(
					'admin_notices',
					function () use ( $site_url, $response ) {
						$error_message = $response->get_error_message();
						/* translators: %1$s is the site URL, %2$s is the error message. */
						echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Failed to delete media metadata on site %1$s: %2$s', 'onemedia' ), esc_html( $site_url ), esc_html( $error_message ) ) ) . '</p></div>';
					}
				);

				wp_die(
					sprintf(
						/* translators: %1$s is the site URL, %2$s is the error message. */
						esc_html__( 'Failed to delete media metadata on site %1$s: %2$s', 'onemedia' ),
						esc_html( $site_url ),
						esc_html( $response->get_error_message() )
					)
				);
			}
		}

		// Delete synced media from options.
		if ( isset( $synced_brand_site_media[ $attachment_id ] ) ) {
			unset( $synced_brand_site_media[ $attachment_id ] );
			update_option( Constants::BRAND_SITES_SYNCED_MEDIA_OPTION, $synced_brand_site_media );
		}
	}

	/**
	 * Update sync attachments on brand sites.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void.
	 */
	public function update_sync_attachments( int $attachment_id ): void {
		// Check post is_onemedia_sync is set to be true.
		$is_onemedia_sync = Utils::is_sync_attachment( $attachment_id );

		if ( ! $is_onemedia_sync ) {
			return;
		}

		// Get the brand sites this media is synced to.
		$onemedia_sync_sites = Utils::get_sync_sites_postmeta( $attachment_id );
		if ( ! is_array( $onemedia_sync_sites ) ) {
			return;
		}

		// POST request suffix.
		$post_request_suffix = '/wp-json/' . Constants::NAMESPACE . '/update-attachment';

		// Get site api key from options.
		$brand_sites = Utils::get_all_brand_sites();
		if ( ! is_array( $brand_sites ) ) {
			return;
		}
		
		// Send updates to all sites.
		foreach ( $onemedia_sync_sites as $site ) {
			$site_url = $site['site'];
			// Trim trailing slash.
			$site_url      = rtrim( $site_url, '/' );
			$site_media_id = $site['id'];
			$site_api_key  = '';

			if ( empty( $site_url ) || empty( $site_media_id ) ) {
				continue;
			}

			foreach ( $brand_sites as $site ) {
				if ( rtrim( $site['siteUrl'], '/' ) === $site_url ) {
					$site_api_key = $site['apiKey'];
					break;
				}
			}

			// Check if site api key is empty.
			if ( empty( $site_api_key ) ) {
				return;
			}

			// Get update attachment data and its url.
			$attachment_url = wp_get_attachment_url( $attachment_id );

			$attachment_data = wp_get_attachment_metadata( $attachment_id );

			if ( ! $attachment_data || ! is_array( $attachment_data ) ) {
				$attachment_data = array();
			}

			// Get attachment title, alt text, caption and description.
			$attachment_title       = get_the_title( $attachment_id );
			$attachment_alt_text    = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$attachment_caption     = get_post_field( 'post_excerpt', $attachment_id );
			$attachment_description = get_post_field( 'post_content', $attachment_id );

			// Get attachment terms.
			$attachment_terms = Utils::get_onemedia_attachment_terms( $attachment_id ) ?? array();
			$attachment_terms = is_array( $attachment_terms ) ? wp_list_pluck( $attachment_terms, 'slug' ) : array();

			// Set attachment data.
			$attachment_data['title']       = $attachment_title;
			$attachment_data['alt_text']    = $attachment_alt_text;
			$attachment_data['caption']     = $attachment_caption;
			$attachment_data['description'] = $attachment_description;
			$attachment_data['terms']       = $attachment_terms;

			// Make POST request to update existing attachment on brand sites.
			wp_remote_post(
				$site_url . $post_request_suffix,
				array(
					'body'    => ( 
						array(
							'attachment_id'   => (int) $site_media_id,
							'attachment_url'  => $attachment_url,
							'attachment_data' => $attachment_data,
						)
					),
					'timeout' => Constants::SYNC_REQUEST_TIMEOUT, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'headers' => array(
						'X-OneMedia-Token' => ( $site_api_key ),
						'Cache-Control'    => 'no-cache, no-store, must-revalidate',
					),
				)
			);
		}
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string Modified body classes.
	 */
	public function add_body_class_for_missing_sites( string $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// Get oneupdate_shared_sites option.
		$brand_sites = Utils::get_brand_sites();

		// If there are no connected brand sites then hide the media sharing menu.
		if ( empty( $brand_sites ) || ! is_array( $brand_sites ) ) {
			$classes .= ' onemedia-missing-brand-sites ';

			// Remove plugin manager submenu.
			remove_submenu_page( Constants::SETTINGS_PAGE_SLUG, Constants::SETTINGS_PAGE_SLUG );
			return $classes;
		}

		return $classes;
	}
}
