<?php
/**
 * Generate the complete changelog from readme release notes and archived entries.
 *
 * @package kagg/generator
 */

/**
 * Parse release sections, including historical two-component versions.
 *
 * @param string $text Release notes.
 * @return array
 */
function kagg_changelog_entries( string $text ): array {
	$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
	preg_match_all( '/^= ([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[^\s=]+)?) =[ \t]*$\n(.*?)(?=^= [^=\n]+ =[ \t]*$|^\[See changelog for all versions\]|\z)/ms', $text, $matches, PREG_SET_ORDER );
	$entries = [];

	foreach ( $matches as $match ) {
		$entries[ $match[1] ] = trim( $match[2] );
	}

	return $entries;
}

/**
 * Merge all readme releases into the archive without duplicating versions.
 *
 * @param string $readme    Readme content.
 * @param string $changelog Archived content.
 * @return string
 * @throws RuntimeException When release notes are absent.
 */
function kagg_generate_changelog( string $readme, string $changelog ): string {
	$readme = str_replace( [ "\r\n", "\r" ], "\n", $readme );
	if ( ! preg_match( '/^== Changelog ==[ \t]*$\n(.*?)(?=^== [^=\n]+ ==[ \t]*$|\z)/ms', $readme, $section ) ) {
		throw new RuntimeException( 'No Changelog section found in readme.txt.' );
	}

	$entries = kagg_changelog_entries( $section[1] );
	if ( ! $entries ) {
		throw new RuntimeException( 'No release notes found in readme.txt.' );
	}

	$entries += kagg_changelog_entries( $changelog );
	uksort( $entries, static function ( string $left, string $right ): int {
		return version_compare( $right, $left );
	} );
	$output = '';
	foreach ( $entries as $version => $notes ) {
		$output .= '= ' . $version . " =\n" . $notes . "\n\n";
	}

	return rtrim( $output ) . "\n";
}

/**
 * Generate or check changelog.txt.
 *
 * @param string[] $arguments Command arguments.
 * @return int
 */
function kagg_update_changelog( array $arguments ): int {
	if ( array_diff( $arguments, [ '--check' ] ) ) {
		fwrite( STDERR, "Usage: php .github/scripts/update-changelog.php [--check]\n" );
		return 1;
	}

	$root      = dirname( __DIR__, 2 );
	$readme    = file_get_contents( $root . '/readme.txt' );
	$changelog = file_exists( $root . '/changelog.txt' ) ? file_get_contents( $root . '/changelog.txt' ) : '';

	try {
		if ( false === $readme || false === $changelog ) {
			throw new RuntimeException( 'Could not read the release notes.' );
		}
		$generated = kagg_generate_changelog( $readme, $changelog );
		if ( in_array( '--check', $arguments, true ) ) {
			if ( $generated !== str_replace( [ "\r\n", "\r" ], "\n", $changelog ) ) {
				throw new RuntimeException( 'changelog.txt is out of date. Run composer changelog.' );
			}
		} elseif ( false === file_put_contents( $root . '/changelog.txt', $generated ) ) {
			throw new RuntimeException( 'Could not write changelog.txt.' );
		}
	} catch ( RuntimeException $exception ) {
		fwrite( STDERR, $exception->getMessage() . "\n" );
		return 1;
	}

	echo "Changelog is up to date.\n";
	return 0;
}

if ( isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	exit( kagg_update_changelog( array_slice( $argv, 1 ) ) );
}
