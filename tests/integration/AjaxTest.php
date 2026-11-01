<?php
/**
 * Public WordPress AJAX handlers with normal nonce and permission checks.
 *
 * @package kagg/generator
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

namespace KAGG\Generator\Tests\Integration;

use KAGG\Generator\Generator\Comment;
use KAGG\Generator\Settings;
use ReflectionException;
use WPAjaxDieContinueException;

/**
 * Handles testing of WordPress AJAX functionality for custom import and generation processes.
 *
 * This class provides tests for WordPress AJAX handlers, validating proper nonce
 * verification, permission handling, and data processing within the AJAX request lifecycle.
 *
 * Features covered:
 * - JSON response validation of AJAX handlers.
 * - Validation of comment count updates after importing items.
 * - Verification of requested row generation through a generation handler.
 * - Protection against unauthorized requests with invalid nonces.
 *
 * The tests ensure the integrity and correctness of AJAX-based import and generation
 * processes in WordPress.
 */
class AjaxTest extends GeneratorTestCase {
	/**
	 * Executes an AJAX action and retrieves the JSON response.
	 *
	 * @param string $action The AJAX action to be executed.
	 *
	 * @return array The decoded JSON response as an associative array.
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection PhpRedundantCatchClauseInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	protected function request_json( string $action ): array {
		$handler = static function () {
			return static function (): void {
				throw new WPAjaxDieContinueException();
			};
		};

		add_filter( 'wp_die_ajax_handler', $handler );

		$level = ob_get_level();

		ob_start();

		try {
			do_action( 'wp_ajax_' . $action );
			self::fail( 'The AJAX handler did not send a JSON response.' );
		} catch ( WPAjaxDieContinueException $exception ) {
			$response = ob_get_contents();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			remove_filter( 'wp_die_ajax_handler', $handler );
		}

		self::assertJson( $response );

		return json_decode( $response, true );
	}

	/**
	 * Tests the functionality of updating comment counts for posts and verifies
	 * the accuracy of the updated data in the database.
	 *
	 * @return void
	 * @throws ReflectionException.
	 */
	public function test_updates_comment_counts(): void {
		global $wpdb;

		$importer = $this->importer( new Comment( 5 ) );

		$this->call_method( $importer, 'generate_items', [ 5, $this->file ] );
		$this->call_method( $importer, 'store_items', [ $this->file ] );

		$_REQUEST['nonce'] = wp_create_nonce( 'kagg-generator-update-comment-counts' );

		self::assertTrue( $this->request_json( 'kagg-generator-update-comment-counts' )['success'] );
		self::assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE comment_count != (SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = $wpdb->posts.ID)" ) );
		self::assertSame( 5, (int) $wpdb->get_var( "SELECT comment_count FROM $wpdb->posts WHERE ID = 1" ) );
		self::assertSame( '', $wpdb->last_error );
	}

	/**
	 * Tests the generation handler functionality to ensure it imports the requested rows correctly.
	 *
	 * This method prepares data representing a generation request, simulates a form submission with necessary
	 * parameters, and verifies that the number of imported rows in the database increases as expected after
	 * the request is processed.
	 *
	 * @return void
	 */
	public function test_generation_handler_imports_requested_rows(): void {
		global $wpdb;
		$data = [
			(object) [
				'name'  => 'generation_id',
				'value' => 'kagg-phpunit',
			],
		];
		foreach ( [
			'post_type'  => 'post',
			'number'     => '1',
			'chunk_size' => '1',
		] as $name => $value ) {
			$data[] = (object) [
				'name'  => Settings::OPTION_KEY . '[' . $name . ']',
				'value' => $value,
			];
		}
		$_POST['data']     = wp_slash( wp_json_encode( $data ) );
		$_POST['index']    = '0';
		$_REQUEST['nonce'] = wp_create_nonce( Settings::GENERATE_ACTION );
		$before            = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
		$response          = $this->request_json( Settings::GENERATE_ACTION );
		self::assertTrue( $response['success'], wp_json_encode( $response ) );
		self::assertSame( $before + 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ) );
	}

	/**
	 * Tests that the generation handler rejects requests with an invalid nonce and does not insert any rows.
	 *
	 * This method sets up a request with an invalid nonce, simulates the request processing,
	 * and verifies that no new rows are inserted into the database by comparing the row count before
	 * and after the request.
	 *
	 * @return void
	 */
	public function test_rejects_invalid_nonce_without_inserting_rows(): void {
		global $wpdb;
		$_REQUEST['nonce'] = 'invalid';
		$before            = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
		self::assertFalse( $this->request_json( Settings::GENERATE_ACTION )['success'] );
		self::assertSame( $before, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ) );
	}
}
