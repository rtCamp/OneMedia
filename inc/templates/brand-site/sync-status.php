<?php
/**
 * This file contains the markup for onemedia sync status column on the upload screen on brand sites.
 *
 * @package OneMedia
 */

$sync_status = isset( $vars['sync_status'] ) ? $vars['sync_status'] : '';
?>

<select name="onemedia_sync_status" id="onemedia_sync_status">
	<option value="" <?php selected( $sync_status, '', true ); ?>><?php esc_html_e( 'All media', 'onemedia' ); ?></option>
	<option value="sync" <?php selected( $sync_status, 'sync', true ); ?>><?php esc_html_e( 'Synced', 'onemedia' ); ?></option>
	<option value="no_sync" <?php selected( $sync_status, 'no_sync', true ); ?>><?php esc_html_e( 'Not Synced', 'onemedia' ); ?></option>
</select>
<input type="hidden" name="onemedia_sync_nonce" value="<?php echo esc_attr( wp_create_nonce( 'onemedia_sync_filter' ) ); ?>">
