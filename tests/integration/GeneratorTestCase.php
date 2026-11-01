<?php
/**
 * WordPress fixtures for import tests that own transactions and replace tables.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Tests\Integration;

use Exception;
use KAGG\Generator\Generator\Generator;
use KAGG\Generator\Generator\Item;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use WP_UnitTestCase;

/**
 * Abstract base class for creating disposable test data and managing test cases
 * involving database interactions and file generation.
 */
abstract class GeneratorTestCase extends WP_UnitTestCase {
	/**
	 * File.
	 *
	 * @var string
	 */
	protected string $file;

	/**
	 * Original tables.
	 *
	 * @var array
	 */
	private array $original_tables = [];

	/**
	 * An array to hold table data or table definitions.
	 *
	 * @var array
	 */
	private array $tables = [];

	/**
	 * Request.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Create disposable tables, an administrator fixture and a temporary import file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		// Core rewrites DDL to temporary tables, which MySQL cannot self-join or rename.
		remove_filter( 'query', [ $this, '_create_temporary_tables' ] );
		remove_filter( 'query', [ $this, '_drop_temporary_tables' ] );
		add_filter( 'wp_die_ajax_handler', [ $this, 'get_wp_die_handler' ] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$this->request = [ $_POST, $_GET, $_REQUEST ];
		$this->file    = tempnam( sys_get_temp_dir(), 'kagg-phpunit-' );

		require_once ABSPATH . 'wp-admin/includes/schema.php';

		$schema = wp_get_db_schema();

		try {
			$random_bytes = random_bytes( 5 );
		} catch ( Exception $e ) {
			$random_bytes = '';
		}

		$prefix                          = 'kagg_' . bin2hex( $random_bytes ) . '_';
		$this->original_tables['prefix'] = $wpdb->prefix;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( [ 'posts', 'comments', 'users', 'usermeta' ] as $name ) {
			$original = $wpdb->$name;
			self::assertSame( 1, preg_match( '/CREATE TABLE ' . preg_quote( $original, '/' ) . '\s*\(.*?;/s', $schema, $matches ) );
			$table                          = $prefix . $name;
			$this->tables[]                 = $table;
			$this->original_tables[ $name ] = $original;
			self::assertNotFalse( $wpdb->query( str_replace( 'CREATE TABLE ' . $original, 'CREATE TABLE ' . $table, $matches[0] ) ), $wpdb->last_error );
			$wpdb->$name = $table;
		}

		$wpdb->prefix = $prefix;
		wp_cache_flush();
		self::assertNotFalse(
			$wpdb->insert(
				$wpdb->users,
				[
					'ID'            => 1,
					'user_login'    => 'fixture',
					'user_nicename' => 'fixture',
					'user_pass'     => 'unused',
					'user_email'    => 'fixture@example.org',
					'display_name'  => 'Fixture',
				]
			),
			$wpdb->last_error
		);

		wp_set_current_user( 0 );
		wp_set_current_user( 1 )->add_cap( 'manage_options' );

		$this->insert_post( 1, time() - 2 * YEAR_IN_SECONDS );

		// Imports issue their own COMMIT/ROLLBACK and deletion uses DDL. These cannot
		// be isolated by WP_UnitTestCase's outer transaction; use disposable tables.
		$wpdb->query( 'COMMIT' );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Remove disposable tables and files, then restore WordPress and request state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		try {
			foreach ( $this->tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS {$table}_copy" );
				$wpdb->query( "DROP TABLE IF EXISTS $table" );
			}
		} finally {
			foreach ( $this->original_tables as $name => $table ) {
				$wpdb->$name = $table;
			}

			wp_set_current_user( 0 );
			wp_cache_flush();

			if ( isset( $this->file ) && is_file( $this->file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $this->file );
			}

			if ( $this->request ) {
				[ $_POST, $_GET, $_REQUEST ] = $this->request;
			}

			// Restore the transaction expected by the WordPress test teardown.
			$wpdb->query( 'START TRANSACTION' );

			parent::tear_down();
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert an unmarked post fixture with matching local and GMT publication dates.
	 *
	 * @param int    $id        Identifier assigned to the fixture post.
	 * @param int    $timestamp Publication time as a Unix timestamp.
	 * @param string $status    WordPress publication status for the fixture.
	 *
	 * @return void
	 */
	protected function insert_post( int $id, int $timestamp, string $status = 'publish' ): void {
		global $wpdb;

		self::assertNotFalse(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->posts,
				[
					'ID'                    => $id,
					'post_date'             => wp_date( 'Y-m-d H:i:s', $timestamp ),
					'post_date_gmt'         => gmdate( 'Y-m-d H:i:s', $timestamp ),
					'post_type'             => 'post',
					'post_status'           => $status,
					'post_title'            => 'Fixture',
					'guid'                  => 'https://example.org/fixture-' . $id,
					'post_content'          => '',
					'post_excerpt'          => '',
					'to_ping'               => '',
					'pinged'                => '',
					'post_content_filtered' => '',
				]
			),
			$wpdb->last_error
		);
	}

	/**
	 * Configure a generator for importing or exporting the supplied item handler.
	 *
	 * @param Item $item   Item handler used to generate rows.
	 * @param bool $export Whether to generate SQL instead of importing CSV.
	 *
	 * @return Generator Configured generator instance.
	 * @throws RuntimeException When MySQL import settings cannot be read.
	 * @throws ReflectionException
	 */
	protected function importer( Item $item, bool $export = false ): Generator {
		$generator = new Generator();
		$this->set_property( $generator, 'item_handler', $item );
		$this->set_property( $generator, 'download_sql', $export );
		$this->set_property( $generator, 'use_local_infile', $generator->use_local_infile() );

		return $generator;
	}

	/**
	 * Invoke a non-public method to test generation and storage independently.
	 *
	 * @param object $test_object Object containing the method under test.
	 * @param string $name        Method name to invoke.
	 * @param array  $args        Positional arguments passed to the method.
	 *
	 * @return mixed Value returned by the invoked method.
	 * @throws ReflectionException When the method cannot be resolved or invoked.
	 */
	protected function call_method( object $test_object, string $name, array $args = [] ) {
		$method = new ReflectionMethod( $test_object, $name );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invokeArgs( $test_object, $args );
	}

	/**
	 * Set a non-public property to prepare the generator state for a test.
	 *
	 * @param object $test_object Object whose property should be updated.
	 * @param string $name        Property name to update.
	 * @param mixed  $value       Value assigned to the property.
	 *
	 * @return void
	 * @throws ReflectionException When the property cannot be resolved.
	 */
	protected function set_property( object $test_object, string $name, $value ): void {
		$property = new ReflectionProperty( $test_object, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $test_object, $value );
	}

	/**
	 * Provide all registered item types for generation, import and deletion tests.
	 *
	 * @return array Named test cases containing a registered item type.
	 */
	public static function item_types(): array {
		return [
			'post'    => [ 'post' ],
			'page'    => [ 'page' ],
			'comment' => [ 'comment' ],
			'user'    => [ 'user' ],
		];
	}
}
