/**
 * PHP consts for JS usage.
 *
 * @package
 */

let settings: {
	restUrl?: string;
	restNonce?: string;
	apiKey?: string;
	settingsLink?: string;
	siteType?: string;
	siteName?: string;
} = {};

if ( typeof window.OneMediaSettings !== 'undefined' ) {
	settings = window.OneMediaSettings;
} else if ( typeof window.OneMediaData !== 'undefined' ) {
	settings = window.OneMediaData;
}

const ONEMEDIA_REST_NAME = 'onemedia';
const ONEMEDIA_REST_VERSION = 'v1';

const API_NAMESPACE = settings?.restUrl ? settings.restUrl + `/${ ONEMEDIA_REST_NAME }/${ ONEMEDIA_REST_VERSION }` : '';
const NONCE = settings?.restNonce ? settings.restNonce : '';
const API_KEY = settings?.apiKey ? settings.apiKey : '';
const SITE_TYPE = settings?.siteType ? settings.siteType : '';
const SITE_NAME = settings?.siteName ? settings.siteName : '';

export {
	API_NAMESPACE,
	NONCE,
	API_KEY,
	SITE_TYPE,
	SITE_NAME,
};
