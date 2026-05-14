/**
 * Media Grid Sync Filter Implementation
 */

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { getFrameProperty } from './utils';

type SyncFilterValue = '' | 'sync' | 'no_sync';

interface MediaQueryMetaEquals {
	key: string;
	value: string;
	compare: '=';
}

interface MediaQueryMetaMissing {
	key: string;
	compare: 'NOT EXISTS';
}

type MediaQueryMeta =
	| MediaQueryMetaEquals[]
	| {
			relation: 'OR';
			0: MediaQueryMetaEquals;
			1: MediaQueryMetaMissing;
	  };

interface MediaQuery {
	onemedia_sync_status?: SyncFilterValue;
	meta_query?: MediaQueryMeta;
	[ key: string ]: unknown;
}

interface MediaAjaxOptions {
	data: {
		_ajax_nonce?: string;
		query: MediaQuery;
		[ key: string ]: unknown;
	};
	[ key: string ]: unknown;
}

interface BackboneModelLike {
	set: ( key: string, value: unknown ) => void;
	get: ( key: string ) => unknown;
}

interface AttachmentsCollectionLike {
	props: BackboneModelLike;
}

interface FrameContentLike {
	get: () => {
		collection?: AttachmentsCollectionLike;
	} | null;
}

interface SyncFilterViewElement {
	html: ( value: string ) => void;
	append: ( element: HTMLElement ) => void;
}

interface SyncFilterViewInstance {
	$el: SyncFilterViewElement;
	model: BackboneModelLike;
	select: HTMLSelectElement;
	listenTo: (
		model: BackboneModelLike,
		eventName: string,
		callback: () => void
	) => void;
	createSelect: () => void;
	updateSelect: () => void;
}

interface SyncFilterViewOptions {
	controller: unknown;
	model: BackboneModelLike;
	priority: number;
}

interface SyncFilterViewConstructor {
	new ( options: SyncFilterViewOptions ): unknown;
}

interface MediaViewConstructor {
	extend: ( definition: {
		tagName: string;
		className: string;
		initialize: ( this: SyncFilterViewInstance ) => void;
		createSelect: ( this: SyncFilterViewInstance ) => void;
		updateSelect: ( this: SyncFilterViewInstance ) => void;
	} ) => SyncFilterViewConstructor;
}

interface ToolbarLike {
	set: ( key: string, view: unknown ) => void;
}

interface AttachmentsBrowserInstance {
	controller: unknown;
	collection: AttachmentsCollectionLike;
	toolbar: ToolbarLike;
}

interface AttachmentsBrowserConstructor {
	prototype: {
		createToolbar: ( this: AttachmentsBrowserInstance ) => void;
	};
	extend: ( definition: {
		createToolbar: ( this: AttachmentsBrowserInstance ) => void;
	} ) => AttachmentsBrowserConstructor;
}

type MediaAjax = (
	this: unknown,
	action: string,
	options: MediaAjaxOptions
) => unknown;

interface WordPressMediaLike {
	View: MediaViewConstructor;
	view: {
		AttachmentsBrowser: AttachmentsBrowserConstructor;
	};
	ajax: MediaAjax;
	frame?: {
		content?: FrameContentLike;
	};
}

function initSyncMediaFilter() {
	const media = getFrameProperty< WordPressMediaLike >( 'wp.media' );
	if ( ! media ) {
		return;
	}

	const mediaUploadConfig = window.OneMediaMediaUpload;
	if ( ! mediaUploadConfig ) {
		return;
	}

	const allLabel = mediaUploadConfig.allLabel ?? '';
	const syncLabel = mediaUploadConfig.syncLabel ?? '';
	const notSyncLabel = mediaUploadConfig.notSyncLabel ?? '';
	const nonce = mediaUploadConfig.nonce ?? '';
	const syncStatusKey = mediaUploadConfig.syncStatus ?? '';
	const originalAttachmentsBrowser = media.view.AttachmentsBrowser;

	const SyncFilterView = media.View.extend( {
		tagName: 'label',
		className: 'attachment-filters onemedia-sync-filter-wrapper',

		initialize( this: SyncFilterViewInstance ) {
			this.createSelect();
			this.listenTo( this.model, 'change', this.updateSelect );
		},

		createSelect( this: SyncFilterViewInstance ) {
			this.$el.html( '' );

			this.select = document.createElement( 'select' );
			this.select.className = 'attachment-filters onemedia-sync-filter';
			this.select.innerHTML = `
				<option value="">${ allLabel }</option>
				<option value="sync">${ syncLabel }</option>
				<option value="no_sync">${ notSyncLabel }</option>
			`;

			const urlParams = new URLSearchParams( window.location.search );
			const savedFilter =
				( urlParams.get( syncStatusKey ) as SyncFilterValue | null ) ??
				'';
			this.select.value = savedFilter;

			if ( savedFilter ) {
				this.model.set( syncStatusKey, savedFilter );
			}

			this.$el.append( this.select );

			this.select.addEventListener( 'change', () => {
				const value = this.select.value as SyncFilterValue;
				this.model.set( syncStatusKey, value );

				if ( window.history.replaceState ) {
					const url = new URL( window.location.href );
					if ( value ) {
						url.searchParams.set( syncStatusKey, value );
					} else {
						url.searchParams.delete( syncStatusKey );
					}
					window.history.replaceState( {}, '', url );
				}
			} );
		},

		updateSelect( this: SyncFilterViewInstance ) {
			const value = this.model.get( syncStatusKey );
			this.select.value = typeof value === 'string' ? value : '';
		},
	} );

	media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend( {
		createToolbar( this: AttachmentsBrowserInstance ) {
			originalAttachmentsBrowser.prototype.createToolbar.call( this );

			this.toolbar.set(
				'onemediaSyncFilter',
				new SyncFilterView( {
					controller: this.controller,
					model: this.collection.props,
					priority: -75,
				} )
			);
		},
	} );

	const originalAjax = media.ajax;
	media.ajax = function (
		this: unknown,
		action: string,
		options: MediaAjaxOptions
	) {
		if ( action === 'query-attachments' ) {
			const syncStatus = options.data.query.onemedia_sync_status;

			options.data._ajax_nonce = nonce;

			if ( syncStatus === 'sync' ) {
				options.data.query.meta_query = [
					{
						key: syncStatusKey,
						value: 'sync',
						compare: '=',
					},
				];
			} else if ( syncStatus === 'no_sync' ) {
				options.data.query.meta_query = {
					relation: 'OR',
					0: {
						key: syncStatusKey,
						value: 'no_sync',
						compare: '=',
					},
					1: {
						key: syncStatusKey,
						compare: 'NOT EXISTS',
					},
				};
			}

			delete options.data.query.onemedia_sync_status;
		}

		return originalAjax.call( this, action, options );
	};

	const initialFilter = new URLSearchParams( window.location.search ).get(
		syncStatusKey
	) as SyncFilterValue | null;
	const library = media.frame?.content?.get()?.collection;
	if ( initialFilter && library ) {
		library.props.set( syncStatusKey, initialFilter );
	}
}

domReady( () => {
	initSyncMediaFilter();
} );
