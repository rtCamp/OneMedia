<?php
/**
 * Bootstrap the PHPUnit tests.
 *
 * @package OneMedia\Tests
 *
 * phpcs:disable WordPressVIPMinimum.Files.IncludingFile.UsingVariable
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */

declare( strict_types = 1 );

define( 'TESTS_REPO_ROOT_DIR', dirname( __DIR__, 2 ) );

if ( file_exists( TESTS_REPO_ROOT_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_REPO_ROOT_DIR . '/vendor/autoload.php';
}

if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_REPO_ROOT_DIR . '/../../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_REPO_ROOT_DIR . '/../../../../../tests/phpunit';
} else {
	$_test_root = '/tmp/wordpress-tests-lib';
}

require_once $_test_root . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require TESTS_REPO_ROOT_DIR . '/onemedia.php';
	}
);

require $_test_root . '/includes/bootstrap.php';