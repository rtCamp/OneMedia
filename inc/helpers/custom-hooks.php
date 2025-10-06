<?php
/**
 * Custom functions for the plugin.
 *
 * @package OneMedia
 */

/**
 * Adds a settings link to the plugin action links on the plugins page.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array Modified plugin action links with settings link added.
 */
function onemedia_add_settings_link( array $links ): array {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=onemedia-settings' ) . '">' . __( 'Settings', 'onemedia' ) . '</a>';

	if ( ! is_array( $links ) ) {
		$links = array();
	}
	array_unshift( $links, $settings_link );

	return $links;
}

// Added a function exists check to prevent fatal errors for phpcs.
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'plugin_action_links_' . plugin_basename( ONEMEDIA_PLUGIN_BASENAME ), 'onemedia_add_settings_link' );
}

/**
 * Handle sync status filter in Ajax requests for media library.
 *
 * @param array $query WordPress query arguments.
 *
 * @return array Modified query arguments.
 */
function onemedia_filter_ajax_query_attachments_args( array $query ): array {

	// Handle the meta_query passed from our JavaScript.
	if ( isset( $query['meta_query'] ) ) {
		return $query;
	}

	// Nonce verification for AJAX requests.
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['_ajax_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'onemedia_check_sync_status' ) ) {
			return $query;
		}
	}

	// Handle direct URL parameter for grid mode.
	$request_query = array_map( 'sanitize_text_field', isset( $_REQUEST['query'] ) ? $_REQUEST['query'] : array() );
	if ( isset( $request_query ) && ! empty( $request_query ) && isset( $request_query['onemedia_sync_status'] ) && ! empty( $request_query['onemedia_sync_status'] ) ) {
		$sync_status = sanitize_text_field( wp_unslash( $request_query['onemedia_sync_status'] ) );

		if ( 'sync' === $sync_status ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'onemedia_sync_status',
					'value'   => 'sync',
					'compare' => '=',
				),
			);
		} elseif ( 'no_sync' === $sync_status ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => 'onemedia_sync_status',
					'value'   => 'no_sync',
					'compare' => '=',
				),
				array(
					'key'     => 'onemedia_sync_status',
					'compare' => 'NOT EXISTS',
				),
			);
		}
	}

	return $query;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'ajax_query_attachments_args', 'onemedia_filter_ajax_query_attachments_args' );
}

/**
 * Filter the AJAX query for attachments to include or exclude onemedia_sync based on the onemedia_sync_media_filter parameter.
 *
 * @param array $query The current query arguments.
 *
 * @return array Modified query arguments.
 */
function onemedia_filter_ajax_query_attachments( array $query ): array {
	// Nonce verification for AJAX requests.
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		$nonce = filter_input( INPUT_POST, '_ajax_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$nonce = sanitize_text_field( wp_unslash( $nonce ) );
		if ( ! wp_verify_nonce( $nonce, 'onemedia_check_sync_status' ) ) {
			return $query;
		}
	}

	// Check if this is an AJAX request for attachments and we're filtering for onemedia_sync.
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		$post_action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! isset( $post_action ) || empty( $post_action ) || 'query-attachments' !== $post_action ) {
			return $query;
		}

		$post_query = array_map( 'sanitize_text_field', isset( $_POST['query'] ) ? $_POST['query'] : array() );
		if ( ! isset( $post_query ) || empty( $post_query ) || ! isset( $post_query['onemedia_sync_media_filter'] ) || empty( $post_query['onemedia_sync_media_filter'] ) ) {
			return $query;
		}

		$onemedia_sync_media_filter = sanitize_text_field( wp_unslash( $post_query['onemedia_sync_media_filter'] ) );

		if ( 'true' === $onemedia_sync_media_filter ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
					'field'    => 'slug',
					'terms'    => ONEMEDIA_PLUGIN_TAXONOMY_TERM,
				),
			);
		}

		// If onemedia_sync_media_filter: false then exclude onemedia.
		if ( 'false' === $onemedia_sync_media_filter ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => ONEMEDIA_PLUGIN_TAXONOMY,
					'field'    => 'slug',
					'terms'    => ONEMEDIA_PLUGIN_TAXONOMY_TERM,
					'operator' => 'NOT IN',
				),
			);
		}
	}
	return $query;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'ajax_query_attachments_args', 'onemedia_filter_ajax_query_attachments' );
}
