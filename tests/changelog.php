<?php
/**
 * Regression checks for merging release notes into the archive.
 *
 * @package kagg/generator
 */

require dirname( __DIR__ ) . '/.github/scripts/update-changelog.php';

$readme   = "== Changelog ==\r\n\r\n= 2.3.0 =\r\n* Current.\r\n\r\n= 2.2.0 =\r\n* Updated.\r\n\r\n= 2.0 =\r\n* Legacy.\r\n\r\n[See changelog for all versions](changelog.txt)\r\n\r\n== Upgrade Notice ==\r\nOther text.\r\n";
$archive  = "= 2.2.0 =\n* Old.\n\n= 1.10.0 =\n* Archived.\n\n= 1.9.0 =\n* Older.\n";
$expected = "= 2.3.0 =\n* Current.\n\n= 2.2.0 =\n* Updated.\n\n= 2.0 =\n* Legacy.\n\n= 1.10.0 =\n* Archived.\n\n= 1.9.0 =\n* Older.\n";
$actual   = kagg_generate_changelog( $readme, $archive );

if ( $expected !== $actual || $actual !== kagg_generate_changelog( $readme, $actual ) ) {
	fwrite( STDERR, "Changelog merge, ordering, legacy version or idempotence failed.\n" );
	exit( 1 );
}

try {
	kagg_generate_changelog( 'No release notes.', $archive );
	fwrite( STDERR, "Missing release notes were accepted.\n" );
	exit( 1 );
} catch ( RuntimeException $exception ) {
	// Expected: an invalid readme must never erase the archive.
}

echo "Changelog regression checks passed.\n";
