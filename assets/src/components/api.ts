/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { removeTrailingSlash } from '../js/utils';
import type {
	ApiBaseResponse,
	ApiErrorResponse,
	ApiFetchOptions,
	AttachmentHealthResponse,
	AttachmentVersion,
	FetchMediaItemsOptions,
	GoverningSiteResponse,
	MediaItemsResponse,
	MultisiteTypeResponse,
	SecretKeyResponse,
	ShareMediaPayload,
	ShareMediaResponse,
	SharedSitesResponse,
	SiteTypeResponse,
	SyncAttachmentStatusResponse,
	SyncedSitesMap,
	SyncedSitesResponse,
	SyncAttachmentVersionsResponse,
	UploadMediaResponse,
} from '../types/media-sharing';
import type { BrandSite, SiteType } from '../types/settings';

const ONEMEDIA_REST_API_NAMESPACE = 'onemedia';
const ONEMEDIA_REST_API_VERSION = 'v1';
const ONEMEDIA_REST_API_BASE =
	'/wp-json/' + ONEMEDIA_REST_API_NAMESPACE + '/' + ONEMEDIA_REST_API_VERSION;

const {
	restUrl: REST_URL = '',
	restNonce: NONCE = '',
	apiKey: API_KEY = '',
	ajaxUrl: SHARING_AJAX_URL = '',
} = window.OneMediaMediaFrame ?? {};

const isRecord = ( value: unknown ): value is Record< string, unknown > => {
	return typeof value === 'object' && value !== null;
};

const getQueryString = (
	params: Record< string, string | number >
): string => {
	const normalizedParams = Object.entries( params ).reduce<
		Record< string, string >
	>( ( accumulator, [ key, value ] ) => {
		accumulator[ key ] = String( value );
		return accumulator;
	}, {} );

	return new URLSearchParams( normalizedParams ).toString();
};

const getErrorDataSuccess = ( responseData: ApiErrorResponse ): boolean => {
	if ( ! isRecord( responseData.data ) ) {
		return false;
	}

	return Boolean( responseData.data.success );
};

const getResponseMeta = ( response: ApiBaseResponse ) => {
	return {
		...( typeof response.status === 'number'
			? { status: response.status }
			: {} ),
		...( typeof response.message === 'string'
			? { message: response.message }
			: {} ),
	};
};

const getFailedSites = (
	response: AttachmentHealthResponse | ApiErrorResponse | ShareMediaResponse
): AttachmentHealthResponse[ 'failed_sites' ] => {
	if (
		'failed_sites' in response &&
		Array.isArray( response.failed_sites )
	) {
		return response.failed_sites;
	}

	if (
		isRecord( response.data ) &&
		Array.isArray( response.data[ 'failed_sites' ] )
	) {
		return response.data[
			'failed_sites'
		] as AttachmentHealthResponse[ 'failed_sites' ];
	}

	return [];
};

/**
 * Makes a REST API request to the OneMedia backend.
 */
export const apiFetch = async <
	TResponse extends ApiBaseResponse = ApiBaseResponse,
	TBody = unknown,
>(
	options: ApiFetchOptions< TBody >
): Promise< TResponse | ApiErrorResponse > => {
	const {
		baseurl = REST_URL,
		endpoint,
		method = 'GET',
		nonce = NONCE,
		apiKey = API_KEY,
		body,
		addNotice,
		errorMsg,
		params = {},
	} = options;

	try {
		let url = `${ baseurl }/onemedia/v1/${ endpoint }`;

		if ( Object.keys( params ).length > 0 ) {
			url += `?${ getQueryString( params ) }`;
		}

		const headers: Record< string, string > = {
			'Content-Type': 'application/json',
			'X-OneMedia-Token': apiKey,
		};

		if ( nonce !== '' ) {
			headers[ 'X-WP-Nonce' ] = nonce;
		}

		const requestInit: RequestInit = {
			method,
			headers,
		};

		if ( body !== undefined ) {
			requestInit.body = JSON.stringify( body );
		}

		const response = await fetch( url, requestInit );
		const responseData = ( await response.json() ) as TResponse &
			ApiErrorResponse;

		if ( ! response.ok ) {
			let message =
				typeof responseData.message === 'string'
					? responseData.message
					: errorMsg ||
					  __( 'An unexpected error occurred', 'onemedia' );

			if ( response.status === 404 ) {
				message = __( 'Resource not found', 'onemedia' );
			}

			addNotice?.( {
				type: 'error',
				message,
			} );

			return {
				...responseData,
				success: getErrorDataSuccess( responseData ),
				message,
			};
		}

		return responseData;
	} catch ( error ) {
		const message =
			errorMsg ||
			( error instanceof Error
				? error.message
				: __( 'An error occurred', 'onemedia' ) );

		addNotice?.( {
			type: 'error',
			message,
		} );

		return {
			success: false,
			...( message ? { message } : {} ),
		};
	}
};

export const fetchBrandSites = async (
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< BrandSite[] > => {
	const response = await apiFetch< SharedSitesResponse >( {
		endpoint: 'shared-sites',
		addNotice,
		errorMsg: __( 'Error fetching brand sites.', 'onemedia' ),
	} );

	if (
		! ( 'shared_sites' in response ) ||
		! Array.isArray( response.shared_sites )
	) {
		return [];
	}

	return response.shared_sites.map( ( site: BrandSite ) => ( {
		...site,
		id: String( site.id ),
	} ) );
};

export const checkBrandSiteHealth = async (
	url: string,
	apiKey: string,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< AttachmentHealthResponse > => {
	const response = await apiFetch< AttachmentHealthResponse >( {
		baseurl: removeTrailingSlash( url ) + ONEMEDIA_REST_API_BASE,
		endpoint: 'health-check',
		method: 'GET',
		nonce: '',
		apiKey,
		addNotice,
		errorMsg: __(
			'Health check failed. Please ensure the site is accessible.',
			'onemedia'
		),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
		failed_sites: getFailedSites( response ),
	};
};

export const checkIfAllSitesConnected = async (
	attachmentId: number
): Promise< AttachmentHealthResponse > => {
	const response = await apiFetch<
		AttachmentHealthResponse,
		{ attachment_id: number }
	>( {
		endpoint: 'check-sites-connected',
		method: 'POST',
		body: {
			attachment_id: attachmentId,
		},
		errorMsg: __( 'Failed to check connected sites.', 'onemedia' ),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
		failed_sites: getFailedSites( response ),
	};
};

export const fetchMultisiteType = async (): Promise< string > => {
	const response = await apiFetch< MultisiteTypeResponse >( {
		endpoint: 'multisite-type',
		method: 'GET',
		errorMsg: __( 'Failed to fetch multisite type.', 'onemedia' ),
	} );

	return 'multisite_type' in response &&
		typeof response.multisite_type === 'string'
		? response.multisite_type
		: '';
};

export const fetchSiteType = async (
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< SiteType > => {
	const response = await apiFetch< SiteTypeResponse >( {
		endpoint: 'site-type',
		addNotice,
		errorMsg: __( 'Error fetching site types.', 'onemedia' ),
	} );

	return 'site_type' in response && typeof response.site_type === 'string'
		? response.site_type
		: '';
};

export const saveSiteType = async (
	siteType: SiteType,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< boolean > => {
	const result = await apiFetch( {
		endpoint: 'site-type',
		method: 'POST',
		body: { site_type: siteType },
		addNotice,
		errorMsg: __( 'Error saving site type.', 'onemedia' ),
	} );

	return Boolean( result.success );
};

export const saveBrandSites = async (
	sites: BrandSite[],
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< SharedSitesResponse > => {
	const response = await apiFetch<
		SharedSitesResponse,
		{ sites: BrandSite[] }
	>( {
		endpoint: 'brand-sites',
		method: 'POST',
		body: { sites },
		addNotice,
		errorMsg: __( 'Error saving Brand sites.', 'onemedia' ),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
		...( 'shared_sites' in response &&
		Array.isArray( response.shared_sites )
			? { shared_sites: response.shared_sites }
			: {} ),
	};
};

export const fetchBrandSiteApiKey = async (): Promise< string | null > => {
	const response = await apiFetch< SecretKeyResponse >( {
		endpoint: 'secret-key',
		method: 'GET',
		errorMsg: __( 'Failed to fetch api key.', 'onemedia' ),
	} );

	return 'secret_key' in response && typeof response.secret_key === 'string'
		? response.secret_key
		: null;
};

export const regenerateBrandSiteApiKey = async (): Promise< string | null > => {
	const response = await apiFetch< SecretKeyResponse >( {
		endpoint: 'secret-key',
		method: 'POST',
		errorMsg: __( 'Failed to regenerate API key.', 'onemedia' ),
	} );

	return 'secret_key' in response && typeof response.secret_key === 'string'
		? response.secret_key
		: null;
};

export const fetchSyncedSites = async (
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< SyncedSitesMap > => {
	const response = await apiFetch< SyncedSitesResponse >( {
		endpoint: 'brand-sites-synced-media',
		addNotice,
		errorMsg: __( 'Failed to fetch synced sites.', 'onemedia' ),
	} );

	if ( isRecord( response.data ) ) {
		return response.data as SyncedSitesMap;
	}

	return {};
};

export const fetchMediaItems = async ( {
	search,
	page,
	perPage,
	imageType,
	addNotice,
}: FetchMediaItemsOptions ): Promise< MediaItemsResponse > => {
	const params: Record< string, string | number > = {};

	if ( page ) {
		params[ 'page' ] = page;
	}

	if ( perPage ) {
		params[ 'per_page' ] = perPage;
	}

	if ( imageType ) {
		params[ 'image_type' ] = imageType;
	}

	if ( search ) {
		params[ 'search_term' ] = search;
	}

	const response = await apiFetch< MediaItemsResponse >( {
		endpoint: 'media',
		params,
		addNotice,
		errorMsg: __( 'Failed to fetch media items.', 'onemedia' ),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
		page:
			'page' in response && typeof response.page === 'number'
				? response.page
				: page || 1,
		per_page:
			'per_page' in response && typeof response.per_page === 'number'
				? response.per_page
				: perPage || 0,
		total:
			'total' in response && typeof response.total === 'number'
				? response.total
				: 0,
		total_pages:
			'total_pages' in response &&
			typeof response.total_pages === 'number'
				? response.total_pages
				: 1,
		media_files:
			'media_files' in response && Array.isArray( response.media_files )
				? response.media_files
				: [],
		status:
			'status' in response && typeof response.status === 'number'
				? response.status
				: 0,
	};
};

export const shareMedia = async (
	payload: ShareMediaPayload,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< ShareMediaResponse > => {
	const response = await apiFetch< ShareMediaResponse, ShareMediaPayload >( {
		endpoint: 'sync-media',
		method: 'POST',
		body: payload,
		addNotice,
		errorMsg: __( 'Failed to sync media.', 'onemedia' ),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
		data: {
			failed_sites: getFailedSites( response ),
		},
	};
};

export const uploadMedia = async (
	formData: FormData,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< UploadMediaResponse | null > => {
	try {
		const response = await fetch( SHARING_AJAX_URL, {
			method: 'POST',
			headers: {
				'X-OneMedia-Token': API_KEY,
			},
			credentials: 'same-origin',
			body: formData,
		} );

		const data = ( await response.json() ) as UploadMediaResponse &
			ApiErrorResponse;

		if ( ! response.ok || response.status === 404 ) {
			if (
				isRecord( data.data ) &&
				typeof data.data.message === 'string'
			) {
				throw new Error( data.data.message );
			}
		}

		return {
			success: Boolean( data.success ),
			...getResponseMeta( data ),
			...( isRecord( data.data ) && typeof data.data.message === 'string'
				? {
						data: {
							message: data.data.message,
						},
				  }
				: {} ),
		};
	} catch ( error ) {
		addNotice?.( {
			type: 'error',
			message:
				error instanceof Error
					? error.message
					: __( 'An error occurred during media upload', 'onemedia' ),
		} );

		return null;
	}
};

export const updateExistingAttachment = async (
	attachmentId: number,
	isSyncMediaUpload: boolean,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< ApiBaseResponse > => {
	const response = await apiFetch( {
		endpoint: 'update-existing-attachment',
		method: 'POST',
		body: {
			attachment_id: attachmentId,
			sync_option: isSyncMediaUpload ? 'sync' : 'no_sync',
		},
		addNotice,
		errorMsg: __( 'Failed to update existing attachment.', 'onemedia' ),
	} );

	return {
		success: Boolean( response.success ),
		...getResponseMeta( response ),
	};
};

export const fetchGoverningSite = async (): Promise< string | null > => {
	const response = await apiFetch< GoverningSiteResponse >( {
		endpoint: 'governing-site',
		method: 'GET',
	} );

	return 'governing_site_url' in response &&
		typeof response.governing_site_url === 'string'
		? response.governing_site_url
		: null;
};

export const removeGoverningSite = async (): Promise< boolean > => {
	const response = await apiFetch( {
		endpoint: 'governing-site',
		method: 'DELETE',
	} );

	return Boolean( response.success );
};

export const isSyncAttachment = async (
	attachmentId: number,
	addNotice?: ApiFetchOptions[ 'addNotice' ]
): Promise< boolean > => {
	const response = await apiFetch< SyncAttachmentStatusResponse >( {
		endpoint: 'is-sync-attachment',
		method: 'POST',
		body: {
			attachment_id: Number( attachmentId ),
		},
		addNotice,
		errorMsg: __( 'Failed to check sync status.', 'onemedia' ),
	} );

	return 'is_sync' in response ? Boolean( response.is_sync ) : false;
};

export const fetchSyncAttachmentVersions = async (
	attachmentId: number
): Promise< AttachmentVersion[] > => {
	const response = await apiFetch< SyncAttachmentVersionsResponse >( {
		endpoint: 'sync-attachment-versions',
		method: 'POST',
		body: {
			attachment_id: Number( attachmentId ),
		},
		errorMsg: __( 'Failed to fetch attachment versions.', 'onemedia' ),
	} );

	return 'versions' in response && Array.isArray( response.versions )
		? response.versions
		: [];
};
