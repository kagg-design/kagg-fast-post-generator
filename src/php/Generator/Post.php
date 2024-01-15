<?php
/**
 * 'Post' class file.
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
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type = 'post';

	/**
	 * Number of paragraphs in the post.
	 *
	 * @var int
	 */
	protected $paragraphs_in_post;

	/**
	 * Number of words in title.
	 *
	 * @var int
	 */
	protected $words_in_title;

	/**
	 * Randomizer class instance for users.
	 *
	 * @var Randomizer
	 */
	private $user_randomizer;

	/**
	 * Non-existing post, having a time to use in the post's generation.
	 *
	 * @var stdClass
	 */
	private $post_time_keeper;

	/**
	 * Prepare the post's stub.
	 *
	 * @return void
	 */
	protected function prepare_stub() {
		$this->paragraphs_in_post = max(
			1,
			(int) apply_filters( 'kagg_generator_paragraphs_in_post', 12 )
		);

		$this->words_in_title = max(
			1,
			(int) apply_filters( 'kagg_generator_words_in_title', 5 )
		);

		$user_id = get_current_user_id();

		$now      = time();
		$wp_date  = $this->wp_date( self::MYSQL_TIME_FORMAT, $now );
		$gmt_date = $this->gmt_date( self::MYSQL_TIME_FORMAT, $now );

		// We have to init all post's fields here in the same order as provided in get_post_fields().
		// Otherwise, csv file won't be created properly.
		// We must include to_ping, pinged, and post_content_filtered as they do not have default values.
		$this->stub = [
			'post_author'           => $user_id,
			'post_date'             => $wp_date,
			'post_date_gmt'         => $gmt_date,
			'post_content'          => '',
			'post_title'            => '',
			'post_excerpt'          => '',
			'post_name'             => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => $wp_date,
			'post_modified_gmt'     => $gmt_date,
			'post_content_filtered' => '',
			'guid'                  => '',
			'post_type'             => $this->item_type,
		];
	}

	/**
	 * Prepare the generation process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {
		$this->user_randomizer = new Randomizer( $this->prepare_users() );

		$initial_timestamp      = time() - $this->initial_time_shift;
		$this->post_time_keeper = new stdClass();

		$this->post_time_keeper->post_date     = $this->wp_date( self::MYSQL_TIME_FORMAT, $initial_timestamp );
		$this->post_time_keeper->post_date_gmt = $this->gmt_date( self::MYSQL_TIME_FORMAT, $initial_timestamp );
	}

	/**
	 * Generate post.
	 *
	 * @return array
	 * @noinspection NonSecureUniqidUsageInspection
	 */
	public function generate(): array {
		$content = implode( "\n\n", Lorem::paragraphs( $this->paragraphs_in_post ) );
		$title   = substr( Lorem::sentence( $this->words_in_title ), 0, - 1 );
		$name    = str_replace( ' ', '-', strtolower( $title ) ) . '-' . uniqid();
		$user    = $this->user_randomizer->get()[0];

		$this->add_time_shift_to_post( $this->post_time_keeper );

		$post_modified = $this->post_time_keeper;

		$this->add_time_shift_to_post( $post_modified );

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
