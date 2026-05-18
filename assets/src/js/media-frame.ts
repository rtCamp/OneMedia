/**
 * Add custom functionality to the WordPress media frame.
 */

/**
 * WordPress dependencies
 */
import {
	createElement,
	createRoot,
	useEffect,
	useState,
} from '@wordpress/element';
import { Snackbar } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import BrowserUploaderButton from '../admin/media-sharing/components/browser-uploader';
import type { NoticeState } from '../types/notice';
import type { WPMediaAttachmentModel } from '../types/wordpress';
import {
	getFrameProperty,
	getNoticeClass,
	observeElement,
	showSnackbarNotice,
} from './utils';

interface MediaAttachmentViewInstance {
	model: WPMediaAttachmentModel;
	el: HTMLElement;
}

interface MediaAttachmentViewConstructor {
	prototype: {
		render: (
			this: MediaAttachmentViewInstance,
			...args: unknown[]
		) => MediaAttachmentViewInstance;
	};
}

type MediaAttachmentGetter = ( id: number | string ) => WPMediaAttachmentModel;

type SyncAttachmentSource =
	| HTMLElement
	| WPMediaAttachmentModel
	| null
	| undefined;

type AttachmentProcessor = ( element: HTMLElement ) => void;

interface MediaReplacedDetail {
	attachmentId?: string;
}

interface SnackbarNoticeDetail {
	detail?: NoticeState;
}

interface JsonErrorResponse {
	data?: {
		message?: string;
	};
}

const isBrandSite = window.OneMediaMediaFrame.siteType === 'brand-site';
const isMediaPage = Boolean( window.OneMediaMediaUpload?.isMediaPage );

/**
 * Show a snackbar notice when a notice payload is provided.
 *
 * @param {NoticeState|null} notice - The notice payload to display.
 * @return {void}
 */
const setSnackbarNotice = ( notice: NoticeState | null ): void => {
	if ( notice ) {
		showSnackbarNotice( notice );
	}
};

/**
 * Check whether the given value is a WordPress media attachment model.
 *
 * @param {SyncAttachmentSource} attachmentOrElement - The value to inspect.
 * @return {boolean} Whether the value is an attachment model.
 */
const isAttachmentModel = (
	attachmentOrElement: SyncAttachmentSource
): attachmentOrElement is WPMediaAttachmentModel =>
	Boolean(
		attachmentOrElement &&
			typeof attachmentOrElement === 'object' &&
			'get' in attachmentOrElement &&
			typeof attachmentOrElement.get === 'function'
	);

/**
 * Get sync status from attachment model/element.
 *
 * @param {HTMLElement|Object} attachmentOrElement - Attachment model or DOM element.
 *
 * @return {boolean} Whether the attachment is synced.
 */
function isSyncAttachment(
	attachmentOrElement: SyncAttachmentSource
): boolean {
	// Invalid input
	if ( ! attachmentOrElement ) {
		return false;
	}

	// Helper to check sync status value
	const isSyncValue = ( value: unknown ): boolean =>
		value === true || value === 1 || value === '1';

	// Handle Backbone model
	if ( isAttachmentModel( attachmentOrElement ) ) {
		return isSyncValue( attachmentOrElement.get( 'is_onemedia_sync' ) );
	}

	// Not a DOM element
	if ( ! ( attachmentOrElement instanceof HTMLElement ) ) {
		return false;
	}

	// No attachment ID
	const attachmentId =
		attachmentOrElement.dataset[ 'id' ] ||
		attachmentOrElement.dataset[ 'attachmentId' ];
	if ( ! attachmentId ) {
		return false;
	}

	// Try media model
	const attachmentGetter = getFrameProperty< MediaAttachmentGetter >(
		'wp.media.attachment'
	);
	if ( attachmentGetter ) {
		const attachment = attachmentGetter( attachmentId );
		return isSyncValue( attachment.get( 'is_onemedia_sync' ) );
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
	const attachmentView = getFrameProperty< MediaAttachmentViewConstructor >(
		'wp.media.view.Attachment'
	);
	if ( ! attachmentView ) {
		return;
	}

	const originalAttachmentRender = attachmentView.prototype.render;
	attachmentView.prototype.render = function ( ...args: unknown[] ) {
		// Call original render.
		originalAttachmentRender.apply( this, args );

		// Add custom class if synced.
		if ( this.model.get( 'is_onemedia_sync' ) === true ) {
			// this.el is the native DOM element
			this.el.classList.add( 'onemedia-synced-media' );

			// Disable pointer events on sync media for brand site's upload page.
			if ( isBrandSite && isMediaPage ) {
				this.el.style.pointerEvents = 'none';
			}
		} else {
			// Ensure class is removed for non-sync media.
			this.el.classList.add( 'onemedia-non-synced-media' );
		}

		return this;
	};
}

/**
 * Customize the media frame.
 */
function customizeMediaFrame() {
	// Add replace media button and remove 'delete permanently' link in attachment details.
	customizeMediaDetails();
}

/**
 * Customize media details to add replace media button and remove delete permanently link.
 */
function customizeMediaDetails() {
	const containers = document.querySelectorAll(
		'.replace-media-react-container'
	);

	// Process attachments on governing site.
	processAttachments(
		containers,
		'data-customize-media-details-processed',
		true,
		// Process for sync media only.
		false,
		( container ) => {
			const attachmentId = container.dataset[ 'attachmentId' ];

			// Remove the delete button link from governing site.
			removeDeleteLinks();

			// Render Replace Media button for sync media.
			const mediaReplacedDetail = attachmentId ? { attachmentId } : {};
			const uploaderButtonProps = {
				onAddMediaSuccess: () => {
					// Handle success - refresh attachment.
					if ( attachmentId ) {
						const attachmentGetter =
							getFrameProperty< MediaAttachmentGetter >(
								'wp.media.attachment'
							);
						const attachment = attachmentGetter?.( attachmentId );
						if ( attachment ) {
							void attachment.fetch();
						}
					}

					// Trigger custom event.
					document.dispatchEvent(
						new CustomEvent< MediaReplacedDetail >(
							'mediaReplaced',
							{
								detail: mediaReplacedDetail,
							}
						)
					);
				},
				isSyncMediaUpload: false,
				setNotice: setSnackbarNotice,
				...( attachmentId ? { attachmentId } : {} ),
			};

			const root = createRoot( container );
			root.render(
				createElement( BrowserUploaderButton, uploaderButtonProps )
			);
		}
	);

	// Process attachments on brand site.
	if ( ! isBrandSite ) {
		return;
	}

	// Get attachment id from URL parameter if available.
	const attachmentId = new URLSearchParams( window.location.search ).get(
		'item'
	);

	// Sanitize and validate attachment ID.
	if ( ! attachmentId ) {
		return;
	}

	// Check if attachment is sync attachment from Backbone model
	const attachmentGetter = getFrameProperty< MediaAttachmentGetter >(
		'wp.media.attachment'
	);
	const attachment = attachmentGetter?.( attachmentId );
	if ( attachment && isSyncAttachment( attachment ) ) {
		removeDeleteLinks();
	}
}

/**
 * Remove delete permanently link from attachment details for sync media.
 */
function removeDeleteLinks() {
	// Remove the delete button link.
	const deleteLinks = document.querySelectorAll(
		'.button-link.delete-attachment'
	);

	deleteLinks.forEach( ( deleteLink ) => {
		if ( deleteLink instanceof HTMLElement ) {
			deleteLink.style.display = 'none';
		}
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
function processAttachments(
	attachmentElements: NodeListOf< Element >,
	processedAttr: string,
	processedSync: boolean,
	processedNonSync: boolean,
	processFn: AttachmentProcessor
) {
	attachmentElements.forEach( ( element ) => {
		if ( ! ( element instanceof HTMLElement ) ) {
			return;
		}

		const attachmentId =
			element.dataset[ 'id' ] || element.dataset[ 'attachmentId' ];

		// Skip if already processed.
		if ( ! attachmentId || element.hasAttribute( processedAttr ) ) {
			return;
		}

		// Get sync status from attachment data.
		const isSync = isSyncAttachment( element );

		// Apply processing function on all sync attachments.
		if ( processedSync && isSync ) {
			processFn( element );
		}

		// Apply processing function on all non-sync attachments.
		if ( processedNonSync && ! isSync ) {
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
		const [ notice, setNotice ] = useState< NoticeState | null >( null );

		// Listen for custom notice events.
		useEffect( () => {
			const handleNoticeEvent = ( event: Event ) => {
				const detail = ( event as CustomEvent< SnackbarNoticeDetail > )
					.detail?.detail;
				if ( detail ) {
					setNotice( detail );
				}
			};

			document.addEventListener( 'onemediaNotice', handleNoticeEvent );

			return () => {
				document.removeEventListener(
					'onemediaNotice',
					handleNoticeEvent
				);
			};
		}, [] );

		if ( ! notice ) {
			return null;
		}

		return createElement( Snackbar, {
			explicitDismiss: false,
			onRemove: () => setNotice( null ),
			className: getNoticeClass( notice.type ),
			children: notice.message,
		} );
	};

	const root = createRoot( snackbarContainer );
	root.render( createElement( SnackbarComponent ) );
}

/**
 * Intercept AJAX errors made via XMLHttpRequest (used by WP admin-ajax) and show snackbar notices.
 */
function interceptAjaxErrors() {
	class InterceptedXMLHttpRequest extends XMLHttpRequest {
		requestUrl = '';
		requestBody = '';

		override open(
			method: string,
			url: string | URL,
			async: boolean = true,
			username?: string | null,
			password?: string | null
		): void {
			this.requestUrl = typeof url === 'string' ? url : url.toString();
			super.open(
				method,
				url,
				async,
				username ?? undefined,
				password ?? undefined
			);
		}

		override send( body?: Document | XMLHttpRequestBodyInit | null ): void {
			this.requestBody = typeof body === 'string' ? body : '';

			this.addEventListener( 'load', () => {
				try {
					const isSaveAttachment =
						this.requestUrl.includes( 'admin-ajax.php' ) &&
						this.requestBody.includes( 'action=save-attachment' );

					if ( ! isSaveAttachment || this.status < 400 ) {
						return;
					}

					// Handle JSON error responses (from wp_send_json_error).
					const json = JSON.parse(
						this.responseText
					) as JsonErrorResponse;
					if ( json.data?.message ) {
						showSnackbarNotice( {
							type: 'error',
							message: json.data.message,
						} );
					}
				} catch {
					// Ignore JSON parse errors and other errors.
				}
			} );

			super.send( body );
		}
	}

	window.XMLHttpRequest = InterceptedXMLHttpRequest;
}

// Initialize the media frame.
domReady( () => {
	initCustomizeMediaFrame();
	initSnackBarNotice();
	interceptAjaxErrors();
} );
