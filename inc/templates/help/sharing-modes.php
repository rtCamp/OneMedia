<?php
/**
 * This file contains the markup for onemedia sharing modes help content.
 *
 * @package OneMedia
 */

?>

<div class="onemedia-help-content">
	<h3><?php esc_html_e( 'Sharing Modes Explained', 'onemedia' ); ?></h3>
	<div class="onemedia-sharing-modes">
		<div class="non-sync-mode">
			<h5>📋  <?php esc_html_e( 'Non-Sync Mode', 'onemedia' ); ?></h5>
			<p><strong><?php esc_html_e( 'What it does:', 'onemedia' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Media is copied as independent files.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'No auto-updates from governing.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'Each brand site manages its own copy.', 'onemedia' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Flexibility:', 'onemedia' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Brand sites can edit title, caption, and metadata.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'Governing changes won\'t affect brand site copies.', 'onemedia' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Best for:', 'onemedia' ); ?></strong> <?php esc_html_e( 'Stock photos, customizable content.', 'onemedia' ); ?></p>
		</div>
		<div class="sync-mode">
			<h5>🔄 <?php esc_html_e( 'Sync Mode', 'onemedia' ); ?></h5>
			<p><strong><?php esc_html_e( 'What it does:', 'onemedia' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Media stays synchronized between governing and brand sites.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'Any changes made in governing site automatically update on brand sites.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'Updates include: title, caption, alt text, and even media replacement.', 'onemedia' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Restrictions:', 'onemedia' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Synced media cannot be modified on brand sites.', 'onemedia' ); ?></li>
				<li><?php esc_html_e( 'Changes must be made from governing site.', 'onemedia' ); ?></li>
			</ul>
			<p><strong><?php esc_html_e( 'Best for:', 'onemedia' ); ?></strong> <?php esc_html_e( 'Logos, branding assets.', 'onemedia' ); ?></p>
		</div>
	</div>
</div>
