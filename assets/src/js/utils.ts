/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param {string} str - The string to validate as a URL.
 *
 * @return {boolean} True if the string is a valid URL, false otherwise.
 */
const isURL = ( str:string ) => {
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
const isValidUrl = ( url:string ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

/**
 * Formats a date string into a more readable format.
 *
 * @param {string} dateString The date string to format.
 *
 * @return {string} The formatted date string.
 */
const formatDate = ( dateString:string ) => {
	return new Date( dateString ).toLocaleString( 'en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
		hour12: true,
	} );
};

export {
	isURL,
	isValidUrl,
	formatDate,
};
