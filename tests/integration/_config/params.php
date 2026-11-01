<?php
/**
 * Load local or environment-driven integration test parameters.
 *
 * @package kagg/generator
 */

$config = getenv( 'KAGG_TEST_PARAMS' );

if ( $config ) {
	if ( ! is_file( $config ) ) {
		throw new RuntimeException( 'KAGG_TEST_PARAMS does not point to a readable configuration file.' );
	}

	return require $config;
}

if ( is_file( __DIR__ . '/params.local.php' ) ) {
	return require __DIR__ . '/params.local.php';
}

if ( ! getenv( 'KAGG_TEST_WORDPRESS_DIR' ) ) {
	throw new RuntimeException( 'Set KAGG_TEST_WORDPRESS_DIR or copy tests/integration/_config/params.example.php to params.local.php.' );
}

return [
	'WP_ROOT_PATH'    => getenv( 'KAGG_TEST_WORDPRESS_DIR' ),
	'DB_HOST'         => getenv( 'KAGG_TEST_DB_HOST' ) ?: '127.0.0.1',
	'DB_NAME'         => getenv( 'KAGG_TEST_DB_NAME' ) ?: 'kagg_generator_tests',
	'DB_USER'         => getenv( 'KAGG_TEST_DB_USER' ) ?: 'root',
	'DB_PASSWORD'     => getenv( 'KAGG_TEST_DB_PASSWORD' ) ?: '',
	'DB_TABLE_PREFIX' => 'kaggtests_',
	'DB_ENGINE'       => getenv( 'KAGG_TEST_DB_ENGINE' ) ?: 'mysql',
	'DB_PATH'         => getenv( 'KAGG_TEST_SQLITE_PATH' ) ?: '',
];
