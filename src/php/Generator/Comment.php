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
	 */
	const RANDOM_POSTS_COUNT = 1000;

	/**
	 * Maximum random IP count. Newly generated comments will have a random IP from this set.
	 */
	const RANDOM_IPS_COUNT = 1000;

	/**
	 * Max nesting level number, starting from 0.
	 */
	const MAX_COMMENT_NESTING_LEVEL = 2;

	/**
	 * Percent of nested comments comparing to previous level. Must be from 0 to 100.
	 */
	const NESTING_PERCENTAGE = 50;

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
	 * Prepare post stub.
	 *
	 * @return void
	 */
	protected function prepare_stub() {
		$user       = wp_get_current_user();
		$user_id    = $user ? $user->ID : 0;
		$user_name  = $user ? $user->display_name : '';
		$user_email = $user ? $user->user_email : '';
		$user_login = $user ? $user->user_login : '';

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
	 * Prepare generate process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {
		global $wpdb;

		$this->post_id_randomizer = new Randomizer( $this->prepare_posts() );
		$this->user_randomizer    = new Randomizer( $this->prepare_users() );
		$this->ip_randomizer      = new Randomizer( $this->prepare_ips() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->comment_ID = (int) $wpdb->get_var(
			"SELECT comment_ID FROM $wpdb->comments ORDER BY comment_ID DESC LIMIT 1"
		);

		$this->post_comments_stub    = [];
		$this->nesting_probabilities = [];
		$nesting_percentage          = self::NESTING_PERCENTAGE / 100;

		// Sum of geometric progression.
		$nesting_sum =
			( $nesting_percentage ** ( self::MAX_COMMENT_NESTING_LEVEL + 1 ) - 1 ) /
			( $nesting_percentage - 1 );

		for ( $i = 0; $i <= self::MAX_COMMENT_NESTING_LEVEL; $i ++ ) {
			$this->post_comments_stub[ $i ]    = [];
			$this->nesting_probabilities[ $i ] = (int) round( ( $nesting_percentage ** $i / $nesting_sum ) * 100 );
		}
	}

	/**
	 * Generate comment.
	 *
	 * @return array
	 */
	public function generate() {
		$user = $this->user_randomizer->get()[0];
		$post = $this->post_id_randomizer->get()[0];

		$this->add_time_shift_to_post( $post );

		$parent = $this->add_comment_to_post( $post );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$content = implode( "\r\r", Lorem::sentences( mt_rand( 1, 30 ) ) );

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
	 */
	private function add_comment_to_post( $post ) {
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

			$level --;
		} while ( $level >= 0 );
		// phpcs:enable WordPress.WP.AlternativeFunctions.rand_mt_rand

		$post->comments[ $level ][] = ++ $this->comment_ID;

		return $parent;
	}

	/**
	 * Get comment level.
	 *
	 * @return int|string
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
	 * Prepare post ids.
	 *
	 * @return string[]
	 */
	private function prepare_posts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, post_date, post_date_gmt
						FROM $wpdb->posts AS p
         				INNER JOIN
						(SELECT ID FROM $wpdb->posts WHERE post_type = 'post' ORDER BY RAND() LIMIT %d) AS t
                        ON p.ID = t.ID;",
				self::RANDOM_POSTS_COUNT
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
	 */
	private function prepare_ips() {
		$ips = [ '127.0.0.1' ];

		for ( $i = 1; $i < self::RANDOM_IPS_COUNT; $i ++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$ips[ $i ] = mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 ) . '.' . mt_rand( 0, 255 );
		}

		return $ips;
	}
}
