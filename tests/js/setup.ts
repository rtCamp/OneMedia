/**
 * External dependencies
 */
import '@testing-library/jest-dom';

const fetchMock = jest.fn<ReturnType<typeof fetch>, Parameters<typeof fetch>>();

Object.defineProperty(global, 'fetch', {
	value: fetchMock,
	writable: true,
});

Object.defineProperty(window, 'OneMediaSettings', {
	value: {
		restUrl: 'https://example.com/wp-json',
		restNonce: 'nonce',
		api_key: 'api-key',
		settingsLink: '/wp-admin/admin.php?page=onemedia-settings',
		siteType: 'governing-site',
		restNamespace: 'onemedia/v1',
		nonce: 'nonce',
		currentSiteUrl: 'https://governing.example.com/',
	},
	writable: true,
});

Object.defineProperty(window, 'OneMediaOnboarding', {
	value: {
		nonce: 'onboarding-nonce',
		site_type: '',
		setup_url: '/wp-admin/admin.php?page=onemedia-settings',
	},
	writable: true,
});

Object.defineProperty(window, 'OneMediaMediaFrame', {
	value: {
		restUrl: 'https://example.com/wp-json',
		restNonce: 'nonce',
		apiKey: 'api-key',
		ajaxUrl: '/wp-admin/admin-ajax.php',
		siteType: 'governing-site',
		uploadNonce: 'upload-nonce',
		allowedMimeTypesMap: {
			'image/jpeg': 'jpg',
			'image/png': 'png',
		},
	},
	writable: true,
});

Object.defineProperty(window, 'OneMediaMediaSharing', {
	value: {
		uploadNonce: 'upload-nonce',
	},
	writable: true,
});

Object.defineProperty(global, 'OneMediaMediaUpload', {
	value: {
		isMediaPage: true,
	},
	writable: true,
});

Object.defineProperty(navigator, 'clipboard', {
	value: {
		writeText: jest.fn().mockResolvedValue(undefined),
	},
	configurable: true,
});

beforeEach(() => {
	jest.clearAllMocks();
	fetchMock.mockReset();
});
