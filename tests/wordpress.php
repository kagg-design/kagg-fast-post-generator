<?php
/**
 * WordPress smoke check: load the plugin and generate rows without storing them.
 *
 * Run with wp --skip-plugins --skip-themes eval-file tests/wordpress.php.
 * The site needs at least one user and one published post.
 *
 * @package kagg/generator
 */

define( 'WP_ADMIN', true );
require dirname( __DIR__ ) . '/plugin.php';

if ( ! has_action( 'wp_ajax_' . KAGG\Generator\Settings::GENERATE_ACTION ) ) {
	throw new RuntimeException( 'The admin generation hook was not registered.' );
}

$generator = new KAGG\Generator\Generator\Generator();
foreach ( $generator->get_registered_items() as $type => $classname ) {
	$item = new $classname( 5 );
	for ( $index = 0; $index < 5; ++$index ) {
		$row = $item->generate();
		if ( array_keys( $row ) !== $item->get_fields() || $type !== $item->get_type() ) {
			throw new RuntimeException( 'Invalid generated row for ' . $type );
		}
		if ( isset( $row['post_type'] ) && $type !== $row['post_type'] ) {
			throw new RuntimeException( 'Incorrect generated post type.' );
		}
		if ( false === strpos( $row[ $item->get_marker_field() ], KAGG\Generator\Settings::MARKER ) ) {
			throw new RuntimeException( 'Missing generated item marker.' );
		}
	}
	echo 'Generated five ' . $type . " rows.\n";
}

echo 'WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ": smoke checks passed.\n";
