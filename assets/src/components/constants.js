let settings = {};
if ( typeof oneMediaSettings !== 'undefined' ) {
	settings = oneMediaSettings;
} else if ( typeof oneMediaSetupSettings !== 'undefined' ) {
	settings = oneMediaSetupSettings;
} else if ( typeof oneMediaMediaSharing !== 'undefined' ) {
	settings = oneMediaMediaSharing;
} else if ( typeof oneMediaMediaFrame !== 'undefined' ) {
	settings = oneMediaMediaFrame;
}

export const REST_URL = settings.restUrl;
export const NONCE = settings.nonce;
export const API_KEY = settings.apiKey;

export const SETUP_URL = typeof oneMediaSetupSettings !== 'undefined'
	? oneMediaSetupSettings.setupUrl
	: '';

export const ONEMEDIA_MEDIA_SHARING = typeof oneMediaMediaSharing !== 'undefined'
	? oneMediaMediaSharing
	: null;
export const UPLOAD_NONCE = typeof ONEMEDIA_MEDIA_SHARING?.uploadNonce !== 'undefined'
	? ONEMEDIA_MEDIA_SHARING?.uploadNonce
	: '';
export const SHARING_AJAX_URL = typeof ONEMEDIA_MEDIA_SHARING?.ajaxUrl !== 'undefined'
	? ONEMEDIA_MEDIA_SHARING?.ajaxUrl
	: '';

export const ALLOWED_MIME_TYPES = typeof ONEMEDIA_MEDIA_SHARING?.allowedMimeTypes !== 'undefined'
	? Object.values( ONEMEDIA_MEDIA_SHARING?.allowedMimeTypes )
	: [];

export const ONEMEDIA_REST_API_NAMESPACE = 'onemedia';
export const ONEMEDIA_REST_API_VERSION = 'v1';
export const ONEMEDIA_REST_API_BASE = '/wp-json/' + ONEMEDIA_REST_API_NAMESPACE + '/' + ONEMEDIA_REST_API_VERSION;
export const ONEMEDIA_PLUGIN_GOVERNING_SITE = 'governing';
export const ONEMEDIA_PLUGIN_BRAND_SITE = 'brand';
export const ONEMEDIA_PLUGIN_TAXONOMY_TERM = 'onemedia';
export const INITIAL_FORM_STATE = { siteName: '', siteUrl: '', apiKey: '' };
export const MEDIA_PER_PAGE = 12;
