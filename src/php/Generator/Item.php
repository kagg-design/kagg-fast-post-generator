<?php
/**
 * Item class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Lorem;
use KAGG\Generator\Randomizer;

/**
 * Class Item.
 */
abstract class Item {

	/**
	 * Zero time in MySQL format.
	 */
	const ZERO_MYSQL_TIME = '0000-00-00 00:00:00';

	/**
	 * MySQL time format.
	 */
	const MYSQL_TIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Maximum users count. Newly generated comments will have a random author from this user set.
	 *
	 * @var int
	 */
	protected $random_users_count;

	/**
	 * Percentage of logged-in users. Must be from 0 to 100.
	 *
	 * @var int
	 */
	protected $logged_in_percentage;

	/**
	 * Number of items to generate.
	 *
	 * @var int
	 */
	protected $number;

	/**
	 * Current index.
	 *
	 * @var int
	 */
	protected $index;

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
	 * Initial time shift, back in time.
	 *
	 * @var int
	 */
	protected $initial_time_shift;

	/**
	 * Max time shift between generated items.
	 *
	 * @var int
	 */
	protected $max_time_shift;

	/**
	 * Class constructor.
	 *
	 * @param int $number Number of items to generate.
	 * @param int $index  Current index.
	 */
	public function __construct( int $number = 1, int $index = 0 ) {
		global $wpdb;

		$this->number = $number;
		$this->index  = $index;
		$this->table  = $wpdb->prefix . $this->table;

		$this->random_users_count = max(
			0,
			(int) apply_filters( 'kagg_generator_random_users_count', 1000 )
		);

		$this->logged_in_percentage = max(
			0,
			(int) apply_filters( 'kagg_generator_logged_in_percentage', 10 )
		);
		$this->logged_in_percentage = min( 100, $this->logged_in_percentage );

		$this->initial_time_shift = max(
			0,
			(int) apply_filters( 'kagg_generator_initial_time_shift', YEAR_IN_SECONDS )
		);

		$this->max_time_shift     = (int) ( $this->initial_time_shift / $number );
		$this->initial_time_shift = (int) ( $this->initial_time_shift * ( $number - $index ) / $number );

		$this->prepare_stub();
		$this->prepare_generate();
	}

	/**
	 * Get an item type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->item_type;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table(): string {
		return $this->table;
	}

	/**
	 * Get marker field name.
	 *
	 * @return string
	 */
	public function get_marker_field(): string {
		return $this->marker_field;
	}

	/**
	 * Get item fields.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return array_keys( $this->stub );
	}

	/**
	 * Add random time shift to post's dates.
	 *
	 * @param object $post           Post.
	 * @param int    $max_time_shift Time shift.
	 *
	 * @return void
	 * @noinspection CallableParameterUseCaseInTypeContextInspection
	 * @noinspection RandomApiMigrationInspection
	 */
	protected function add_time_shift_to_post( $post, int $max_time_shift = 0 ) {  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
		$max_time_shift = 0 === $max_time_shift ? $this->max_time_shift : $max_time_shift;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$time_shift = mt_rand( 0, $max_time_shift );

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
	 * @return array
	 */
	protected function prepare_users(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, user_email, display_name FROM $wpdb->users ORDER BY RAND() LIMIT %d",
				$this->random_users_count
			)
		);

		return array_map(
			static function ( $user ) {
				$user->ID = (int) $user->ID;

				return $user;
			},
			$users
		);
	}

	/**
	 * Prepare logged-out users.
	 *
	 * @return array
	 */
	protected function prepare_logged_out_users(): array {
		$username_randomizer = new Randomizer( Lorem::get_name_list() );
		$logged_out_users    = [];

		for ( $i = 0; $i < $this->random_users_count; $i++ ) {
			$username   = $username_randomizer->get()[0];
			$user_login = strtolower( $username );

			$logged_out_users[] = (object) [
				'ID'           => 0,
				'user_email'   => $user_login . '@generator.kagg.eu',
				'display_name' => $username,
			];
		}

		return $logged_out_users;
	}

	/**
	 * Get WP or zero date.
	 *
	 * @param string $format Format.
	 * @param int    $time   Time.
	 *
	 * @return false|string
	 */
	protected function wp_date( string $format, int $time ) {
		$wp_date = wp_date( $format, $time );

		return $wp_date ?: self::ZERO_MYSQL_TIME;
	}

	/**
	 * Get GMT or zero dates.
	 *
	 * @param string $format Format.
	 * @param int    $time   Time.
	 *
	 * @return false|string
	 */
	protected function gmt_date( string $format, int $time ) {
		$gmt_date = gmdate( $format, $time );

		return $gmt_date ?: self::ZERO_MYSQL_TIME;
	}

	/**
	 * Prepare item stub.
	 *
	 * @return void
	 */
	abstract protected function prepare_stub();

	/**
	 * Prepare the generation process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {}

	/**
	 * Generate item.
	 *
	 * @return array
	 */
	abstract protected function generate(): array;
}
