<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneMedia
 */

declare( strict_types=1 );

namespace OneMedia;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Multisite loop for uninstalling from all sites.
 */
function multisite_uninstall(): void {
	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		uninstall();
		restore_current_blog();
	}
}

/**
 * The (site-specific) uninstall function.
 */
function uninstall(): void {
	delete_plugin_data();
}

/**
 * Deletes meta, options, transients, etc.
 */
function delete_plugin_data(): void {
	global $wpdb;

	$table_prefix = $wpdb->prefix;

	// Ignoring caching warning for these queries because it will only be queried once during plugin uninstallation.
	// Remove all attachment meta related to onemedia.
	$postmeta_table = $table_prefix . 'postmeta';
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$postmeta_table} WHERE meta_key IN ('onemedia_sync_status', 'onemedia_sync_sites', 'is_onemedia_sync', 'onemedia_sync_versions')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			[ 'term_taxonomy_id' => $term_taxonomy_id ],
			[ '%d' ]
		);

		// Remove the term and its taxonomy entry.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_taxonomy_table,
			[ 'term_taxonomy_id' => $term_taxonomy_id ],
			[ '%d' ]
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

	$options = [
		// Common site options.
		'onemedia_site_type',
		'onemedia_show_onboarding',

		// Governing site options.
		'onemedia_shared_sites',

		// Brand site options.
		'onemedia_parent_site_url',
		'onemedia_consumer_api_key',

		// Plugin specific options.
		'onemedia_media_type_children',
		'onemedia_brand_sites_synced_media',
		'onemedia_attachment_key_map',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

// Run the uninstaller.
multisite_uninstall();
