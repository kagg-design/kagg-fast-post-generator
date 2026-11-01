<?php
/**
 * Release note regression tests.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for validating the behavior of the changelog generation functionality.
 */
class ChangelogTest extends TestCase {

	/**
	 * Verify changelog merging preserves history, sorts versions and remains idempotent.
	 *
	 * @return void
	 */
	public function test_merge_preserves_archive_and_sorts_legacy_versions(): void {
		$readme   = "== Changelog ==\r\n\r\n= 2.3.0 =\r\n* Current.\r\n\r\n= 2.2.0 =\r\n* Updated.\r\n\r\n= 2.0 =\r\n* Legacy.\r\n\r\n[See changelog for all versions](changelog.txt)\r\n\r\n== Upgrade Notice ==\r\nOther text.\r\n";
		$archive  = "= 2.2.0 =\n* Old.\n\n= 1.10.0 =\n* Archived.\n\n= 1.9.0 =\n* Older.\n";
		$expected = "= 2.3.0 =\n* Current.\n\n= 2.2.0 =\n* Updated.\n\n= 2.0 =\n* Legacy.\n\n= 1.10.0 =\n* Archived.\n\n= 1.9.0 =\n* Older.\n";

		self::assertSame( $expected, kagg_generate_changelog( $readme, $archive ) );
		self::assertSame( $expected, kagg_generate_changelog( $readme, $expected ), 'Merging twice must be idempotent.' );
	}

	/**
	 * Verify missing or empty release notes are rejected before replacing the archive.
	 *
	 * @dataProvider invalid_readmes
	 *
	 * @param string $readme  Invalid readme content supplied to the changelog generator.
	 * @param string $message Expected validation error message.
	 *
	 * @return void
	 */
	public function test_invalid_readme_cannot_erase_the_archive( string $readme, string $message ): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( $message );
		kagg_generate_changelog( $readme, "= 1.0 =\n* Archived.\n" );
	}

	/**
	 * Provide readmes without a changelog section or without release entries.
	 *
	 * @return array Named test cases containing invalid input and an expected error.
	 */
	public static function invalid_readmes(): array {
		return [
			'missing section' => [ 'No release notes.', 'No Changelog section found' ],
			'empty section'   => [ "== Changelog ==\n\n== Upgrade Notice ==\nText.", 'No release notes found' ],
		];
	}
}
