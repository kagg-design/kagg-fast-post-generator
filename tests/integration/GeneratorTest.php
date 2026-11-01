<?php
/**
 * Plugin loading, generated rows, actual imports and deletion.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Tests\Integration;

use KAGG\Generator\Generator\Generator;
use KAGG\Generator\Settings;
use ReflectionException;

/**
 * Class GeneratorTest
 *
 * Extends the GeneratorTestCase class to verify core functionality related to
 * data generation, plugin hooks, and database operations within the plugin.
 */
class GeneratorTest extends GeneratorTestCase {
	/**
	 * Verify plugin loading registers the administrative generation AJAX action.
	 *
	 * @return void
	 */
	public function test_plugin_registers_admin_generation_hook(): void {
		self::assertTrue( function_exists( 'kagg_generator' ) );
		self::assertNotFalse( has_action( 'wp_ajax_' . Settings::GENERATE_ACTION ) );
	}

	/**
	 * Verify generated rows follow the field order, item type and marker contract.
	 *
	 * @dataProvider item_types
	 *
	 * @param string $type Registered item type to generate.
	 *
	 * @return void
	 */
	public function test_generates_rows_with_the_declared_fields_and_marker( string $type ): void {
		$class = ( new Generator() )->get_registered_items()[ $type ];
		$item  = new $class( 5 );

		self::assertSame( $type, $item->get_type() );

		for ( $index = 0; $index < 5; ++$index ) {
			$row = $item->generate();

			self::assertSame( $item->get_fields(), array_keys( $row ) );
			self::assertStringStartsWith( Settings::MARKER, $row[ $item->get_marker_field() ] );

			if ( isset( $row['post_type'] ) ) {
				self::assertSame( $type, $row['post_type'] );
			}
		}
	}

	/**
	 * Verify complete imports and deletion preserve existing records and table indexes.
	 *
	 * @dataProvider item_types
	 *
	 * @param string $type Registered item type to import and delete.
	 *
	 * @return void
	 * @throws ReflectionException
	 */
	public function test_imports_all_batches_and_deletes_only_generated_records( string $type ): void {
		global $wpdb;

		$class = ( new Generator() )->get_registered_items()[ $type ];
		$item  = new $class( 205 ); // Two SQLite INSERT batches and a remainder.
		$table = $item->get_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 'comment' === $type ) {
			self::assertNotFalse(
				$wpdb->insert(
					$table,
					[
						'comment_post_ID' => 1,
						'comment_content' => 'Fixture',
						'comment_agent'   => 'fixture',
					]
				)
			);
		}

		$before   = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
		$indexes  = $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A );
		$importer = $this->importer( $item );

		$this->call_method( $importer, 'generate_items', [ 205, $this->file ] );
		$this->call_method( $importer, 'store_items', [ $this->file ] );

		self::assertSame( count( $before ) + 205, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) );
		self::assertSame( '', $wpdb->last_error );

		$settings = new Settings();
		$settings->init();

		self::assertTrue( $this->call_method( $settings, 'delete_items', [ $item ] ) );
		self::assertSame( $before, $wpdb->get_results( "SELECT * FROM $table", ARRAY_A ), 'Deletion must preserve unmarked rows.' );

		$after_indexes = $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		self::assertNotEmpty( $after_indexes );
		self::assertSame( array_column( $indexes, 'Key_name' ), array_column( $after_indexes, 'Key_name' ) );
		self::assertFalse( $this->call_method( $settings, 'delete_items', [ $item ] ), 'Deleting twice must be harmless.' );
	}
}
