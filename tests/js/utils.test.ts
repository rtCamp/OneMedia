/**
 * Internal dependencies
 */
import { removeTrailingSlash, trimTitle } from '../../assets/src/js/utils';

describe('utils', () => {
	it('removes trailing slashes from URLs', () => {
		expect(removeTrailingSlash('https://example.com///')).toBe(
			'https://example.com'
		);
	});

	it('trims long titles and keeps short ones intact', () => {
		expect(trimTitle('Short title', 20)).toBe('Short title');
		expect(trimTitle('This is a very long media title', 10)).toBe(
			'This is a ' + '…'
		);
	});
});
