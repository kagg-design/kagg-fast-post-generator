<?php
/**
 * SQLite import, export and transaction regression tests.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Tests\Integration;

use KAGG\Generator\Generator\Generator;
use KAGG\Generator\Generator\Post;
use PDO;
use ReflectionException;
use RuntimeException;

/**
 * SQLite import, export and transaction regression tests.
 *
 * Handles testing of SQLite-specific functionality, ensuring compatibility with WordPress SQLite Database Integration.
 * Provides test cases for database fixtures, import/export handling, special character preservation,
 * and transactional integrity during failures.
 */
class SQLiteTest extends GeneratorTestCase {
	/**
	 * Prepare isolated database fixtures and skip tests on non-SQLite installations.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! ( new Generator() )->is_sqlite() ) {
			self::markTestSkipped( 'Requires WordPress SQLite Database Integration.' );
		}
	}

	/**
	 * Verify SQLite imports never require the MySQL LOCAL INFILE setting.
	 *
	 * @return void
	 */
	public function test_sqlite_never_requests_mysql_local_infile(): void {
		self::assertFalse( ( new Generator() )->use_local_infile() );
	}

	/**
	 * Verify CSV imports and executable SQLite exports preserve special characters.
	 *
	 * @dataProvider export_modes
	 *
	 * @param bool $export Whether to execute a native SQL export instead of importing CSV.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function test_import_and_native_sql_export_preserve_difficult_text( bool $export ): void {
		global $wpdb;

		$text = "O'Reilly \"quoted\" | 100% \\path\\\nUnicode: Rīga 😀";

		/**
		 * Represents a generic item within the application. The specific details
		 * and type of the item can vary depending on the context in which it is used.
		 *
		 * This variable can be utilized to store or process information related to
		 * a single item instance in the application.
		 */
		$item = new class( $text ) extends Post {
			/**
			 * Text.
			 *
			 * @var string
			 */
			private string $text;

			/**
			 * Initialize the post generator with text containing import and export edge cases.
			 *
			 * @param string $text Fixture text to place in generated post content.
			 */
			public function __construct( string $text ) {
				$this->text = $text;
				parent::__construct();
			}

			/**
			 * Generate a post row containing the supplied text fixture.
			 *
			 * @return array Generated post fields with the fixture as post content.
			 */
			public function generate(): array {
				$row                 = parent::generate();
				$row['post_content'] = $this->text;

				return $row;
			}
		};

		$importer = $this->importer( $item, $export );
		$this->call_method( $importer, 'generate_items', [ 1, $this->file ] );

		if ( $export ) {
			$native = new PDO( 'sqlite::memory:', null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
			$fields = '`' . implode( '` TEXT, `', $item->get_fields() ) . '` TEXT';

			$native->exec( "CREATE TABLE `$wpdb->posts` ($fields)" );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$native->exec( file_get_contents( $this->file ) );

			self::assertSame( $text, $native->query( "SELECT post_content FROM `$wpdb->posts`" )->fetchColumn() );
		} else {
			$this->call_method( $importer, 'store_items', [ $this->file ] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::assertSame( $text, $wpdb->get_var( "SELECT post_content FROM $wpdb->posts ORDER BY ID DESC LIMIT 1" ) );
		}
	}

	/**
	 * Provide CSV import and native SQL export modes for text preservation tests.
	 *
	 * @return array Named test cases containing an export flag.
	 */
	public static function export_modes(): array {
		return [
			'CSV import'        => [ false ],
			'native SQL export' => [ true ],
		];
	}

	/**
	 * Verify failure of the second INSERT batch rolls back all rows in the chunk.
	 *
	 * @return void
	 * @throws ReflectionException
	 * @noinspection SqlResolve
	 */
	public function test_failed_second_batch_rolls_back_the_entire_chunk(): void {
		global $wpdb;

		$importer = $this->importer( new Post( 205 ) );

		$this->call_method( $importer, 'generate_items', [ 205, $this->file ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
		$inserts = 0;
		$fail    = static function ( string $query ) use ( &$inserts, $wpdb ): string {
			if ( 2 === ++$inserts && 0 === strpos( $query, "INSERT INTO `$wpdb->posts`" ) ) {
				return 'INSERT INTO kagg_missing_table VALUES (1)';
			}

			return $query;
		};

		add_filter( 'query', $fail );

		$previous = $wpdb->suppress_errors();

		try {
			$this->call_method( $importer, 'store_items', [ $this->file ] );
			self::fail( 'The second INSERT must fail.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'kagg_missing_table', $exception->getMessage() );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous );
		}

		self::assertSame( 2, $inserts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		self::assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ), 'No partial rows may survive a failed import.' );
	}
}
