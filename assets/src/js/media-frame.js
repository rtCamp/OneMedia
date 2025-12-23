/**
 * Add custom functionality to the WordPress media frame.
 */

/**
 * WordPress dependencies
 */
import { createRoot, createElement, useState, useEffect } from '@wordpress/element';
import { Snackbar } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { observeElement, getFrameProperty, getNoticeClass, showSnackbarNotice } from './utils';
import BrowserUploaderButton from '../admin/media-sharing/components/browser-uploader';

const isBrandSite = window.OneMediaMediaFrame.siteType === 'brand-site';
const isMediaPage = window.OneMediaMediaUpload?.isMediaPage;

/**
 * Get sync status from attachment model/element.
 *
 * @param {HTMLElement|Object} attachmentOrElement - Attachment model or DOM element.
 *
 * @return {boolean} Whether the attachment is synced.
 */
function isSyncAttachment( attachmentOrElement ) {
	// Invalid input
	if ( ! attachmentOrElement ) {
		return false;
	}

	// Helper to check sync status value
	const isSyncValue = ( value ) => value === true || value === 1 || value === '1';

	// Handle Backbone model
	if ( typeof attachmentOrElement.get === 'function' ) {
		return isSyncValue( attachmentOrElement.get( 'is_sync_attachment' ) );
	}

	// Not a DOM element
	if ( ! ( attachmentOrElement instanceof HTMLElement ) ) {
		return false;
	}

	// No attachment ID
	const attachmentId = attachmentOrElement.dataset.id || attachmentOrElement.dataset.attachmentId;
	if ( ! attachmentId ) {
		return false;
	}

	// Try media model
	const wpMedia = getFrameProperty( 'wp.media' );
	if ( wpMedia && wpMedia.attachment ) {
		const attachment = wpMedia.attachment( attachmentId );
		if ( attachment && attachment.get ) {
			return isSyncValue( attachment.get( 'is_sync_attachment' ) );
		}
	}

	// Fallback to CSS class
	return attachmentOrElement.classList.contains( 'onemedia-synced-media' );
}

/**
 * Extend Backbone media view to add custom class for synced attachments.
 * This should only be called once during initialization.
 */
function customizeSyncMediaFrame() {
	// Check if wp.media is available
	if ( ! window.wp || ! window.wp.media ) {
		return;
	}

	const originalAttachmentRender = window.wp.media.view.Attachment.prototype.render;

	window.wp.media.view.Attachment.prototype.render = function() {
		// Call original render.
		originalAttachmentRender.apply( this, arguments );

		// Add custom class if synced.
		if ( this.model.get( 'is_sync_attachment' ) === true ) {
			// this.el is the native DOM element
			const element = this.el;

			element.classList.add( 'onemedia-synced-media' );

			// Disable pointer events on sync media for brand site's upload page.
			if ( isBrandSite && isMediaPage ) {
				element.style.pointerEvents = 'none';
			}
		} else {
			// Ensure class is removed for non-sync media.
			const element = this.el;
			element.classList.add( 'onemedia-non-synced-media' );
		}

		return this;
	};
}

/**
 * Customize the media frame.
 */
async function customizeMediaFrame() {
	// Add replace media button and remove 'delete permanently' link in attachment details.
	customizeMediaDetails();
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
				return createElement( BrowserUploaderButton, {
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
			root.render( createElement( MediaReplaceComponent ) );
		},
	);

	// Process attachments on brand site.
	if ( isBrandSite ) {
		// Get attachment id from URL parameter if available.
		const urlParams = new URLSearchParams( window?.location?.search );
		const attachmentId = urlParams?.get( 'item' );

		// Sanitize and validate attachment ID.
		if ( ! attachmentId ) {
			return;
		}

		// Check if attachment is sync attachment from Backbone model
		const wpMedia = getFrameProperty( 'wp.media' );
		if ( wpMedia && wpMedia.attachment ) {
			const attachment = wpMedia.attachment( attachmentId );
			if ( attachment && isSyncAttachment( attachment ) ) {
				removeDeleteLinks();
			}
		}
	}
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
	attachmentElements.forEach( ( element ) => {
		const attachmentId = element.dataset.id || element.dataset.attachmentId;

		// Skip if already processed.
		if ( ! attachmentId || element.hasAttribute( processedAttr ) ) {
			return;
		}

		// Get sync status from attachment data.
		const isSync = isSyncAttachment( element );

		// Apply processing function on all sync attachments.
		if ( processedSync && isSync && typeof processFn === 'function' ) {
			processFn( element );
		}

		// Apply processing function on all non-sync attachments.
		if ( processedNonSync && ! isSync && typeof processFn === 'function' ) {
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
	// Extend Backbone prototype once on initialization
	customizeSyncMediaFrame();

	// For media library page and media modal.
	observeElement( '.attachments-wrapper ul li.attachment', () => {
		customizeMediaFrame();
	} );
}

/**
 * Create and initialize a snackbar notice element.
 */
function initSnackBarNotice() {
	const snackbarContainer = document.createElement( 'div' );
	snackbarContainer.id = 'onemedia-snackbar-container';
	document.body.appendChild( snackbarContainer );

	// Render snackbar component.
	const SnackbarComponent = () => {
		const [ notice, setNotice ] = useState( null );

		// Listen for custom notice events.
		useEffect( () => {
			const handleNoticeEvent = ( event ) => {
				const detail = event?.detail || {};
				const type = detail?.type || 'error';
				const message = detail?.message || '';
				setNotice( { type, message } );
			};

			document.addEventListener( 'onemediaNotice', handleNoticeEvent );

			return () => {
				document.removeEventListener( 'onemediaNotice', handleNoticeEvent );
			};
		}, [] );

		if ( ! notice ) {
			return null;
		}
		return (
			<Snackbar
				status={ notice?.type ?? 'error' }
				isDismissible={ true }
				onRemove={ () => setNotice( null ) }
				className={ getNoticeClass( notice?.type ) }
			>
				{ notice?.message }
			</Snackbar>
		);
	};

	const root = createRoot( snackbarContainer );
	root.render( createElement( SnackbarComponent ) );
}

/**
 * Intercept AJAX errors made via XMLHttpRequest (used by WP admin-ajax) and show snackbar notices.
 */
async function interceptAjaxErrors() {
	const OriginalXHR = window?.XMLHttpRequest;

	window.XMLHttpRequest = function() {
		const xhr = new OriginalXHR();

		let requestUrl = '';
		let requestBody = '';

		const originalOpen = xhr.open;
		xhr.open = function( method, url, ...rest ) {
			requestUrl = url || '';
			originalOpen.apply( this, [ method, url, ...rest ] );
		};

		const originalSend = xhr.send;
		xhr.send = function( body ) {
			requestBody = body || '';
			this.addEventListener( 'load', function() {
				try {
					const isSaveAttachment =
						requestUrl.includes( 'admin-ajax.php' ) &&
						requestBody &&
						requestBody.includes( 'action=save-attachment' );

					if ( ! isSaveAttachment ) {
						return;
					}

					const isErrorStatus = this.status >= 400;
					if ( ! isErrorStatus ) {
						return;
					}

					let message = '';

					// Handle JSON error responses (from wp_send_json_error).
					const json = JSON.parse( this.responseText );
					if ( json?.data?.message ) {
						message += json.data.message;
					}

					if ( message.length > 0 ) {
						showSnackbarNotice( { type: 'error', message } );
					}
				} catch ( err ) {
					// Ignore JSON parse errors and other errors.
				}
			} );
			originalSend.apply( this, [ body ] );
		};

		return xhr;
	};
}

// Initialize the media frame.
domReady( () => {
	initCustomizeMediaFrame();
	initSnackBarNotice();
	interceptAjaxErrors();
} );
