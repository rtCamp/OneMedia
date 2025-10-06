<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneMedia
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove all OneMedia options on plugin uninstall.
 *
 * This function deletes all options and attachment meta related to OneMedia.
 * 
 * @param bool     $is_multisite Whether the WordPress installation is multisite. Default is false.
 * @param int|null $blog_id The blog ID to target in a multisite setup. Default is null (not used).
 *
 * @return void
 */
function onemedia_remove_all_options_on_uninstall( bool $is_multisite = false, int|null $blog_id ): void {
	global $wpdb;

	// For multisite, we need to use the correct table prefix for the specific blog.
	if ( $is_multisite && $blog_id ) {
		$table_prefix = $wpdb->get_blog_prefix( $blog_id );
	} else {
		$table_prefix = $wpdb->prefix;
	}

	// Ignoring caching warning for these queries because it will only be queried once during plugin uninstallation.
	// Remove all attachment meta related to onemedia.
	$postmeta_table = $table_prefix . 'postmeta';
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$postmeta_table} WHERE meta_key IN ('onemedia_sync_status', 'onemedia_sync_sites', 'is_onemedia_sync')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	$terms_table              = $table_prefix . 'terms';
	$term_taxonomy_table      = $table_prefix . 'term_taxonomy';
	$term_relationships_table = $table_prefix . 'term_relationships';

	// Remove onemedia term and term relationships from all attachments.
	// Get the term_taxonomy_id for slug 'onemedia' in taxonomy 'onemedia_media_type'.
	$term_taxonomy_id_query = sprintf(
		'SELECT tt.term_taxonomy_id 
		FROM `%s` t
		INNER JOIN `%s` tt ON t.term_id = tt.term_id
		WHERE t.slug = %%s AND tt.taxonomy = %%s',
		esc_sql( $terms_table ),
		esc_sql( $term_taxonomy_table )
	);

	$term_taxonomy_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			$term_taxonomy_id_query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'onemedia',
			'onemedia_media_type'
		)
	);

	if ( $term_taxonomy_id ) {
		// Remove all relationships for this term.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_relationships_table,
			array( 'term_taxonomy_id' => $term_taxonomy_id ),
			array( '%d' )
		);

		// Remove the term and its taxonomy entry.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_taxonomy_table,
			array( 'term_taxonomy_id' => $term_taxonomy_id ),
			array( '%d' )
		);

		$delete_query = sprintf(
			'DELETE t FROM `%s` t
			LEFT JOIN `%s` tt ON t.term_id = tt.term_id
			WHERE tt.term_taxonomy_id IS NULL AND t.slug = %%s',
			esc_sql( $terms_table ),
			esc_sql( $term_taxonomy_table )
		);

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				$delete_query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'onemedia'
			) 
		);
	}

	// Clear up onemedia options.
	$onemedia_options = array(
		'onemedia_child_site_api_key',
		'onemedia_media_type_children',
		'onemedia_site_type',
		'onemedia_brand_sites',
		'onemedia_brand_sites_synced_media',
		'onemedia_governing_site_url',
		'onemedia_attachment_key_map',
	);

	foreach ( $onemedia_options as $onemedia_option ) {
		delete_option( $onemedia_option );
	}
}

if ( ! function_exists( 'onemedia_plugin_uninstall' ) ) {

	/**
	 * Function to deactivate the plugin and clean up options.
	 * 
	 * @return void
	 */
	function onemedia_plugin_uninstall(): void {

		// For multisite, loop through each site and remove options.
		if ( is_multisite() ) {
			// Get all blog ids.
			$blog_ids = array();

			if ( function_exists( 'get_sites' ) ) {
				$sites = get_sites( array( 'fields' => 'ids' ) );
				if ( $sites ) {
					$blog_ids = $sites;
				}
			}

			foreach ( $blog_ids as $blog_id ) {
				// Ignoring the warning since we only need the change the database context for uninstallation.
				switch_to_blog( $blog_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
				onemedia_remove_all_options_on_uninstall( true, $blog_id );
				restore_current_blog();
			}       
		} else {
			// For single site, remove options from the current site.
			onemedia_remove_all_options_on_uninstall( false, null );
		}
	}
}
/**
 * Uninstall the plugin and clean up options.
 */
onemedia_plugin_uninstall();
