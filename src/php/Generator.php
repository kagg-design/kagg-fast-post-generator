<?php
/**
 * Generator class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

use RuntimeException;

/**
 * Class Generator.
 */
class Generator {

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		$this->run_checks( Settings::GENERATE_ACTION, true );

		ob_start();

		$data       = json_decode(
			html_entity_decode( filter_input( INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ),
			false
		);
		$settings   = [];
		$option_key = Settings::OPTION_KEY;

		foreach ( $data as $datum ) {
			if ( preg_match( "/$option_key\[(.+)]/", $datum->name, $m ) ) {
				$settings[ $m[1] ] = $datum->value;
			}
		}

		$index         = filter_input( INPUT_POST, 'index', FILTER_VALIDATE_INT );
		$chunk_size    = (int) $settings['chunk_size'];
		$number        = (int) $settings['number'];
		$count         = min( $number - $index, $chunk_size );
		$step          = (int) floor( $index / $chunk_size ) + 1;
		$steps         = (int) ceil( $number / $chunk_size );
		$temp_filename = tempnam( sys_get_temp_dir(), 'kagg-generator-' );
		$error         = false;

		try {
			$start = microtime( true );
			$this->generate_posts( $count, $settings, $temp_filename );
			$end   = microtime( true );
			$time1 = round( $end - $start, 3 );

			$start = microtime( true );
			$this->write_posts( $settings, $temp_filename );
			$end   = microtime( true );
			$time2 = round( $end - $start, 3 );

			$error_message = ob_get_clean();
		} catch ( RuntimeException $ex ) {
			$error = true;

			// We will have some messages here if WP_DEBUG_DISPLAY is on.
			$error_message = ob_get_clean() . $ex->getMessage();
		}

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

		unlink( $temp_filename );

		wp_send_json_success(
			sprintf(
			// translators: 1: Step. 2: Steps. 3: Generated posts. 4: Total posts to generate. 5: Generation time. 6: DB storing time. 7: Total time.
				esc_html__( 'Step %1$s/%2$s. %3$s/%4$s posts generated. Time used: (generate: %5$s + store: %6$s) = %7$s sec.', 'kagg-generator' ),
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
	 * Generate posts.
	 *
	 * @param int    $count         Number of posts to generate.
	 * @param array  $settings      Settings.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function generate_posts( $count, $settings, $temp_filename ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$f = fopen( 'php://temp', 'wb+' );

		if ( ! $f ) {
			throw new RuntimeException( esc_html__( 'Cannot create a temporary php://temp file.', 'kagg-generator' ) );
		}

		for ( $i = 0; $i < $count; $i ++ ) {
			$result = fputcsv( $f, $this->generate_post( $settings ), '|' );

			if ( ! $result ) {
				throw new RuntimeException( esc_html__( 'Cannot write to a temporary php://temp file.', 'kagg-generator' ) );
			}
		}

		rewind( $f );

		$file_contents = stream_get_contents( $f );

		$result = chmod( $temp_filename, 0644 );

		if ( ! $result ) {
			throw new RuntimeException(
				sprintf(
				// translators: 1: Temp filename.
					esc_html__( 'Cannot set permissions to the temporary file %s.', 'kagg-generator' ),
					$temp_filename
				)
			);
		}

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
	 * Write posts to the database.
	 *
	 * @param array  $settings      Settings.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return void
	 * @throws RuntimeException With error message.
	 */
	private function write_posts( $settings, $temp_filename ) {
		global $wpdb;

		$fname = str_replace( '\\', '/', $temp_filename );

		$fields = implode( ', ', $this->get_post_fields( $settings ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"LOAD DATA INFILE %s INTO TABLE $wpdb->posts
                    FIELDS TERMINATED BY '|' ENCLOSED BY '\"'
					( $fields )",
				$fname
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $result ) {
			throw new RuntimeException( $wpdb->last_error );
		}
	}

	/**
	 * Get post fields.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private function get_post_fields( $settings ) {
		$fields = [ 'post_content', 'post_title', 'post_excerpt', 'post_name', 'guid', 'post_type' ];

		// Do not proceed with default column values.
		if ( 'post' === $settings['post_type'] ) {
			$fields = array_diff( $fields, [ 'post_type' ] );
		}

		return $fields;
	}

	/**
	 * Generate post.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private function generate_post( $settings ) {
		$content = implode( "\r\r", Lorem::paragraphs( 12 ) );
		$title   = substr( Lorem::sentence( 5 ), 0, -1 );

		$post = [
			'post_content' => $content,
			'post_title'   => $title,
			'post_excerpt' => substr( $content, 0, 100 ),
			'post_name'    => str_replace( ' ', '-', strtolower( $title ) ),
			'guid'         => Settings::GUID . $title,
		];

		// Do not write default 'post' value.
		if ( 'post' !== $settings['post_type'] ) {
			$post['post_type'] = $settings['post_type'];
		}

		return $post;
	}
}
