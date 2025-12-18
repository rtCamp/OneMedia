/**
 * Utility functions
 */

import type { NoticeType } from '../admin/settings/page';

/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param {string} str - The string to validate as a URL.
 *
 * @return {boolean} True if the string is a valid URL, false otherwise.
 */
const isURL = ( str: string ): boolean => {
	try {
		new URL( str );
		return true;
	} catch {
		return false;
	}
};

/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
const isValidUrl = ( url: string ): boolean => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

/**
 * Removes trailing slashes from a URL.
 *
 * @param {string} url - The URL to process.
 * @return {string} The URL without trailing slashes.
 */
const removeTrailingSlash = ( url: string ): string => url.replace( /\/+$/, '' );

/**
 * Returns the appropriate CSS class for a notice based on its type.
 *
 * @param {string} type - The type of notice ('error', 'warning', 'success').
 * @return {string} The corresponding CSS class.
 */
const getNoticeClass = ( type: NoticeType['type'] ): string => {
	if ( type === 'error' ) {
		return 'onemedia-error-notice';
	}
	if ( type === 'warning' ) {
		return 'onemedia-warning-notice';
	}
	return 'onemedia-success-notice';
};

/**
 * Trims a title to a specified maximum length, adding an ellipsis if trimmed.
 *
 * @param {string} title     - The title to trim.
 * @param {number} maxLength - The maximum length of the title (default is 25).
 * @return {string} The trimmed title.
 */
const trimTitle = ( title: string, maxLength: number = 25 ): string => {
	if ( typeof title !== 'string' ) {
		return '';
	}
	return title.length > maxLength
		? title.substring( 0, maxLength ) + '…'
		: title;
};

/**
 * Debounced function that delays invoking the provided function until after
 * the specified wait time has elapsed since the last time it was invoked.
 *
 * @param {Function} func - The function to debounce.
 * @param {number}   wait - The number of milliseconds to delay.
 * @return {Function}        - The debounced function.
 */
const debounce = <T extends ( ...args: any[] ) => any>(
	func: T,
	wait: number,
): ( ( ...args: Parameters<T> ) => void ) => {
	let timeout: ReturnType<typeof setTimeout> | undefined;
	return function executedFunction( ...args: Parameters<T> ) {
		const later = () => {
			clearTimeout( timeout );
			func( ...args );
		};
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
	};
};

/**
 * Observe for elements matching selector and run callback when found.
 *
 * @param {string}   selector      - CSS selector to observe for.
 * @param {Function} onFound       - Callback when element is found.
 * @param {number}   debounceDelay - Time to wait after last mutation before firing (default 200ms).
 * @return {MutationObserver} The MutationObserver instance.
 */
const observeElement = (
	selector: string,
	onFound: ( elements: NodeListOf<Element> ) => void,
	debounceDelay: number = 200,
): MutationObserver => {
	const debouncedOnFound = debounce( () => {
		const elements = document.querySelectorAll( selector );
		if ( elements.length > 0 ) {
			onFound( elements );
		}
	}, debounceDelay );

	const observer = new MutationObserver( () => {
		debouncedOnFound();
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );

	// Run once in case elements already exist.
	const existing = document.querySelectorAll( selector );
	if ( existing.length > 0 ) {
		onFound( existing );
	}

	return observer;
};

/**
 * Retrieves a nested property from the window object based on a dot-separated path.
 *
 * @param {string} propertyPath - Dot-separated path to the property, e.g. 'wp.media.view.AttachmentDetails'
 * @return {*} The value of the nested property, or undefined if not found.
 */
const getFrameProperty = ( propertyPath: string ): any => {
	if ( typeof propertyPath !== 'string' || ! propertyPath ) {
		return undefined;
	}

	try {
		// Split the path by dots and reduce to get the nested property.
		return propertyPath.split( '.' ).reduce( ( obj, key ) => obj?.[ key ], window as any );
	} catch ( error ) {
		return undefined;
	}
};

/**
 * Retrieves the title of the current wp.media frame.
 *
 * @return {string} The title of the media frame, or an empty string if not found.
 */
const getFrameTitle = (): string => {
	const frameTitleProperty = getFrameProperty( 'wp.media.frame' );

	if ( ! frameTitleProperty || typeof frameTitleProperty?.state !== 'function' ) {
		return '';
	}

	const frameTitle = frameTitleProperty?.state()?.get( 'title' );

	return typeof frameTitle === 'string' ? frameTitle : '';
};

/**
 * Show a snackbar notice with the specified type and message.
 *
 * @param {NoticeType} detail - The detail object containing type and message.
 */
const showSnackbarNotice = ( detail: NoticeType ): void => {
	if ( ! detail || typeof detail !== 'object' ) {
		return;
	}

	const type = detail?.type || 'error';
	const message = detail?.message || '';

	if ( ! message ) {
		return;
	}

	const event = new CustomEvent( 'onemediaNotice', {
		detail: {
			type,
			message,
		},
	} );
	document.dispatchEvent( event );
};

export {
	isURL,
	isValidUrl,
	removeTrailingSlash,
	getNoticeClass,
	trimTitle,
	debounce,
	observeElement,
	getFrameTitle,
	getFrameProperty,
	showSnackbarNotice,
};
