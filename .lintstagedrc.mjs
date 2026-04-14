/**
 * @type {import('lint-staged').Configuration}
 */
export default {
	'**/*.{js,jsx,ts,tsx}': [ 'wp-scripts lint-js --fix' ],
	'**/*.{css,scss}': [ 'wp-scripts lint-style --allow-empty-input --fix' ],
	'**/*.php': ( filenames ) => {
		const cwd = process.cwd();
		const relativeFilenames = filenames
			.map( ( filename ) => `"${ filename.replace( cwd + '/', '' ) }"` )
			.join( ' ' );

		return [
			`sh -c "./vendor/bin/phpcbf ${ relativeFilenames } || [ \$? -eq 3 ]"`,
		];
	},
	'**/*.{json,md,css,scss,js,jsx,ts,tsx}': [ 'wp-scripts format --' ],
};