<?php
/**
 * Comment class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Lorem;
use KAGG\Generator\Randomizer;
use KAGG\Generator\Settings;

/**
 * Class Comment.
 */
class Comment extends Item {

	/**
	 * Maximum random posts count. Newly generated comments in a chunk will be distributed among them.
	 *
	 * @var int
	 */
	protected int $random_posts_count;

	/**
	 * Random IPs count. Newly generated comments will have a random IP from this set.
	 *
	 * @var int
	 */
	protected int $random_ips_count;

	/**
	 * Max nesting level number, starting from 0.
	 *
	 * @var int
	 */
	protected int $max_nesting_level;

	/**
	 * Percentage of nested comments comparing to previous level. Must be from 0 to 100.
	 *
	 * @var int
	 */
	protected int $nesting_percentage;

	/**
	 * Max sentences in comment.
	 *
	 * @var int
	 */
	protected int $max_sentences;

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected string $item_type = 'comment';

	/**
	 * Item DB table name without a prefix.
	 *
	 * @var string
	 */
	protected string $table = 'comments';

	/**
	 * Item DB table field name containing added items' marker.
	 *
	 * @var string
	 */
	protected string $marker_field = 'comment_author_url';

	/**
	 * Posts ordered by publication time, including the oldest available post.
	 *
	 * @var object[]
	 */
	private array $posts;

	/**
	 * Number of posts available at the current comment time.
	 *
	 * @var int
	 */
	private int $available_posts = 0;

	/**
	 * UTC timestamp of the previous comment.
	 *
	 * @var int
	 */
	private int $comment_time;

	/**
	 * Start of this chunk's time window.
	 *
	 * @var int
	 */
	private int $chunk_start;

	/**
	 * Randomizer class instance for users.
	 *
	 * @var Randomizer
	 */
	private Randomizer $user_randomizer;

	/**
	 * Randomizer class instance for logged-out users.
	 *
	 * @var Randomizer
	 */
	private Randomizer $logged_out_user_randomizer;

	/**
	 * Randomizer class instance for IPs.
	 *
	 * @var Randomizer
	 */
	private Randomizer $ip_randomizer;

	/**
	 * Current comment id.
	 *
	 * @var int
	 */
	private int $comment_ID;

	/**
	 * Post comments stub.
	 *
	 * @var array
	 */
	private array $post_comments_stub;

	/**
	 * Nesting level probabilities.
	 *
	 * @var array|int
	 */
	private $nesting_probabilities;

	/**
	 * Prepare post's stub.
	 *
	 * @return void
	 */
	protected function prepare_stub(): void {
		$this->random_posts_count = max(
			1,
			(int) apply_filters( 'kagg_generator_comment_random_posts_count', 1000 )
		);

		$this->random_ips_count = max(
			1,
			(int) apply_filters( 'kagg_generator_comment_random_ips_count', 1000 )
		);

		$this->max_nesting_level = max(
			0,
			(int) apply_filters( 'kagg_generator_comment_max_nesting_level', 2 )
		);

		$this->nesting_percentage = max(
			0,
			(int) apply_filters( 'kagg_generator_comment_nesting_percentage', 50 )
		);
		$this->nesting_percentage = min( 100, $this->nesting_percentage );

		$this->max_sentences = max(
			1,
			(int) apply_filters( 'kagg_generator_comment_max_sentences', 30 )
		);

		$user       = wp_get_current_user();
		$user_id    = $user->ID ?? 0;
		$user_name  = $user->display_name ?? '';
		$user_email = $user->user_email ?? '';
		$user_login = $user->user_login ?? '';

		$now      = time();
		$wp_date  = $this->wp_date( self::MYSQL_TIME_FORMAT, $now );
		$gmt_date = $this->gmt_date( self::MYSQL_TIME_FORMAT, $now );

		// Here we have to list the fields in the same order as in the wp_comments table.
		// Otherwise, csv file won't be created properly.
		$this->stub = [
			'comment_post_ID'      => 0,
			'comment_author'       => $user_name,
			'comment_author_email' => $user_email,
			'comment_author_url'   => Settings::MARKER . $user_login,
			'comment_author_IP'    => '127.0.0.1',
			'comment_date'         => $wp_date,
			'comment_date_gmt'     => $gmt_date,
			'comment_content'      => '',
			'comment_karma'        => 0,
			'comment_approved'     => '1',
			'comment_agent'        => 'WordPress',
			'comment_type'         => 'comment',
			'comment_parent'       => 0,
			'user_id'              => $user_id,
		];
	}

	/**
	 * Prepare the generation process.
	 *
	 * @return void
	 */
	protected function prepare_generate(): void {
		global $wpdb;

		$this->posts                      = $this->prepare_posts();
		$this->user_randomizer            = new Randomizer( $this->prepare_users() );
		$this->logged_out_user_randomizer = new Randomizer( $this->prepare_logged_out_users() );
		$this->ip_randomizer              = new Randomizer( $this->prepare_ips() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_comment       = $wpdb->get_row(
			"SELECT comment_ID, comment_date, comment_date_gmt FROM $wpdb->comments ORDER BY comment_ID DESC LIMIT 1"
		);
		$this->comment_ID   = (int) ( $last_comment->comment_ID ?? 0 );
		$this->chunk_start  = time() - $this->initial_time_shift;
		$this->comment_time = max(
			$this->chunk_start,
			$this->comment_time,
			$last_comment ? min( time(), $this->get_timestamp( $last_comment->comment_date, $last_comment->comment_date_gmt ) ) : 0
		);

		$this->post_comments_stub    = [];
		$this->nesting_probabilities = [];
		$nesting_percentage          = $this->nesting_percentage / 100;

		// Sum of geometric progression.
		$nesting_sum =
			( $nesting_percentage ** ( $this->max_nesting_level + 1 ) - 1 ) /
			( $nesting_percentage - 1 );

		for ( $i = 0; $i <= $this->max_nesting_level; $i++ ) {
			$this->post_comments_stub[ $i ]    = [];
			$this->nesting_probabilities[ $i ] = (int) round( ( $nesting_percentage ** $i / $nesting_sum ) * 100 );
		}
	}

	/**
	 * Generate comment.
	 *
	 * @return array
	 * @noinspection RandomApiMigrationInspection
	 */
	public function generate(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		if ( mt_rand( 1, 100 ) <= $this->logged_in_percentage ) {
			$user = $this->user_randomizer->get()[0];
		} else {
			$user = $this->logged_out_user_randomizer->get()[0];
		}

		// Use disjoint time slots so a new chunk cannot go backwards, even in SQL export mode.
		$minimum            = max( $this->comment_time, $this->chunk_start );
		$this->chunk_start += $this->max_time_shift;
		$maximum            = max( $minimum, min( time(), $this->chunk_start ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$this->comment_time = mt_rand( $minimum, $maximum );

		$count = count( $this->posts );
		while ( $this->available_posts < $count && $this->posts[ $this->available_posts ]->timestamp <= $this->comment_time ) {
			++$this->available_posts;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$post = $this->posts[ mt_rand( 0, $this->available_posts - 1 ) ];

		$parent = $this->add_comment_to_post( $post );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$content = implode( "\n\n", Lorem::sentences( mt_rand( 1, $this->max_sentences ) ) );

		$comment                         = $this->stub;
		$comment['comment_post_ID']      = $post->ID;
		$comment['comment_author']       = $user->display_name;
		$comment['comment_author_email'] = $user->user_email;
		$comment['comment_author_IP']    = $this->ip_randomizer->get()[0];
		$comment['comment_date']         = $this->wp_date( self::MYSQL_TIME_FORMAT, $this->comment_time );
		$comment['comment_date_gmt']     = $this->gmt_date( self::MYSQL_TIME_FORMAT, $this->comment_time );
		$comment['comment_content']      = $content;
		$comment['comment_parent']       = $parent;
		$comment['user_id']              = $user->ID;

		return $comment;
	}

	/**
	 * Add comment to the post and return comment parent.
	 *
	 * @param object $post Post.
	 *
	 * @return int
	 * @noinspection RandomApiMigrationInspection
	 */
	private function add_comment_to_post( object $post ): int {
		if ( ! isset( $post->comments ) ) {
			$post->comments = $this->post_comments_stub;
		}

		$level  = $this->get_comment_level();
		$parent = 0;

		do {
			if ( 0 === $level ) {
				break;
			}

			$count = count( $post->comments[ $level - 1 ] );

			if ( $count ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				$parent = $post->comments[ $level - 1 ][ mt_rand( 0, $count - 1 ) ];
				break;
			}

			--$level;
		} while ( $level >= 0 );
		// phpcs:enable WordPress.WP.AlternativeFunctions.rand_mt_rand

		$post->comments[ $level ][] = ++$this->comment_ID;

		return $parent;
	}

	/**
	 * Get comment level.
	 *
	 * @return int|string
	 * @noinspection RandomApiMigrationInspection
	 */
	private function get_comment_level() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$rand = mt_rand( 0, 100 );

		$level = 0;

		foreach ( $this->nesting_probabilities as $level => $nesting_probability ) {
			if ( $rand > $nesting_probability ) {
				break;
			}
		}

		return $level;
	}

	/**
	 * Prepare posts in publication order. Scheduled posts cannot receive comments yet.
	 *
	 * @return object[]
	 */
	private function prepare_posts(): array {
		global $wpdb;

		$now = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$oldest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_date, post_date_gmt FROM $wpdb->posts
				WHERE post_type = 'post' AND post_date <= %s AND post_date_gmt <= %s
				ORDER BY post_date, ID LIMIT 1",
				$this->wp_date( self::MYSQL_TIME_FORMAT, $now ),
				$this->gmt_date( self::MYSQL_TIME_FORMAT, $now )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, post_date, post_date_gmt
						FROM $wpdb->posts AS p
         				INNER JOIN
						(SELECT ID FROM $wpdb->posts WHERE post_type = 'post'
						AND post_date <= %s AND post_date_gmt <= %s ORDER BY RAND() LIMIT %d) AS t
                        ON p.ID = t.ID;",
				$this->wp_date( self::MYSQL_TIME_FORMAT, $now ),
				$this->gmt_date( self::MYSQL_TIME_FORMAT, $now ),
				$this->random_posts_count
			)
		);

		if ( $oldest ) {
			// Keep the start of the timeline independent of each chunk's random sample.
			array_unshift( $posts, $oldest );
			$this->comment_time = $this->get_timestamp( $oldest->post_date, $oldest->post_date_gmt );
		} else {
			$this->comment_time = 0;
			// If no posts, generate comments as not attached to any post.
			$posts = [
				(object) [
					'ID'            => '0',
					'post_date'     => self::ZERO_MYSQL_TIME,
					'post_date_gmt' => self::ZERO_MYSQL_TIME,
				],
			];
		}

		$unique_posts = [];
		foreach ( $posts as $post ) {
			$post->timestamp           = $this->get_timestamp( $post->post_date, $post->post_date_gmt );
			$unique_posts[ $post->ID ] = $post;
		}
		$unique_posts = array_slice( $unique_posts, 0, $this->random_posts_count );
		usort(
			$unique_posts,
			static function ( object $first, object $second ): int {
				return $first->timestamp <=> $second->timestamp;
			}
		);

		return $unique_posts;
	}

	/**
	 * Read a UTC timestamp, falling back to the site's timezone for missing GMT dates.
	 *
	 * @param string $date     Local date.
	 * @param string $date_gmt GMT date.
	 *
	 * @return int
	 */
	private function get_timestamp( string $date, string $date_gmt ): int {
		if ( self::ZERO_MYSQL_TIME === $date_gmt ) {
			if ( self::ZERO_MYSQL_TIME === $date ) {
				return 0;
			}
			$date_gmt = get_gmt_from_date( $date );
		}

		return (int) strtotime( $date_gmt . ' UTC' );
	}

	/**
	 * Prepare IPs.
	 *
	 * @return string[]
	 * @noinspection RandomApiMigrationInspection
	 */
	private function prepare_ips(): array {
		$ips = [ '127.0.0.1' ];

		for ( $i = 1; $i < $this->random_ips_count; $i++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$ips[ $i ] = mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 );
		}

		return $ips;
	}
}
