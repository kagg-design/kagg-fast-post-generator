<?php
/**
 * Standard WordPress PHPUnit bootstrap, following the cyr2lat test layout.
 *
 * @package kagg/generator
 */

$project_root = dirname( __DIR__, 2 );

require_once $project_root . '/vendor/autoload.php';
require_once $project_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: $project_root . '/vendor/wp-phpunit/wp-phpunit';
$config    = getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ?: __DIR__ . '/wp-tests-config.php';

// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $config );

if ( ! is_file( $tests_dir . '/includes/functions.php' ) ) {
	throw new RuntimeException( 'WordPress PHPUnit is missing. Run composer install or set WP_TESTS_DIR.' );
}

// Also supports an external WordPress test suite, which does not use the Composer config wrapper.
define( 'WP_TESTS_CONFIG_FILE_PATH', $config );

const WP_ADMIN   = true;
const DOING_AJAX = true;

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/plugin.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
