<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package SyncedPatternsForThemes
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = __DIR__ . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find the WordPress test suite at {$_tests_dir}." . PHP_EOL;
	echo 'Run `composer install`, or set WP_TESTS_DIR.' . PHP_EOL;
	exit( 1 );
}

// Forward custom PHPUnit Polyfills configuration to the PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );

if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

require_once __DIR__ . '/vendor/autoload.php';

// Gives access to tests_add_filter().
require_once "{$_tests_dir}/includes/functions.php";

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require __DIR__ . '/synced-patterns-for-themes.php';
	}
);

require "{$_tests_dir}/includes/bootstrap.php";

// Depends on WP_UnitTestCase, so it can only be loaded once the suite has booted.
require_once __DIR__ . '/tests/class-pattern-test-case.php';
