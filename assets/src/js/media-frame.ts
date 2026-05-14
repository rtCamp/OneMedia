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

const setSnackbarNotice = ( notice: NoticeState | null ): void => {
	if ( notice ) {
		showSnackbarNotice( notice );
	}
};

const isAttachmentModel = (
	attachmentOrElement: SyncAttachmentSource
): attachmentOrElement is WPMediaAttachmentModel =>
	Boolean(
		attachmentOrElement &&
			typeof attachmentOrElement === 'object' &&
			'get' in attachmentOrElement &&
			typeof attachmentOrElement.get === 'function'
	);

function isSyncAttachment(
	attachmentOrElement: SyncAttachmentSource
): boolean {
	if ( ! attachmentOrElement ) {
		return false;
	}

	const isSyncValue = ( value: unknown ): boolean =>
		value === true || value === 1 || value === '1';

	if ( isAttachmentModel( attachmentOrElement ) ) {
		return isSyncValue( attachmentOrElement.get( 'is_onemedia_sync' ) );
	}

	if ( ! ( attachmentOrElement instanceof HTMLElement ) ) {
		return false;
	}

	const attachmentId =
		attachmentOrElement.dataset[ 'id' ] ||
		attachmentOrElement.dataset[ 'attachmentId' ];
	if ( ! attachmentId ) {
		return false;
	}

	const attachmentGetter = getFrameProperty< MediaAttachmentGetter >(
		'wp.media.attachment'
	);
	if ( attachmentGetter ) {
		const attachment = attachmentGetter( attachmentId );
		return isSyncValue( attachment.get( 'is_onemedia_sync' ) );
	}

	return attachmentOrElement.classList.contains( 'onemedia-synced-media' );
}

function customizeSyncMediaFrame() {
	const attachmentView = getFrameProperty< MediaAttachmentViewConstructor >(
		'wp.media.view.Attachment'
	);
	if ( ! attachmentView ) {
		return;
	}

	const originalAttachmentRender = attachmentView.prototype.render;
	attachmentView.prototype.render = function ( ...args: unknown[] ) {
		originalAttachmentRender.apply( this, args );

		if ( this.model.get( 'is_onemedia_sync' ) === true ) {
			this.el.classList.add( 'onemedia-synced-media' );
			if ( isBrandSite && isMediaPage ) {
				this.el.style.pointerEvents = 'none';
			}
		} else {
			this.el.classList.add( 'onemedia-non-synced-media' );
		}

		return this;
	};
}

function customizeMediaFrame() {
	customizeMediaDetails();
}

function customizeMediaDetails() {
	const containers = document.querySelectorAll(
		'.replace-media-react-container'
	);

	processAttachments(
		containers,
		'data-customize-media-details-processed',
		true,
		false,
		( container ) => {
			const attachmentId = container.dataset[ 'attachmentId' ];

			removeDeleteLinks();

			const mediaReplacedDetail = attachmentId ? { attachmentId } : {};
			const uploaderButtonProps = {
				onAddMediaSuccess: () => {
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

	if ( ! isBrandSite ) {
		return;
	}

	const attachmentId = new URLSearchParams( window.location.search ).get(
		'item'
	);
	if ( ! attachmentId ) {
		return;
	}

	const attachmentGetter = getFrameProperty< MediaAttachmentGetter >(
		'wp.media.attachment'
	);
	const attachment = attachmentGetter?.( attachmentId );
	if ( attachment && isSyncAttachment( attachment ) ) {
		removeDeleteLinks();
	}
}

function removeDeleteLinks() {
	const deleteLinks = document.querySelectorAll(
		'.button-link.delete-attachment'
	);

	deleteLinks.forEach( ( deleteLink ) => {
		if ( deleteLink instanceof HTMLElement ) {
			deleteLink.style.display = 'none';
		}
	} );
}

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
		if ( ! attachmentId || element.hasAttribute( processedAttr ) ) {
			return;
		}

		const isSync = isSyncAttachment( element );
		if ( processedSync && isSync ) {
			processFn( element );
		}

		if ( processedNonSync && ! isSync ) {
			processFn( element );
		}

		element.setAttribute( processedAttr, 'true' );
	} );
}

function initCustomizeMediaFrame() {
	customizeSyncMediaFrame();
	observeElement( '.attachments-wrapper ul li.attachment', () => {
		customizeMediaFrame();
	} );
}

function initSnackBarNotice() {
	const snackbarContainer = document.createElement( 'div' );
	snackbarContainer.id = 'onemedia-snackbar-container';
	document.body.appendChild( snackbarContainer );

	const SnackbarComponent = () => {
		const [ notice, setNotice ] = useState< NoticeState | null >( null );

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

domReady( () => {
	initCustomizeMediaFrame();
	initSnackBarNotice();
	interceptAjaxErrors();
} );
