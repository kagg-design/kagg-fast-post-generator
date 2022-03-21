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

		wp_send_json_success(
			sprintf(
				'Step %1$d/%2$d. %3$d/%4$d posts generated. Time used (%5$s + %6$s) = %7$s sec.',
				$step,
				$steps,
				min( $step * $chunk_size, $number ),
				$number,
				$time1,
				$time2,
				$time1 + $time2
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
		if ( ! check_ajax_referer( Settings::ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session expired. Please reload the page.', 'kagg-generator' ) );
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
			fputcsv( $f, $this->kagg_generate_post( $settings ) );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"LOAD DATA INFILE %s INTO TABLE $wpdb->posts
                    FIELDS TERMINATED BY ','
					( post_content, post_title, post_excerpt, post_name, post_type )",
				$fname
			)
		);

		$end = microtime( true );

		return round( $end - $start, 3 );
	}

	/**
	 * Generate post.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	private function kagg_generate_post( $settings ) {
		$content = $this->kagg_generate_random_string( 2048 );
		$title   = substr( $content, 0, 20 );

		return [
			'post_content' => $content,
			'post_title'   => $title,
			'post_excerpt' => substr( $content, 0, 100 ),
			'post_name'    => strtolower( $title ),
			'post_type'    => $settings['post_type'],
		];
	}

	/**
	 * Generate random string.
	 *
	 * @param int $length String length.
	 *
	 * @return string
	 */
	private function kagg_generate_random_string( $length = 10 ) {
		$s = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		return substr( str_shuffle( str_repeat( $s, ceil( $length / strlen( $s ) ) ) ), 0, $length );
	}
}
