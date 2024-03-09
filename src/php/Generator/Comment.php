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
	protected $random_posts_count;

	/**
	 * Random IPs count. Newly generated comments will have a random IP from this set.
	 *
	 * @var int
	 */
	protected $random_ips_count;

	/**
	 * Max nesting level number, starting from 0.
	 *
	 * @var int
	 */
	protected $max_nesting_level;

	/**
	 * Percentage of nested comments comparing to previous level. Must be from 0 to 100.
	 *
	 * @var int
	 */
	protected $nesting_percentage;

	/**
	 * Max sentences in comment.
	 *
	 * @var int
	 */
	protected $max_sentences;

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type = 'comment';

	/**
	 * Item DB table name without prefix.
	 *
	 * @var string
	 */
	protected $table = 'comments';

	/**
	 * Item DB table field name containing added items' marker.
	 *
	 * @var string
	 */
	protected $marker_field = 'comment_author_url';

	/**
	 * Randomizer class instance for post_ids.
	 *
	 * @var Randomizer
	 */
	private $post_id_randomizer;

	/**
	 * Randomizer class instance for users.
	 *
	 * @var Randomizer
	 */
	private $user_randomizer;

	/**
	 * Randomizer class instance for logged-out users.
	 *
	 * @var Randomizer
	 */
	private $logged_out_user_randomizer;

	/**
	 * Randomizer class instance for IPs.
	 *
	 * @var Randomizer
	 */
	private $ip_randomizer;

	/**
	 * Current comment id.
	 *
	 * @var int
	 */
	private $comment_ID;

	/**
	 * Post comments stub.
	 *
	 * @var array
	 */
	private $post_comments_stub;

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
	protected function prepare_stub() {
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
			(int) apply_filters( 'kagg_generator_comment_max_nesting_level', 50 )
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

		// Here we have to list the fields in the same order as in wp_comments table.
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
	protected function prepare_generate() {
		global $wpdb;

		$this->post_id_randomizer         = new Randomizer( $this->prepare_posts() );
		$this->user_randomizer            = new Randomizer( $this->prepare_users() );
		$this->logged_out_user_randomizer = new Randomizer( $this->prepare_logged_out_users() );
		$this->ip_randomizer              = new Randomizer( $this->prepare_ips() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->comment_ID = (int) $wpdb->get_var(
			"SELECT comment_ID FROM $wpdb->comments ORDER BY comment_ID DESC LIMIT 1"
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

		$post = $this->post_id_randomizer->get()[0];

		if ( isset( $post->max_time_shift ) ) {
			$max_time_shift = $post->max_time_shift;
		} else {
			$date                 = self::ZERO_MYSQL_TIME === $post->post_date ? 0 : strtotime( $post->post_date );
			$date_gmt             = self::ZERO_MYSQL_TIME === $post->post_date_gmt ? 0 : strtotime( $post->post_date_gmt );
			$max_date             = max( $date, $date_gmt );
			$now                  = time();
			$max_time_shift       = $now - $max_date;
			$post_count           = max( 1, $this->post_id_randomizer->count() );
			$comments_per_post    = $this->number / $post_count;
			$max_time_shift       = max( 0, $max_time_shift ) / $comments_per_post;
			$post->max_time_shift = $max_time_shift;
		}

		$this->add_time_shift_to_post( $post, (int) $max_time_shift );

		$parent = $this->add_comment_to_post( $post );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$content = implode( "\n\n", Lorem::sentences( mt_rand( 1, $this->max_sentences ) ) );

		$comment                         = $this->stub;
		$comment['comment_post_ID']      = $post->ID;
		$comment['comment_author']       = $user->display_name;
		$comment['comment_author_email'] = $user->user_email;
		$comment['comment_author_IP']    = $this->ip_randomizer->get()[0];
		$comment['comment_date']         = $post->post_date;
		$comment['comment_date_gmt']     = $post->post_date_gmt;
		$comment['comment_content']      = $content;
		$comment['comment_parent']       = $parent;
		$comment['user_id']              = $user->ID;

		return $comment;
	}

	/**
	 * Add comment to post and return comment parent.
	 *
	 * @param object $post Post.
	 *
	 * @return int
	 * @noinspection RandomApiMigrationInspection
	 */
	private function add_comment_to_post( $post ): int {
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
	 * Prepare posts' ids.
	 *
	 * @return string[]
	 */
	private function prepare_posts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, post_date, post_date_gmt
						FROM $wpdb->posts AS p
         				INNER JOIN
						(SELECT ID FROM $wpdb->posts WHERE post_type = 'post' ORDER BY RAND() LIMIT %d) AS t
                        ON p.ID = t.ID;",
				$this->random_posts_count
			)
		);

		// If no posts, generate comments as not attached to any post.
		return $posts ?: [
			(object) [
				'ID'            => '0',
				'post_date'     => self::ZERO_MYSQL_TIME,
				'post_date_gmt' => self::ZERO_MYSQL_TIME,
			],
		];
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
