<?php
/**
 * WordPress core test configuration. Never use a production database here.
 *
 * @package kagg/generator
 */

$params = require __DIR__ . '/_config/params.php';

define( 'ABSPATH', rtrim( $params['WP_ROOT_PATH'], '/\\' ) . '/' );

if ( ! is_file( ABSPATH . 'wp-settings.php' ) ) {
	throw new RuntimeException( 'WP_ROOT_PATH must point to downloaded WordPress core files.' );
}

foreach ( [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ] as $constant ) {
	define( $constant, $params[ $constant ] );
}

const DB_CHARSET      = 'utf8mb4';
const DB_COLLATE      = '';
const WP_TESTS_DOMAIN = 'example.org';
const WP_TESTS_EMAIL  = 'admin@example.org';
const WP_TESTS_TITLE  = 'KAGG Generator Tests';

define( 'WP_PHP_BINARY', $params['WP_PHP_BINARY'] ?? PHP_BINARY );

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$table_prefix = $params['DB_TABLE_PREFIX'];

if ( 'sqlite' === ( $params['DB_ENGINE'] ?? 'mysql' ) ) {
	define( 'DB_ENGINE', 'sqlite' );
	define( 'DB_PATH', $params['DB_PATH'] );
}
