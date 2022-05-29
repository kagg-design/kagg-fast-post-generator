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
	 * Randomizer class instance.
	 *
	 * @var Randomizer
	 */
	private $randomizer;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		global $wpdb;

		parent::__construct();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} ORDER BY RAND() LIMIT %d",
				self::RANDOM_POSTS_COUNT
			)
		);

		// If no posts, generate comments as not attached to any post.
		$ids = $ids ?: [ '0' ];

		$this->randomizer = new Randomizer( $ids );
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

		$wp_date  = wp_date( 'Y-m-d H:i:s' );
		$gmt_date = gmdate( 'Y-m-d H:i:s' );

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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$content = implode( "\r\r", Lorem::sentences( mt_rand( 1, 30 ) ) );

		$comment                    = $this->stub;
		$comment['comment_post_ID'] = $this->randomizer->get( 1 )[0];
		$comment['comment_content'] = $content;

		return $comment;
	}
}
