/**
 * Add custom functionality to the WordPress media frame.
 */

/**
 * WordPress dependencies
 */
import React from 'react';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { observeElement, getFrameTitle, getFrameProperty, isBrandSite } from './utils';
import BrowserUploaderButton from '../admin/media-sharing/browser-uploader';
import { isSyncAttachment as isSyncAttachmentApi } from '../components/api';

/**
 * Customize the media frame.
 */
async function customizeMediaFrame() {
	// Add replace media button and remove 'delete permanently' link in attachment details.
	customizeMediaDetails();

	// Customize media items display.
	// Get all attachments.
	const attachmentElements = document.querySelectorAll(
		'.attachments-wrapper ul li.attachment',
	);

	if ( ! attachmentElements || 0 === attachmentElements.length ) {
		return;
	}

	// For 'Upload Non-Sync Media' frame hide sync media items.
	hideMediaItems( true, __( 'Upload Non-Sync Media', 'onemedia' ), attachmentElements );

	// For 'Edit Media' frame hide non-sync media items.
	hideMediaItems( false, __( 'Edit Media', 'onemedia' ), attachmentElements );

	// For all the media items in the wp media modal, add sync badge if it's a sync media.
	showSyncBadge( attachmentElements );
}

/**
 * Customize media details to add replace media button and remove delete permanently link.
 */
async function customizeMediaDetails() {
	const containers = document.querySelectorAll( '.replace-media-react-container' );

	// Process attachments on governing site.
	processAttachments(
		containers,
		'data-customize-media-details-processed',
		true, // Process for sync media only.
		false,
		( container ) => {
			const attachmentId = container.dataset.attachmentId;

			// Render Replace Media button for sync media.
			const MediaReplaceComponent = () => {
				return React.createElement( BrowserUploaderButton, {
					onAddMediaSuccess: () => {
						// Handle success - refresh attachment.
						const attachmentProperty = getFrameProperty( 'wp.media.attachment' );
						if ( attachmentProperty && typeof attachmentProperty === 'function' ) {
							const attachment = attachmentProperty( attachmentId );
							if ( attachment ) {
								attachment.fetch();
							}
						}

						// Trigger custom event.
						const event = new CustomEvent( 'mediaReplaced', {
							detail: { attachmentId },
						} );
						document.dispatchEvent( event );
					},
					isSyncMediaUpload: false,
					attachmentId,
				} );
			};

			// Remove the delete button link from governing site.
			removeDeleteLinks();

			const root = createRoot( container );
			root.render( React.createElement( MediaReplaceComponent ) );
		},
	);

	const isBrand = await isBrandSite();

	// Process attachments on brand site.
	if ( isBrand ) {
		// Get attachment id from URL parameter if available.
		const urlParams = new URLSearchParams( window?.location?.search );
		const attachmentId = urlParams?.get( 'item' );

		// Sanitize and validate attachment ID.
		if ( ! attachmentId ) {
			return;
		}

		// Check is attachment is sync attachment.
		const isSyncAttachment = await isSyncAttachmentApi( attachmentId );

		if ( isSyncAttachment ) {
			removeDeleteLinks();
		}
	}
}

/**
 * Show 'SYNCED' badge on sync media items.
 *
 * @param {NodeList} attachmentElements - The media attachment elements.
 */
async function showSyncBadge( attachmentElements ) {
	const isBrand = await isBrandSite();

	processAttachments(
		attachmentElements,
		'data-sync-badge-processed',
		true,
		false,
		( element ) => {
			// Add 'SYNCED' badge.
			element.classList.add( 'onemedia-synced-media' );

			// Disable pointer events on sync media for brand site's upload page.
			if ( isBrand && ! getFrameTitle() ) {
				element.style.pointerEvents = 'none';
			}
		},
	);
}

/**
 * Hide sync media items in specific frames.
 *
 * @param {boolean}  hideSync           - Whether to hide sync media items or non-sync.
 * @param {string}   frameTitle         - The title of the media frame to match.
 * @param {NodeList} attachmentElements - The media attachment elements.
 */
function hideMediaItems( hideSync, frameTitle, attachmentElements ) {
	const currentFrameTitle = getFrameTitle();
	if ( ! currentFrameTitle || ! frameTitle || currentFrameTitle.toLocaleLowerCase() !== frameTitle.toLocaleLowerCase() ) {
		return;
	}

	processAttachments(
		attachmentElements,
		'data-hide-sync-media-processed',
		hideSync,
		! hideSync,
		( element ) => {
			// Hide media item.
			element.style.display = 'none';
		},
	);
}

/**
 * Remove delete permanently link from attachment details for sync media.
 */
function removeDeleteLinks() {
	// Remove the delete button link.
	const deleteLinks = document.querySelectorAll(
		'.button-link.delete-attachment',
	);

	Array.from( deleteLinks ).forEach( ( deleteLink ) => {
		deleteLink.style.display = 'none';
	} );
}

/**
 * Process each attachment element with a given function depending on its sync status.
 *
 * @param {NodeList} attachmentElements - The media attachment elements.
 * @param {string}   processedAttr      - The attribute to mark processed elements.
 * @param {boolean}  processedSync      - Whether to process sync elements or not.
 * @param {boolean}  processedNonSync   - Whether to process non-sync elements or not.
 * @param {Function} processFn          - The function to process each attachment element.
 */
function processAttachments( attachmentElements, processedAttr, processedSync, processedNonSync, processFn ) {
	attachmentElements.forEach( async ( element ) => {
		const attachmentId = element.dataset.id || element.dataset.attachmentId;

		// Skip if already processed.
		if ( ! attachmentId || element.hasAttribute( processedAttr ) ) {
			return;
		}

		// Check if it's a sync attachment.
		const isSyncAttachment = await isSyncAttachmentApi( attachmentId );

		// Apply processing function on all sync attachments.
		if ( processedSync && isSyncAttachment && typeof processFn === 'function' ) {
			processFn( element );
		}

		// Apply processing function on all non-sync attachments.
		if ( processedNonSync && ! isSyncAttachment && typeof processFn === 'function' ) {
			processFn( element );
		}

		// Mark as processed.
		element.setAttribute( processedAttr, 'true' );
	} );
}

/**
 * Initialize customization of the media frame.
 */
function initCustomizeMediaFrame() {
	// For media library page and media modal.
	observeElement( '.attachments-wrapper ul li.attachment', () => {
		customizeMediaFrame();
	} );
}

// Initialize the media frame.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', initCustomizeMediaFrame );
} else {
	initCustomizeMediaFrame();
}
