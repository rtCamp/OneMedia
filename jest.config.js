/**
 * Jest configuration for OneMedia.
 */

/**
 * WordPress dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/jest-unit.config');

module.exports = {
	...defaultConfig,
	displayName: 'onemedia',
	rootDir: '.',
	roots: ['<rootDir>', '<rootDir>/tests/js'],
	setupFilesAfterEnv: [
		...(defaultConfig.setupFilesAfterEnv || []),
		'<rootDir>/tests/js/setup.ts',
	],
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@/(.*)$': '<rootDir>/assets/src/$1',
	},
	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
		'/inc/',
		'/vendor/',
		'/tests/e2e/',
		'/tests/php/',
	],
	testMatch: [
		'**/__tests__/**/*.{js,jsx,ts,tsx}',
		'**/*.{test,spec}.{js,jsx,ts,tsx}',
	],
	collectCoverageFrom: [
		'assets/src/**/*.{js,jsx,ts,tsx}',
		'!assets/src/**/*.d.ts',
		'!assets/src/**/index.{js,jsx,ts,tsx}',
		'!assets/src/**/*.{css,scss}',
	],
	coverageDirectory: 'tests/_output/js-coverage',
	coverageThreshold: {
		global: {
			branches: 0,
			functions: 0,
			lines: 0,
			statements: 0,
		},
	},
	coverageReporters: ['text', 'text-summary', 'lcov', 'html'],
	verbose: process.env.CI === 'true',
	testTimeout: 10000,
	watchPlugins: [
		'jest-watch-typeahead/filename',
		'jest-watch-typeahead/testname',
	],
};
