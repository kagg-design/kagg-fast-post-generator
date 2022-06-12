<?php
/**
 * Post class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Lorem;
use KAGG\Generator\Randomizer;
use KAGG\Generator\Settings;
use stdClass;

/**
 * Class Post.
 */
class Post extends Item {

	/**
	 * Initial time shift, back in time.
	 */
	const INITIAL_TIME_SHIFT = YEAR_IN_SECONDS;

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type = 'post';

	/**
	 * Randomizer class instance for users.
	 *
	 * @var Randomizer
	 */
	private $user_randomizer;

	/**
	 * Non-existing post, having a time to use in post generation.
	 *
	 * @var stdClass
	 */
	private $post_time_keeper;

	/**
	 * Prepare post stub.
	 *
	 * @return void
	 */
	protected function prepare_stub() {
		$user    = wp_get_current_user();
		$user_id = $user ? $user->ID : 0;

		$now      = time();
		$wp_date  = wp_date( 'Y-m-d H:i:s', $now );
		$gmt_date = gmdate( 'Y-m-d H:i:s', $now );

		// We have to init all post fields here in the same order as provided in get_post_fields().
		// Otherwise, csv file won't be created properly.
		$this->stub = [
			'post_author'       => $user_id,
			'post_date'         => $wp_date,
			'post_date_gmt'     => $gmt_date,
			'post_content'      => '',
			'post_title'        => '',
			'post_excerpt'      => '',
			'post_name'         => '',
			'post_modified'     => $wp_date,
			'post_modified_gmt' => $gmt_date,
			'guid'              => '',
			'post_type'         => $this->item_type,
		];
	}

	/**
	 * Prepare generate process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {
		$this->user_randomizer = new Randomizer( $this->prepare_users() );

		$now                    = time() - self::INITIAL_TIME_SHIFT;
		$this->post_time_keeper = new stdClass();

		$this->post_time_keeper->post_date     = wp_date( self::MYSQL_TIME_FORMAT, $now );
		$this->post_time_keeper->post_date_gmt = gmdate( self::MYSQL_TIME_FORMAT, $now );
	}

	/**
	 * Generate post.
	 *
	 * @return array
	 * @noinspection NonSecureUniqidUsageInspection
	 */
	public function generate() {
		$content = implode( "\r\r", Lorem::paragraphs( 12 ) );
		$title   = substr( Lorem::sentence( 5 ), 0, - 1 );
		$name    = str_replace( ' ', '-', strtolower( $title ) ) . '-' . uniqid();
		$user    = $this->user_randomizer->get()[0];

		$this->add_time_shift( $this->post_time_keeper );

		$post_modified = $this->post_time_keeper;

		$this->add_time_shift( $post_modified );

		$post                      = $this->stub;
		$post['post_author']       = $user->ID;
		$post['post_date']         = $this->post_time_keeper->post_date;
		$post['post_date_gmt']     = $this->post_time_keeper->post_date_gmt;
		$post['post_content']      = $content;
		$post['post_title']        = $title;
		$post['post_excerpt']      = substr( $content, 0, 100 );
		$post['post_name']         = $name;
		$post['post_modified']     = $post_modified->post_date;
		$post['post_modified_gmt'] = $post_modified->post_date_gmt;
		$post['guid']              = Settings::MARKER . $name;

		return $post;
	}
}
