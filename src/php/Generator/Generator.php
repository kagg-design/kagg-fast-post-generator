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
	 * Item handler instance.
	 *
	 * @var Item $item_handler
	 */
	private $item_handler;

	/**
	 * Whether to download SQL file.
	 *
	 * @var bool
	 */
	private $download_sql;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->registered_items = [
			'post'    => Post::class,
			'page'    => Page::class,
			'comment' => Comment::class,
			'user'    => User::class,
		];
	}

	/**
	 * Get item handlers.
	 *
	 * @return string[]
	 */
	public function get_registered_items(): array {
		return $this->registered_items;
	}

	/**
	 * Determine if we should use LOCAL in the MySQL statement LOAD DATA [LOCAL] INFILE.
	 *
	 * @return bool
	 * @throws RuntimeException With error message.
	 */
	public function use_local_infile(): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( 'SHOW VARIABLES LIKE %s', 'secure_file_priv' ),
			ARRAY_A
		);

		if ( false === $result ) {
			throw new RuntimeException( esc_html( $wpdb->last_error ) );
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

		$generation_id          = $this->get_input( $data, Settings::GENERATION_ID );
		$settings               = $this->get_settings( $data );
		$this->use_local_infile = $this->use_local_infile();
		$number                 = (int) $settings['number'];
		$chunk_size             = (int) $settings['chunk_size'];
		$this->download_sql     = isset( $settings['sql'] );
		$count                  = min( $number - $index, $chunk_size );
		$step                   = (int) floor( $index / $chunk_size ) + 1;
		$steps                  = (int) ceil( $number / $chunk_size );
		$temp_filename          = tempnam( sys_get_temp_dir(), Settings::PREFIX );
		$error                  = false;
		$item_type              = $settings['post_type'];
		$item_classname         = $this->registered_items[ $item_type ];
		$this->item_handler     = new $item_classname( $number, $index );

		$time1 = 0;
		$time2 = 0;

		try {
			$start = microtime( true );
			$this->generate_items( $count, $temp_filename );
			$end   = microtime( true );
			$time1 = round( $end - $start, 3 );

			$start = microtime( true );
			$this->store_items( $temp_filename );
			$end   = microtime( true );
			$time2 = round( $end - $start, 3 );

			$error_message = ob_get_clean();
		} catch ( RuntimeException $ex ) {
			$error = true;

			// We will have some messages here if WP_DEBUG_DISPLAY is on.
			$error_message = ob_get_clean() . $ex->getMessage();
		}

		if ( $this->download_sql ) {
			$user_id          = get_current_user_id();
			$temp_filenames   = array_filter( (array) get_user_meta( $user_id, $generation_id, true ) );
			$temp_filenames[] = str_replace( '\\', '/', $temp_filename );

			update_user_meta( $user_id, $generation_id, $temp_filenames );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp_filename );
		}

		if ( $error || $error_message ) {
			wp_send_json_error(
				sprintf(
				// translators: 1: Step. 2: Steps. 3: Error messages.
					esc_html__( 'Step %1$s/%2$s. Error encountered: %3$s.', 'kagg-generator' ),
					number_format( $step ),
					number_format( $steps ),
					$error_message
				)
			);
		}

		wp_send_json_success(
			sprintf(
			// translators: 1: Step. 2: Steps. 3: Generated items. 4: Total items to generate. 5: Generation time. 6: DB storing time. 7: Total time.
				esc_html__( 'Step %1$s/%2$s. %3$s/%4$s items generated. Time used: (generate: %5$s + store: %6$s) = %7$s sec.', 'kagg-generator' ),
				number_format( $step ),
				number_format( $steps ),
				number_format( min( $step * $chunk_size, $number ) ),
				number_format( $number ),
				number_format( $time1, 3 ),
				number_format( $time2, 3 ),
				number_format( $time1 + $time2, 3 )
			)
		);
	}

	/**
	 * Download SQL file.
	 *
	 * @return void
	 */
	public function download_sql() {
		$this->run_checks( Settings::DOWNLOAD_SQL_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$data = json_decode(
			isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '',
			false
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$generation_id      = $this->get_input( $data, Settings::GENERATION_ID );
		$settings           = $this->get_settings( $data );
		$item_type          = $settings['post_type'];
		$item_classname     = $this->registered_items[ $item_type ];
		$this->item_handler = new $item_classname();
		$table              = $this->item_handler->get_table();
		$user_id            = get_current_user_id();
		$temp_filenames     = array_filter( (array) get_user_meta( $user_id, $generation_id, true ) );

		if ( ! $temp_filenames ) {
			exit();
		}

		$this->http_headers();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, Squiz.WhiteSpace.LanguageConstructSpacing.IncorrectSingle
		echo(
			"/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
			"/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" .
			"/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" .
			"/*!50503 SET NAMES utf8mb4 */;\n" .
			"/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n" .
			"/*!40103 SET TIME_ZONE='+00:00' */;\n" .
			"/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n" .
			"/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n" .
			"/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n" .
			"/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n" .
			"\n" .
			"LOCK TABLES `$table` WRITE;\n" .
			"/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\n"
		);

		foreach ( $temp_filenames as $temp_filename ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile( $temp_filename );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp_filename );
		}

		echo(
			"/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n" .
			"UNLOCK TABLES;\n" .
			"\n" .
			"/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n" .
			"/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n" .
			"/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n" .
			"/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n" .
			"/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
			"/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
			"/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n" .
			"/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n"
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped, Squiz.WhiteSpace.LanguageConstructSpacing.IncorrectSingle

		delete_user_meta( $user_id, $generation_id );

		exit();
	}

	/**
	 * Run checks.
	 *
	 * @param string $action     Action name.
	 * @param bool   $check_data Action name.
	 *
	 * @return void
	 */
	public function run_checks( string $action, bool $check_data = false ) {
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
	 * @param int    $count         Number of items to generate.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function generate_items( int $count, string $temp_filename ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$f = fopen( 'php://temp', 'wb+' );

		if ( ! $f ) {
			throw new RuntimeException( esc_html__( 'Cannot create a temporary php://temp file.', 'kagg-generator' ) );
		}

		if ( $this->download_sql ) {
			$write = [ $this, 'write_item_sql' ];
		} else {
			$write = [ $this, 'write_item_csv' ];
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$last_item = ( $count - 1 ) === $i;

			if ( ! $write( $f, $last_item ) ) {
				throw new RuntimeException( esc_html__( 'Cannot write to a temporary php://temp file.', 'kagg-generator' ) );
			}
		}

		rewind( $f );

		$this->write_file( $temp_filename, $f );
	}

	/**
	 * Write item in csv format.
	 *
	 * @param resource $f         File.
	 * @param bool     $last_item Whether we process the last item in the file.
	 *
	 * @return false|int
	 * @noinspection PhpUnusedParameterInspection
	 */
	private function write_item_csv( $f, bool $last_item ) {
		return fputcsv( $f, $this->item_handler->generate(), '|' );
	}

	/**
	 * Write item in sql format.
	 *
	 * @param resource $f         File.
	 * @param bool     $last_item Whether we process the last item in the file.
	 *
	 * @return false|int
	 */
	private function write_item_sql( $f, bool $last_item ) {
		global $wpdb;

		$fields = $this->item_handler->generate();

		foreach ( $fields as &$field ) {
			if ( is_string( $field ) ) {
				$field = "'" . $wpdb->_real_escape( $field ) . "'";
			}
		}

		unset( $field );

		$last_comma = $last_item ? '' : ',';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		return fwrite( $f, '(' . implode( ',', $fields ) . ')' . $last_comma );
	}

	/**
	 * Write file.
	 *
	 * @param string   $temp_filename Temporary filename.
	 * @param resource $f             File.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 * @noinspection SqlInsertValues
	 * @noinspection SqlResolve
	 */
	private function write_file( string $temp_filename, $f ) {
		if ( $this->download_sql ) {
			$table         = $this->item_handler->get_table();
			$fields        = implode( ', ', $this->item_handler->get_fields() );
			$file_contents = "INSERT INTO `$table` ($fields) VALUES " . stream_get_contents( $f ) . ";\n";
		} else {
			$file_contents = stream_get_contents( $f );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $temp_filename, $file_contents );

		if ( ! $result ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
					// translators: 1: Temp filename.
						esc_html__( 'Cannot write to the temporary file %s.', 'kagg-generator' ),
						$temp_filename
					)
				)
			);
		}
	}

	/**
	 * Store items in the database.
	 *
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function store_items( string $temp_filename ) {
		global $wpdb;

		if ( $this->download_sql ) {
			return;
		}

		$filename = str_replace( '\\', '/', $temp_filename );

		$fields = implode( ', ', $this->item_handler->get_fields() );

		$this->set_local_infile();

		$local = $this->use_local_infile ? 'LOCAL' : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"LOAD DATA $local INFILE %s INTO TABLE {$this->item_handler->get_table()}
                    FIELDS TERMINATED BY '|' ENCLOSED BY '\"'
					( $fields )",
				$filename
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $result ) {
			throw new RuntimeException( esc_html( $wpdb->last_error ) );
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
			throw new RuntimeException( esc_html( $wpdb->last_error ) );
		}

		$this->local_infile_value = $result['Value'] ?? '';

		if ( 'ON' !== $this->local_infile_value ) {
			$result = $wpdb->query( "SET GLOBAL local_infile = 'ON'" );

			if ( false === $result ) {
				throw new RuntimeException( esc_html( $wpdb->last_error ) );
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
				throw new RuntimeException( esc_html( $wpdb->last_error ) );
			}
		}
	}

	/**
	 * Get input value.
	 *
	 * @param array  $data Form data.
	 * @param string $name Input name.
	 *
	 * @return string
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function get_input( array $data, string $name ): string {
		foreach ( $data as $datum ) {
			if ( $datum->name === $name ) {
				return $datum->value;
			}
		}

		return '';
	}

	/**
	 * Get settings from input and option.
	 *
	 * @param array $data Form data.
	 *
	 * @return array
	 */
	private function get_settings( array $data ): array {
		$settings   = [];
		$option_key = Settings::OPTION_KEY;

		foreach ( $data as $datum ) {
			if ( preg_match( "/$option_key\[(.+)]/", $datum->name, $m ) ) {
				$settings[ $m[1] ] = $datum->value;
			}
		}

		return $settings;
	}

	/**
	 * Send HTTP headers for .sql file download.
	 */
	private function http_headers() {

		$file_name = 'kagg-generator.sql';

		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/sql' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );
		header( 'Content-Transfer-Encoding: binary' );
	}
}
