<?php
/**
 * Comment chronology across chunk boundaries, timezones, imports and SQL export.
 *
 * @package kagg/generator
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection SqlWithoutWhere */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

namespace KAGG\Generator\Tests\Integration;

use KAGG\Generator\Generator\Comment;
use ReflectionException;

/**
 * Class CommentDatesTest
 *
 * Provides test cases for validating the behavior of comment date generation and chronological ordering.
 * This class extends GeneratorTestCase.
 */
class CommentDatesTest extends GeneratorTestCase {
	/**
	 * Verify comment chronology across chunks, timezones, imports and SQL exports.
	 *
	 * @dataProvider timelines
	 *
	 * @param string $timezone   WordPress timezone used to generate local dates.
	 * @param int    $chunk_size Number of comments generated in each chunk.
	 * @param bool   $export     Whether to check SQL exports instead of imported rows.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function test_comment_dates_follow_id_order( string $timezone, int $chunk_size, bool $export ): void {
		global $wpdb;

		add_filter(
			'pre_option_timezone_string',
			static function () use ( $timezone ): string {
				return $timezone;
			}
		);

		add_filter(
			'kagg_generator_comment_random_posts_count',
			static function (): int {
				return 2;
			}
		);

		add_filter(
			'kagg_generator_comment_max_sentences',
			static function (): int {
				return 1;
			}
		);

		$wpdb->query( "DELETE FROM $wpdb->posts" );
		$this->insert_post( 1, time() - 2 * YEAR_IN_SECONDS );
		$this->insert_post( 2, time() - 30 * DAY_IN_SECONDS );
		$this->insert_post( 3, time() + YEAR_IN_SECONDS, 'future' );

		$previous     = '';
		$previous_sql = '';
		$parents      = [];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
		mt_srand( 15 );

		for ( $index = 0; $index < 43; $index += $chunk_size ) {
			$base_id  = (int) $wpdb->get_var( "SELECT MAX(comment_ID) FROM $wpdb->comments" );
			$item     = new Comment( 43, $index );
			$count    = min( $chunk_size, 43 - $index );
			$importer = $this->importer( $item );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$handle = fopen( $this->file, 'wb' );

			try {
				for ( $offset = 0; $offset < $count; ++$offset ) {
					$row = $item->generate();
					self::assertGreaterThanOrEqual( $previous, $row['comment_date_gmt'], 'Dates went backwards between IDs or chunks.' );
					self::assertLessThanOrEqual( gmdate( 'Y-m-d H:i:s' ), $row['comment_date_gmt'], 'A comment is in the future.' );
					self::assertSame( wp_date( 'Y-m-d H:i:s', strtotime( $row['comment_date_gmt'] . ' UTC' ) ), $row['comment_date'] );
					self::assertNotSame( 3, (int) $row['comment_post_ID'], 'Scheduled posts must not receive comments.' );
					$post_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date_gmt FROM $wpdb->posts WHERE ID = %d", $row['comment_post_ID'] ) );
					self::assertNotNull( $post_date );
					self::assertGreaterThanOrEqual( $post_date, $row['comment_date_gmt'], 'A comment predates its post.' );
					if ( ! $export && $row['comment_parent'] ) {
						self::assertArrayHasKey( $row['comment_parent'], $parents );
						$parent = $parents[ $row['comment_parent'] ];
						self::assertSame( $parent['comment_post_ID'], $row['comment_post_ID'] );
						self::assertGreaterThanOrEqual( $parent['comment_date_gmt'], $row['comment_date_gmt'] );
					}
					$parents[ $base_id + $offset + 1 ] = $row;
					$previous                          = $row['comment_date_gmt'];
					self::assertNotFalse( fputcsv( $handle, $row, '|', '"', $importer->is_sqlite() ? '' : '\\' ) );
				}
			} finally {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $handle );
			}

			if ( ! $export ) {
				$this->call_method( $importer, 'store_items', [ $this->file ] );
			} else {
				$importer = $this->importer( new Comment( 43, $index ), true );

				$this->call_method( $importer, 'generate_items', [ $count, $this->file ] );

				preg_match_all(
					"/'(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})','(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})'/",
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					file_get_contents( $this->file ),
					$dates
				);

				self::assertCount( $count, $dates[2] );

				foreach ( $dates[2] as $date ) {
					self::assertGreaterThanOrEqual( $previous_sql, $date, 'SQL export dates went backwards.' );
					$previous_sql = $date;
				}
			}
		}

		if ( ! $export ) {
			$stored = $wpdb->get_col( "SELECT comment_date_gmt FROM $wpdb->comments ORDER BY comment_ID" );

			self::assertCount( 43, $stored );

			$sorted = $stored;

			sort( $sorted );
			self::assertSame( $sorted, $stored );
		}
	}

	/**
	 * Provide timezone, chunk size and output mode combinations for chronology tests.
	 *
	 * @return array Named test cases containing a timezone, chunk size and export flag.
	 */
	public static function timelines(): array {
		$cases = [];

		foreach ( [ 'UTC', 'Asia/Kolkata', 'America/New_York' ] as $timezone ) {
			foreach ( [ 1, 17, 43 ] as $chunk_size ) {
				foreach ( [ false, true ] as $export ) {
					$cases[ $timezone . ', chunk ' . $chunk_size . ( $export ? ', SQL' : ', import' ) ] = [
						$timezone,
						$chunk_size,
						$export,
					];
				}
			}
		}

		return $cases;
	}

	/**
	 * Verify a new generation starts no earlier than the latest stored comment.
	 *
	 * @return void
	 */
	public function test_new_generation_continues_after_the_last_stored_comment(): void {
		global $wpdb;

		$last_date = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		self::assertNotFalse(
			$wpdb->insert(
				$wpdb->comments,
				[
					'comment_date'     => get_date_from_gmt( $last_date ),
					'comment_date_gmt' => $last_date,
					'comment_content'  => '',
					'comment_agent'    => '',
				]
			)
		);

		$row = ( new Comment( 100 ) )->generate();

		self::assertGreaterThanOrEqual( $last_date, $row['comment_date_gmt'] );
	}

	/**
	 * Verify missing GMT post dates are interpreted using the WordPress timezone.
	 *
	 * @return void
	 */
	public function test_missing_post_gmt_date_uses_site_timezone(): void {
		global $wpdb;

		add_filter(
			'pre_option_timezone_string',
			static function (): string {
				return 'Asia/Kolkata';
			}
		);

		$wpdb->query( "UPDATE $wpdb->posts SET post_date_gmt = '0000-00-00 00:00:00'" );

		$row = ( new Comment() )->generate();

		self::assertSame( 1, (int) $row['comment_post_ID'] );
		self::assertNotSame( '0000-00-00 00:00:00', $row['comment_date_gmt'] );
		self::assertSame( get_date_from_gmt( $row['comment_date_gmt'] ), $row['comment_date'] );
	}

	/**
	 * Verify an empty site produces a valid comment date without a post reference.
	 *
	 * @return void
	 */
	public function test_empty_site_still_generates_a_real_comment_date(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->posts" );
		$row = ( new Comment() )->generate();
		self::assertSame( 0, (int) $row['comment_post_ID'] );
		self::assertNotSame( '0000-00-00 00:00:00', $row['comment_date_gmt'] );
	}
}
