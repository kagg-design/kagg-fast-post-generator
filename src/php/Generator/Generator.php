<?php
/**
 * Generator class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

use KAGG\Generator\Settings;
use RuntimeException;

/**
 * Class Generator.
 */
class Generator {

	/**
	 * Name of the local_infile MySQL variable.
	 */
	const LOCAL_INFILE = 'local_infile';

	/**
	 * Value of the local_infile MySQL variable.
	 *
	 * @var string
	 */
	private $local_infile_value;

	/**
	 * Use LOCAL in the MySQL statement LOAD DATA [LOCAL] INFILE.
	 *
	 * @var bool
	 */
	private $use_local_infile;

	/**
	 * Registered item types and theirs handler class names.
	 *
	 * @var string[] Item handlers.
	 */
	private $registered_items;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->registered_items = [
			'post'    => Post::class,
			'page'    => Page::class,
			'comment' => Comment::class,
		];
	}

	/**
	 * Get item handlers.
	 *
	 * @return string[]
	 */
	public function get_registered_items() {
		return $this->registered_items;
	}

	/**
	 * Determine if we should use LOCAL in the MySQL statement LOAD DATA [LOCAL] INFILE.
	 *
	 * @return bool
	 * @throws RuntimeException With error message.
	 */
	public function use_local_infile() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( 'SHOW VARIABLES LIKE %s', 'secure_file_priv' ),
			ARRAY_A
		);

		if ( false === $result ) {
			throw new RuntimeException( $wpdb->last_error );
		}

		return ! empty( $result['Value'] );
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function run() {
		$this->run_checks( Settings::GENERATE_ACTION, true );

		ob_start();

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$index = isset( $_POST['index'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['index'] ) ) : 0;
		$data  = json_decode(
			isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '',
			false
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$settings               = $this->get_settings( $data );
		$this->use_local_infile = $this->use_local_infile();
		$chunk_size             = (int) $settings['chunk_size'];
		$number                 = (int) $settings['number'];
		$count                  = min( $number - $index, $chunk_size );
		$step                   = (int) floor( $index / $chunk_size ) + 1;
		$steps                  = (int) ceil( $number / $chunk_size );
		$temp_filename          = tempnam( sys_get_temp_dir(), 'kagg-generator-' );
		$error                  = false;
		$item_type              = $settings['post_type'];
		$item_classname         = $this->registered_items[ $item_type ];

		/**
		 * Item handler instance.
		 *
		 * @var Item $item_handler
		 */
		$item_handler = new $item_classname();

		$time1 = 0;
		$time2 = 0;

		try {
			$start = microtime( true );
			$this->generate_items( $item_handler, $count, $temp_filename );
			$end   = microtime( true );
			$time1 = round( $end - $start, 3 );

			$start = microtime( true );
			$this->write_items( $item_handler, $temp_filename );
			$end   = microtime( true );
			$time2 = round( $end - $start, 3 );

			$error_message = ob_get_clean();
		} catch ( RuntimeException $ex ) {
			$error = true;

			// We will have some messages here if WP_DEBUG_DISPLAY is on.
			$error_message = ob_get_clean() . $ex->getMessage();
		}

		unlink( $temp_filename );

		if ( $error || $error_message ) {
			wp_send_json_error(
				sprintf(
				// translators: 1: Step. 2: Steps. 3: Error messages.
					esc_html__( 'Step %1$s/%2$s. Error encountered: %3$s.', 'kagg-generator' ),
					number_format( $step, 0 ),
					number_format( $steps, 0 ),
					$error_message
				)
			);
		}

		wp_send_json_success(
			sprintf(
			// translators: 1: Step. 2: Steps. 3: Generated items. 4: Total items to generate. 5: Generation time. 6: DB storing time. 7: Total time.
				esc_html__( 'Step %1$s/%2$s. %3$s/%4$s items generated. Time used: (generate: %5$s + store: %6$s) = %7$s sec.', 'kagg-generator' ),
				number_format( $step, 0 ),
				number_format( $steps, 0 ),
				number_format( min( $step * $chunk_size, $number ), 0 ),
				number_format( $number, 0 ),
				number_format( $time1, 3 ),
				number_format( $time2, 3 ),
				number_format( $time1 + $time2, 3 )
			)
		);
	}

	/**
	 * Run checks.
	 *
	 * @param string $action     Action name.
	 * @param bool   $check_data Action name.
	 *
	 * @return void
	 */
	public function run_checks( $action, $check_data = false ) {
		// Run a security check.
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'kagg-generator' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'kagg-generator' ) );
		}

		if ( ! $check_data ) {
			return;
		}

		// Check for ajax data.
		if ( empty( $_POST['data'] ) ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'kagg-generator' ) );
		}
	}

	/**
	 * Generate items.
	 *
	 * @param Item   $item_handler  Item handler class instance.
	 * @param int    $count         Number of items to generate.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function generate_items( $item_handler, $count, $temp_filename ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$f = fopen( 'php://temp', 'wb+' );

		if ( ! $f ) {
			throw new RuntimeException( esc_html__( 'Cannot create a temporary php://temp file.', 'kagg-generator' ) );
		}

		for ( $i = 0; $i < $count; $i ++ ) {
			$result = fputcsv( $f, $item_handler->generate(), '|' );

			if ( ! $result ) {
				throw new RuntimeException( esc_html__( 'Cannot write to a temporary php://temp file.', 'kagg-generator' ) );
			}
		}

		rewind( $f );

		$file_contents = stream_get_contents( $f );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$result = file_put_contents( $temp_filename, $file_contents );

		if ( ! $result ) {
			throw new RuntimeException(
				sprintf(
				// translators: 1: Temp filename.
					esc_html__( 'Cannot write to the temporary file %s.', 'kagg-generator' ),
					$temp_filename
				)
			);
		}
	}

	/**
	 * Write items to the database.
	 *
	 * @param Item   $item_handler  Item handler class instance.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function write_items( $item_handler, $temp_filename ) {
		global $wpdb;

		$filename = str_replace( '\\', '/', $temp_filename );

		$fields = implode( ', ', $item_handler->get_fields() );

		$this->set_local_infile();

		$local = $this->use_local_infile ? 'LOCAL' : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"LOAD DATA $local INFILE %s INTO TABLE {$item_handler->get_table()}
                    FIELDS TERMINATED BY '|' ENCLOSED BY '\"'
					( $fields )",
				$filename
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $result ) {
			throw new RuntimeException( $wpdb->last_error );
		}

		$this->revert_local_infile();
	}

	/**
	 * Set local_infile variable to 'ON' if needed.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function set_local_infile() {
		global $wpdb;

		if ( ! $this->use_local_infile ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( 'SHOW VARIABLES LIKE %s', self::LOCAL_INFILE ),
			ARRAY_A
		);

		if ( false === $result ) {
			throw new RuntimeException( $wpdb->last_error );
		}

		$this->local_infile_value = isset( $result['Value'] ) ? $result['Value'] : '';

		if ( 'ON' !== $this->local_infile_value ) {
			$result = $wpdb->query( "SET GLOBAL local_infile = 'ON'" );

			if ( false === $result ) {
				throw new RuntimeException( $wpdb->last_error );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Revert local variable if needed.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function revert_local_infile() {
		global $wpdb;

		if ( ! $this->use_local_infile ) {
			return;
		}

		if ( 'ON' !== $this->local_infile_value ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					'SET GLOBAL local_infile = %s',
					$this->local_infile_value
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( false === $result ) {
				throw new RuntimeException( $wpdb->last_error );
			}
		}
	}

	/**
	 * Get settings from input and option.
	 *
	 * @param array $data Form data.
	 *
	 * @return array
	 */
	private function get_settings( $data ) {
		$settings   = [];
		$option_key = Settings::OPTION_KEY;

		foreach ( $data as $datum ) {
			if ( preg_match( "/$option_key\[(.+)]/", $datum->name, $m ) ) {
				$settings[ $m[1] ] = $datum->value;
			}
		}

		return $settings;
	}
}
