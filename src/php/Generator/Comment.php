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

	const RANDOM_POSTS_COUNT = 1000;
	const RANDOM_USERS_COUNT = 1000;
	const RANDOM_IPS_COUNT   = 1000;
	const MAX_TIME_SHIFT     = HOUR_IN_SECONDS;
	const ZERO_MYSQL_TIME    = '0000-00-00 00:00:00';
	const MYSQL_TIME_FORMAT  = 'Y-m-d H:i:s';
	const NESTED_PERCENTAGE  = 50;

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
	 * Randomizer class instance for comments.
	 *
	 * @var Randomizer
	 */
	private $comment_randomizer;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->post_id_randomizer = new Randomizer( $this->prepare_posts() );
		$this->user_randomizer    = new Randomizer( $this->prepare_users() );
		$this->ip_randomizer      = new Randomizer( $this->prepare_ips() );
		$this->comment_randomizer = new Randomizer( $this->prepare_comments() );
	}

	/**
	 * Prepare post stub.
	 *
	 * @return void
	 */
	public function prepare_stub() {
		$user       = wp_get_current_user();
		$user_id    = $user ? $user->ID : 0;
		$user_name  = $user ? $user->display_name : '';
		$user_email = $user ? $user->user_email : '';
		$user_login = $user ? $user->user_login : '';

		$wp_date  = wp_date( self::MYSQL_TIME_FORMAT );
		$gmt_date = gmdate( self::MYSQL_TIME_FORMAT );

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
	 * Generate comment.
	 *
	 * @return array
	 */
	public function generate() {
		$user = $this->user_randomizer->get()[0];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		if ( mt_rand( 0, 100 ) <= self::NESTED_PERCENTAGE ) {
			// This is child comment.
			$parent    = $this->comment_randomizer->get()[0];
			$parent_id = $parent->comment_ID;

			// Post from parent comment.
			$post = new \stdClass();
			$post->ID = $parent->comment_post_ID;
			$post->post_date = $parent->comment_date;
			$post->post_date_gmt = $parent->comment_date_gmt;
		} else {
			// This is top-level comment.
			$parent_id = 0;
			$post      = $this->post_id_randomizer->get()[0];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$time_shift = mt_rand( 0, self::MAX_TIME_SHIFT );
		$post       = $this->add_time_shift( $post, $time_shift );

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
		$comment['comment_parent']       = $parent_id;
		$comment['user_id']              = $user->ID;

		return $comment;
	}

	/**
	 * Add random time shift to post dates.
	 *
	 * @param object $post       Comment.
	 * @param int    $time_shift Time shift.
	 *
	 * @return object
	 */
	private function add_time_shift( $post, $time_shift ) {
		$date     = self::ZERO_MYSQL_TIME === $post->post_date ? 0 : strtotime( $post->post_date ) + $time_shift;
		$date_gmt = self::ZERO_MYSQL_TIME === $post->post_date_gmt ? 0 : strtotime( $post->post_date_gmt ) + $time_shift;
		$max_date = max( $date, $date_gmt );
		$now      = time();

		if ( $max_date > $now ) {
			$in_future = $max_date - $now;
			$date      = max( $date - $in_future, 0 );
			$date_gmt  = max( $date_gmt - $in_future, 0 );
		}

		$post->post_date     = 0 === $date ? self::ZERO_MYSQL_TIME : gmdate( self::MYSQL_TIME_FORMAT, $date );
		$post->post_date_gmt = 0 === $date_gmt ? self::ZERO_MYSQL_TIME : gmdate( self::MYSQL_TIME_FORMAT, $date_gmt );

		return $post;
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
						FROM wp_posts AS p
         				INNER JOIN
     					(SELECT ID FROM wp_posts WHERE post_type = 'post' ORDER BY RAND() LIMIT %d) AS t
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
	 * Prepare users.
	 *
	 * @return array[]
	 */
	private function prepare_users() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_email, display_name FROM $wpdb->users ORDER BY RAND() LIMIT %d",
				self::RANDOM_USERS_COUNT
			)
		);
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

	/**
	 * Prepare comments
	 *
	 * @return stdClass[]
	 */
	private function prepare_comments()	{
		global $wpdb;

		// Only use parent comments from the current chunk posts.
		$posts    = $this->post_id_randomizer->get( self::RANDOM_POSTS_COUNT );
		$post_ids = array_map(
			function( $item ) {
				return $item->ID;
			},
			$posts
		);

		$post_id_placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_post_ID, comment_date, comment_date_gmt
					FROM $wpdb->comments
					WHERE comment_post_ID IN ( $post_id_placeholders )",
				$post_ids
			)
		);
	}
}
