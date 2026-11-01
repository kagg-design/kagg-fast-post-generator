<?php
/**
 * Comment chronology regression checks, loaded by database.php on a disposable site.
 *
 * @package kagg/generator
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.WP.AlternativeFunctions -- Native temporary files and a repeatable random seed for tests.

require_once ABSPATH . 'wp-admin/includes/schema.php';

$original_prefix   = $wpdb->prefix;
$original_posts    = $wpdb->posts;
$original_comments = $wpdb->comments;
$schema            = wp_get_db_schema( 'blog' );
$test_prefix       = $original_prefix . 'kagg_dates_';
$create_tables     = [];
foreach ( [
	$original_posts    => $test_prefix . 'posts',
	$original_comments => $test_prefix . 'comments',
] as $original => $temporary ) {
	kagg_test_check( 1 === preg_match( '/CREATE TABLE ' . preg_quote( $original, '/' ) . '\\s*\\(.*?;/s', $schema, $matches ), 'Missing table schema.' );
	$create_tables[ $temporary ] = str_replace( 'CREATE TABLE ' . $original, 'CREATE TABLE ' . $temporary, $matches[0] );
}

// Keep the fixtures and imported rows away from the site's ordinary tables.
$wpdb->prefix    = $test_prefix;
$wpdb->posts     = $test_prefix . 'posts';
$wpdb->comments  = $test_prefix . 'comments';
$timezone        = 'UTC';
$timezone_filter = static function () use ( &$timezone ): string {
	return $timezone;
};
$small_pool      = static function (): int {
	return 2;
};
$short_text      = static function (): int {
	return 1;
};
add_filter( 'pre_option_timezone_string', $timezone_filter );
add_filter( 'kagg_generator_comment_random_posts_count', $small_pool );
add_filter( 'kagg_generator_comment_max_sentences', $short_text );
$date_file = tempnam( sys_get_temp_dir(), 'kagg-dates-' );

try {
	foreach ( $create_tables as $sql ) {
		kagg_test_check( false !== $wpdb->query( $sql ), $wpdb->last_error );
	}
	$now = time();
	foreach ( [ 'UTC', 'Asia/Kolkata', 'America/New_York' ] as $timezone ) {
		$wpdb->query( "DELETE FROM $wpdb->posts" );
		foreach ( [
			1 => $now - 2 * YEAR_IN_SECONDS,
			2 => $now - 30 * DAY_IN_SECONDS,
			3 => $now + YEAR_IN_SECONDS,
		] as $fixture_id => $timestamp ) {
			kagg_test_check(
				false !== $wpdb->insert(
					$wpdb->posts,
					[
						'ID'                    => $fixture_id,
						'post_date'             => wp_date( 'Y-m-d H:i:s', $timestamp ),
						'post_date_gmt'         => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'post_type'             => 'post',
						'post_status'           => 3 === $fixture_id ? 'future' : 'publish',
						'post_content'          => '',
						'post_title'            => 'Chronology fixture',
						'post_excerpt'          => '',
						'to_ping'               => '',
						'pinged'                => '',
						'post_content_filtered' => '',
					]
				),
				$wpdb->last_error
			);
		}

		foreach ( [ false, true ] as $export ) {
			foreach ( [ 1, 17, 43 ] as $chunk_size ) {
				$wpdb->query( "DROP TABLE $wpdb->comments" );
				kagg_test_check( false !== $wpdb->query( $create_tables[ $wpdb->comments ] ), $wpdb->last_error );
				$previous     = '';
				$previous_sql = '';
				$parents      = [];
				$total        = 43;
				mt_srand( 15 );
				for ( $index = 0; $index < $total; $index += $chunk_size ) {
					$base_id  = (int) $wpdb->get_var( "SELECT MAX(comment_ID) FROM $wpdb->comments" );
					$item     = new KAGG\Generator\Generator\Comment( $total, $index );
					$count    = min( $chunk_size, $total - $index );
					$importer = new KAGG\Generator\Generator\Generator();
					kagg_test_set( $importer, 'item_handler', $item );
					kagg_test_set( $importer, 'download_sql', false );
					kagg_test_set( $importer, 'use_local_infile', $importer->use_local_infile() );
					$handle = fopen( $date_file, 'wb' );
					for ( $offset = 0; $offset < $count; ++$offset ) {
						$row = $item->generate();
						kagg_test_check( $row['comment_date_gmt'] >= $previous, 'Comment dates went backwards between IDs or chunks.' );
						kagg_test_check( $row['comment_date_gmt'] <= gmdate( 'Y-m-d H:i:s' ), 'A comment is in the future.' );
						kagg_test_check( wp_date( 'Y-m-d H:i:s', strtotime( $row['comment_date_gmt'] . ' UTC' ) ) === $row['comment_date'], 'Local and GMT comment dates disagree.' );
						kagg_test_check( 3 !== (int) $row['comment_post_ID'], 'A scheduled post received a comment.' );
						$post_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date_gmt FROM $wpdb->posts WHERE ID = %d", $row['comment_post_ID'] ) );
						kagg_test_check( $row['comment_date_gmt'] >= $post_date, 'Comment predates its post.' );
						if ( ! $export && $row['comment_parent'] ) {
							$parent = $parents[ $row['comment_parent'] ] ?? null;
							kagg_test_check( $parent && $parent['comment_post_ID'] === $row['comment_post_ID'] && $parent['comment_date_gmt'] <= $row['comment_date_gmt'], 'Invalid reply chronology or post.' );
						}
						$parents[ $base_id + $offset + 1 ] = $row;
						$previous                          = $row['comment_date_gmt'];
						fputcsv( $handle, $row, '|', '"', $importer->is_sqlite() ? '' : '\\' );
					}
					fclose( $handle );
					if ( ! $export ) {
						kagg_test_call( $importer, 'store_items', [ $date_file ] );
					} else {
						kagg_test_set( $importer, 'item_handler', new KAGG\Generator\Generator\Comment( $total, $index ) );
						kagg_test_set( $importer, 'download_sql', true );
						kagg_test_call( $importer, 'generate_items', [ $count, $date_file ] );
						$sql = file_get_contents( $date_file );
						preg_match_all( "/'(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})','(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})'/", $sql, $dates );
						kagg_test_check( count( $dates[2] ) === $count, 'Incorrect SQL export row count.' );
						foreach ( $dates[2] as $date ) {
							kagg_test_check( $date >= $previous_sql, 'SQL export dates went backwards between IDs or chunks.' );
							$previous_sql = $date;
						}
					}
				}
				if ( ! $export ) {
					$stored = $wpdb->get_results( "SELECT comment_date_gmt FROM $wpdb->comments ORDER BY comment_ID" );
					kagg_test_check( count( $stored ) === $total, 'Incorrect imported comment count.' );
					$previous = '';
					foreach ( $stored as $row ) {
						kagg_test_check( $row->comment_date_gmt >= $previous, 'Stored comment dates disagree with ID order.' );
						$previous = $row->comment_date_gmt;
					}
				}
			}
		}
	}

	// A new generation must continue after the last stored comment.
	$last_date = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	$wpdb->insert(
		$wpdb->comments,
		[
			'comment_date'     => get_date_from_gmt( $last_date ),
			'comment_date_gmt' => $last_date,
			'comment_content'  => '',
			'comment_agent'    => '',
		]
	);
	$row = ( new KAGG\Generator\Generator\Comment( 100 ) )->generate();
	kagg_test_check( $row['comment_date_gmt'] >= $last_date, 'A new generation predates the last stored comment.' );

	// Missing GMT dates must use the site timezone; empty sites still get real dates.
	$wpdb->query( "DELETE FROM $wpdb->posts WHERE ID != 1" );
	$wpdb->query( "UPDATE $wpdb->posts SET post_date_gmt = '0000-00-00 00:00:00'" );
	$row = ( new KAGG\Generator\Generator\Comment() )->generate();
	kagg_test_check( 1 === (int) $row['comment_post_ID'] && '0000-00-00 00:00:00' !== $row['comment_date_gmt'], 'Missing GMT date was not handled.' );
	$wpdb->query( "DELETE FROM $wpdb->posts" );
	$row = ( new KAGG\Generator\Generator\Comment() )->generate();
	kagg_test_check( 0 === (int) $row['comment_post_ID'] && '0000-00-00 00:00:00' !== $row['comment_date_gmt'], 'Empty-site comment has an invalid date or post.' );
} finally {
	remove_filter( 'pre_option_timezone_string', $timezone_filter );
	remove_filter( 'kagg_generator_comment_random_posts_count', $small_pool );
	remove_filter( 'kagg_generator_comment_max_sentences', $short_text );
	foreach ( array_keys( $create_tables ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}
	$wpdb->prefix   = $original_prefix;
	$wpdb->posts    = $original_posts;
	$wpdb->comments = $original_comments;
	unlink( $date_file );
}

echo "Comment chronology checks passed (three timezones, three chunk sizes, import and SQL export timelines).\n";
