<?php
/**
 * Generator class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

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
		$this->run_checks();

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

		$time1 = $this->generate_posts( $count, $settings, $temp_filename );
		$time2 = $this->write_posts( $count, $settings, $temp_filename );

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
	 * @return void
	 */
	private function run_checks() {
		// Run a security check.
		if ( ! check_ajax_referer( Settings::GENERATE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'kagg-generator' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'kagg-generator' ) );
		}

		// Check for form data.
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
	 * @return float
	 */
	private function generate_posts( $count, $settings, $temp_filename ) {
		$start = microtime( true );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$f = fopen( 'php://temp', 'wb+' );

		for ( $i = 0; $i < $count; $i ++ ) {
			fputcsv( $f, $this->generate_post( $settings ) );
		}

		rewind( $f );

		$file_contents = stream_get_contents( $f );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		file_put_contents( $temp_filename, $file_contents );

		$end = microtime( true );

		return round( $end - $start, 3 );
	}

	/**
	 * Write posts to the database.
	 *
	 * @param int    $count         Number of posts to generate.
	 * @param array  $settings      Settings.
	 * @param string $temp_filename Temporary filename.
	 *
	 * @return float
	 */
	private function write_posts( $count, $settings, $temp_filename ) {
		global $wpdb;

		$start = microtime( true );

		$fname = str_replace( '\\', '/', $temp_filename );

		$fields = implode( ', ', $this->get_post_fields( $settings ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"LOAD DATA INFILE %s INTO TABLE $wpdb->posts
                    FIELDS TERMINATED BY ','
					( $fields )",
				$fname
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$end = microtime( true );

		return round( $end - $start, 3 );
	}

	/**
	 * Get post fields.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private function get_post_fields( $settings ) {
		$fields = [ 'post_content', 'post_title', 'post_excerpt', 'post_name', 'post_type' ];

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
		$content = $this->generate_random_string( 2048 );
		$title   = substr( $content, 0, 20 );

		$post = [
			'post_content' => $content,
			'post_title'   => $title,
			'post_excerpt' => substr( $content, 0, 100 ),
			'post_name'    => strtolower( $title ),
		];

		// Do not write default 'post' value.
		if ( 'post' !== $settings['post_type'] ) {
			$post['post_type'] = $settings['post_type'];
		}

		return $post;
	}

	/**
	 * Generate random string.
	 *
	 * @param int $length String length.
	 *
	 * @return string
	 */
	private function generate_random_string( $length = 10 ) {
		$s = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		return substr( str_shuffle( str_repeat( $s, ceil( $length / strlen( $s ) ) ) ), 0, $length );
	}
}
