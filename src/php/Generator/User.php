<?php
/**
 * User class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Lorem;
use KAGG\Generator\Randomizer;
use KAGG\Generator\Settings;
use stdClass;

/**
 * Class User.
 */
class User extends Item {

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type = 'user';

	/**
	 * Item DB table name without prefix.
	 *
	 * @var string
	 */
	protected $table = 'users';

	/**
	 * Item DB table field name containing added items' marker.
	 *
	 * @var string
	 */
	protected $marker_field = 'user_url';

	/**
	 * Randomizer class instance for usernames.
	 *
	 * @var Randomizer
	 */
	private $username_randomizer;

	/**
	 * Non-existing user, having a user_registered date to use in post's generation.
	 *
	 * @var stdClass
	 */
	private $user_time_keeper;

	/**
	 * Random password for generated users. The same for all users.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Existing user logins.
	 *
	 * @var mixed
	 */
	private $existing_user_logins = [];

	/**
	 * Prepare post's stub.
	 *
	 * @return void
	 */
	protected function prepare_stub() {
		// We have to init all post's fields here in the same order as provided in get_post_fields().
		// Otherwise, csv file won't be created properly.
		$this->stub = [
			'user_login'          => '',
			'user_pass'           => '',
			'user_nicename'       => '',
			'user_email'          => '',
			'user_url'            => '',
			'user_registered'     => self::ZERO_MYSQL_TIME,
			'user_activation_key' => '',
			'user_status'         => 0,
			'display_name'        => '',
		];
	}

	/**
	 * Prepare the generation process.
	 *
	 * @return void
	 */
	protected function prepare_generate() {
		global $wpdb;

		$this->username_randomizer = new Randomizer( Lorem::get_name_list() );
		$this->password            = wp_hash_password( wp_generate_password() );

		$this->user_time_keeper                  = new stdClass();
		$this->user_time_keeper->user_registered = gmdate(
			self::MYSQL_TIME_FORMAT,
			time() - $this->initial_time_shift
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( "SELECT user_login FROM $wpdb->users" );

		if ( ! $result ) {
			return;
		}

		$this->existing_user_logins = array_flip( wp_list_pluck( $wpdb->last_result, 'user_login' ) );
	}

	/**
	 * Generate post.
	 *
	 * @return array
	 */
	public function generate(): array {
		$username   = $this->username_randomizer->get()[0];
		$user_login = strtolower( $username );

		while ( isset( $this->existing_user_logins[ $user_login ] ) ) {
			if ( preg_match( '/^(.+?)(\d+)$/', $user_login, $m ) ) {
				$number     = ( (int) $m[2] ) + 1;
				$user_login = $m[1] . $number;
				continue;
			}

			$user_login .= '_1';
		}

		$this->existing_user_logins[ $user_login ] = 0;

		$this->add_time_shift_to_user( $this->user_time_keeper );

		$user                    = $this->stub;
		$user['user_login']      = $user_login;
		$user['user_pass']       = $this->password;
		$user['user_nicename']   = $user_login;
		$user['user_email']      = $user_login . '@generator.kagg.eu';
		$user['user_url']        = Settings::MARKER . $user_login;
		$user['user_registered'] = $this->user_time_keeper->user_registered;
		$user['display_name']    = $username;

		return $user;
	}

	/**
	 * Add random time shift to user registered date.
	 *
	 * @param object $user User.
	 *
	 * @return void
	 * @noinspection RandomApiMigrationInspection
	 */
	private function add_time_shift_to_user( $user ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$time_shift = mt_rand( 0, $this->max_time_shift );

		$date = self::ZERO_MYSQL_TIME === $user->user_registered ? 0 : strtotime( $user->user_registered ) + $time_shift;
		$now  = time();

		if ( $date > $now ) {
			$in_future = $date - $now;
			$date      = max( $date - $in_future, 0 );
		}

		$user->user_registered = 0 === $date ? self::ZERO_MYSQL_TIME : gmdate( self::MYSQL_TIME_FORMAT, $date );
	}
}
