<?php
/**
 * Item class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

/**
 * Class Item.
 */
abstract class Item {

	/**
	 * Maximum random users count. Newly generated comments will have a random author from this user set.
	 */
	const RANDOM_USERS_COUNT = 1000;

	/**
	 * Standard max time shift between generated items. May be adjusted in child classes.
	 */
	const MAX_TIME_SHIFT = HOUR_IN_SECONDS;

	/**
	 * Zero time in MySQL format.
	 */
	const ZERO_MYSQL_TIME = '0000-00-00 00:00:00';

	/**
	 * MySQL time format.
	 */
	const MYSQL_TIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type;

	/**
	 * Item DB table name without prefix.
	 *
	 * @var string
	 */
	protected $table = 'posts';

	/**
	 * Item DB table field name containing added items' marker.
	 *
	 * @var string
	 */
	protected $marker_field = 'guid';

	/**
	 * Item stub.
	 *
	 * @var array
	 */
	protected $stub = [];

	/**
	 * Class constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . $this->table;

		$this->prepare_stub();
		$this->prepare_generate();
	}

	/**
	 * Get item type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->item_type;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table() {
		return $this->table;
	}

	/**
	 * Get marker field name.
	 *
	 * @return string
	 */
	public function get_marker_field() {
		return $this->marker_field;
	}

	/**
	 * Get item fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array_keys( $this->stub );
	}

	/**
	 * Add random time shift to post dates.
	 *
	 * @param object $post Post.
	 *
	 * @return void
	 */
	protected function add_time_shift( $post ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$time_shift = mt_rand( 0, self::MAX_TIME_SHIFT );

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
	}

	/**
	 * Prepare users.
	 *
	 * @return array[]
	 */
	protected function prepare_users() {
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
	 * Prepare item stub.
	 *
	 * @return void
	 */
	abstract protected function prepare_stub();

	/**
	 * Prepare generate process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {}

	/**
	 * Generate item.
	 *
	 * @return array
	 */
	abstract protected function generate();
}
