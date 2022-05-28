<?php
/**
 * Post class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Lorem;
use KAGG\Generator\Settings;

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
	 * Prepare post stub.
	 *
	 * @return void
	 */
	public function prepare_stub() {
		$user    = wp_get_current_user();
		$user_id = $user ? $user->ID : 0;

		$wp_date  = wp_date( 'Y-m-d H:i:s' );
		$gmt_date = gmdate( 'Y-m-d H:i:s' );

		// We have to init all post fields here in the same order as provided in get_post_fields().
		// Otherwise, csv file won't be created properly.
		$this->item_stub = [
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
	 * Generate post.
	 *
	 * @return array
	 * @noinspection NonSecureUniqidUsageInspection
	 */
	public function generate_item() {
		$content = implode( "\r\r", Lorem::paragraphs( 12 ) );
		$title   = substr( Lorem::sentence( 5 ), 0, - 1 );
		$name    = str_replace( ' ', '-', strtolower( $title ) ) . '-' . uniqid();

		$post                 = $this->item_stub;
		$post['post_content'] = $content;
		$post['post_title']   = $title;
		$post['post_excerpt'] = substr( $content, 0, 100 );
		$post['post_name']    = $name;
		$post['guid']         = Settings::MARKER . $name;

		return $post;
	}
}
