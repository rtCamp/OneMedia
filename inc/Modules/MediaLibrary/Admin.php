<?php
/**
 * Admin class to handle all the admin functionalities related to MediaLibrary.
 *
 * @package OneMedia\Modules\Post_Types;
 */

namespace OneMedia\Modules\MediaLibrary;

use OneMedia\Contracts\Interfaces\Registrable;
use OneMedia\Modules\Core\Assets;
use OneMedia\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
		add_filter( 'ajax_query_attachments_args', [ $this,'onemedia_filter_ajax_query_attachments_args' ] );
		add_filter( 'ajax_query_attachments_args', [ $this,'onemedia_filter_ajax_query_attachments' ] );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		if ( in_array( $current_screen->id, [ 'upload', 'edit-onemedia_media_type' ], true ) ) {
			wp_enqueue_style( Assets::MEDIA_TAXONOMY_STYLE_HANDLE );
		}

		if ( 'upload' !== $current_screen->id && ! Settings::is_consumer_site() ) {
			return;
		}

		wp_localize_script(
			Assets::MEDIA_SYNC_FILTER_SCRIPT_HANDLE,
			'oneMediaMediaUpload',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::MEDIA_SYNC_FILTER_SCRIPT_HANDLE );

		wp_localize_script(
			Assets::MEDIA_FRAME_SCRIPT_HANDLE,
			'OneMediaMediaFrame',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::MEDIA_FRAME_SCRIPT_HANDLE );
	}

	/**
	 * Handle sync status filter in Ajax requests for media library.
	 *
	 * @param array $query WordPress query arguments.
	 *
	 * @return array Modified query arguments.
	 */
	public function onemedia_filter_ajax_query_attachments_args( array $query ): array {

		// Handle the meta_query passed from our JavaScript.
		if ( isset( $query['meta_query'] ) ) {
			return $query;
		}

		// Nonce verification for AJAX requests.
		if ( wp_doing_ajax() && isset( $_REQUEST['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'onemedia_check_sync_status' ) ) {
				return $query;
			}
		}

		// Handle direct URL parameter for grid mode.
		$request_query = array_map( 'sanitize_text_field', isset( $_REQUEST['query'] ) ? $_REQUEST['query'] : [] );
		if ( isset( $request_query ) && ! empty( $request_query ) && isset( $request_query['onemedia_sync_status'] ) && ! empty( $request_query['onemedia_sync_status'] ) ) {
			$sync_status = sanitize_text_field( wp_unslash( $request_query['onemedia_sync_status'] ) );

			if ( 'sync' === $sync_status ) {
				$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => 'onemedia_sync_status',
						'value'   => 'sync',
						'compare' => '=',
					],
				];
			} elseif ( 'no_sync' === $sync_status ) {
				$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					[
						'key'     => 'onemedia_sync_status',
						'value'   => 'no_sync',
						'compare' => '=',
					],
					[
						'key'     => 'onemedia_sync_status',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		return $query;
	}

	/**
	 * Filter the AJAX query for attachments to include or exclude onemedia_sync based on the onemedia_sync_media_filter parameter.
	 *
	 * @param array $query The current query arguments.
	 *
	 * @return array Modified query arguments.
	 */
	public function onemedia_filter_ajax_query_attachments( array $query ): array {
		// Nonce verification for AJAX requests.
		if ( wp_doing_ajax() ) {
			$nonce = filter_input( INPUT_POST, '_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce = sanitize_text_field( wp_unslash( $nonce ) );
			if ( ! wp_verify_nonce( $nonce, 'onemedia_check_sync_status' ) ) {
				return $query;
			}
		}

		// Check if this is an AJAX request for attachments and we're filtering for onemedia_sync.
		if ( wp_doing_ajax() ) {
			$post_action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! isset( $post_action ) || empty( $post_action ) || 'query-attachments' !== $post_action ) {
				return $query;
			}

			$post_query = array_map( 'sanitize_text_field', isset( $_POST['query'] ) ? $_POST['query'] : [] );
			if ( ! isset( $post_query ) || empty( $post_query ) || ! isset( $post_query['onemedia_sync_media_filter'] ) || empty( $post_query['onemedia_sync_media_filter'] ) ) {
				return $query;
			}

			$onemedia_sync_media_filter = sanitize_text_field( wp_unslash( $post_query['onemedia_sync_media_filter'] ) );

			if ( 'true' === $onemedia_sync_media_filter ) {
				$query['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
						'field'    => 'slug',
						'terms'    => ONEMEDIA_PLUGIN_TAXONOMY_TERM,
					],
				];
			}

			// If onemedia_sync_media_filter: false then exclude onemedia.
			if ( 'false' === $onemedia_sync_media_filter ) {
				$query['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
						'field'    => 'slug',
						'terms'    => ONEMEDIA_PLUGIN_TAXONOMY_TERM,
						'operator' => 'NOT IN',
					],
				];
			}
		}
		return $query;
	}
}
