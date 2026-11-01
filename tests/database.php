<?php
/**
 * Database integration checks. Run only on a disposable WordPress installation.
 *
 * Run with wp --skip-plugins --skip-themes eval-file tests/database.php.
 *
 * @package kagg/generator
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection JsonEncodingApiUsageInspection */
/** @noinspection SqlResolve */
/** @noinspection PhpUnhandledExceptionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO

const WP_ADMIN = true;
require_once dirname( __DIR__ ) . '/plugin.php';

/**
 * Call a private generator method to test generation and storage independently.
 *
 * @param object $item Object under test.
 * @param string $name Method name.
 * @param array  $args Arguments.
 *
 * @return mixed
 * @throws ReflectionException ReflectionException.
 */
function kagg_test_call( object $item, string $name, array $args = [] ) {
	$method = new ReflectionMethod( $item, $name );

	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method->invokeArgs( $item, $args );
}

/**
 * Set the current generation state.
 *
 * @param object $item  Object under test.
 * @param string $name  Property name.
 * @param mixed  $value Value.
 *
 * @throws ReflectionException ReflectionException.
 */
function kagg_test_set( object $item, string $name, $value ): void {
	$property = new ReflectionProperty( $item, $name );

	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true );
	}
	$property->setValue( $item, $value );
}

/**
 * Require a successful check.
 *
 * @param bool   $condition Check result.
 * @param string $message   Failure description.
 *
 * @throws RuntimeException RuntimeException.
 * @noinspection PhpSingleStatementWithBracesInspection
 */
function kagg_test_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new RuntimeException( $message );
	}
}

global $wpdb;
wp_set_current_user( (int) $wpdb->get_var( "SELECT MIN(ID) FROM $wpdb->users" ) );
require __DIR__ . '/comment-dates.php';
$generator = new KAGG\Generator\Generator\Generator();
$settings  = new KAGG\Generator\Settings();
$file      = tempnam( sys_get_temp_dir(), 'kagg-test-' );
$count     = 205; // Two full INSERT batches and a remainder.
kagg_test_set( $generator, 'download_sql', false );
kagg_test_set( $generator, 'use_local_infile', $generator->use_local_infile() );

// Test actual generated rows, including all fields and identifiers.
foreach ( $generator->get_registered_items() as $item_type => $classname ) {
	$item   = new $classname( $count );
	$table  = $item->get_table();
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
	kagg_test_set( $generator, 'item_handler', $item );
	kagg_test_call( $generator, 'generate_items', [ $count, $file ] );
	kagg_test_call( $generator, 'store_items', [ $file ] );
	kagg_test_check( $before + $count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ), 'Incorrect stored count for ' . $item_type );
	kagg_test_check( '' === $wpdb->last_error, $wpdb->last_error );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo 'Stored ' . $count . ' ' . $item_type . " rows.\n";
}

if ( $generator->is_sqlite() ) {
	kagg_test_check( ! $generator->use_local_infile(), 'SQLite requested MySQL configuration.' );

	// CSV, wpdb escaping and native SQLite SQL export must preserve difficult text.
	$text = "O'Reilly \"quoted\" | 100% \\path\\\nUnicode: Rīga 😀";
	$item = new class( $text ) extends KAGG\Generator\Generator\Post {
		/**
		 * The text to be stored in the object.
		 *
		 * @var string
		 */
		private string $text;

		/**
		 * Constructor method.
		 *
		 * @param string $text The text to initialize the object with.
		 *
		 * @return void
		 */
		public function __construct( string $text ) {
			$this->text = $text;
			parent::__construct();
		}

		/**
		 * Generates and returns an array with modified content.
		 *
		 * @return array The generated array with added post content.
		 */
		public function generate(): array {
			$row                 = parent::generate();
			$row['post_content'] = $this->text;

			return $row;
		}
	};
	kagg_test_set( $generator, 'item_handler', $item );
	kagg_test_call( $generator, 'generate_items', [ 1, $file ] );
	kagg_test_call( $generator, 'store_items', [ $file ] );
	kagg_test_check( $text === $wpdb->get_var( "SELECT post_content FROM $wpdb->posts ORDER BY ID DESC LIMIT 1" ), 'CSV import changed text.' );

	kagg_test_set( $generator, 'download_sql', true );
	kagg_test_call( $generator, 'generate_items', [ 1, $file ] );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$sql    = file_get_contents( $file );
	$native = new PDO( 'sqlite::memory:' );
	$fields = '`' . implode( '` TEXT, `', $item->get_fields() ) . '` TEXT';
	$native->exec( "CREATE TABLE `$wpdb->posts` ($fields)" );
	$native->exec( $sql );
	kagg_test_check(
		$text === $native->query( "SELECT post_content FROM `$wpdb->posts`" )
		->fetchColumn(),
		'SQL export changed text.'
	);
	kagg_test_set( $generator, 'download_sql', false );

	// Force the second INSERT to fail and require rollback of the first batch.
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
	kagg_test_call( $generator, 'generate_items', [ $count, $file ] );
	$inserts = 0;
	$fail    = static function ( string $query ) use ( &$inserts, $wpdb ): string {
		if ( 2 === ++$inserts && 0 === strpos( $query, "INSERT INTO `$wpdb->posts`" ) ) {
			return 'INSERT INTO kagg_missing_table VALUES (1)';
		}

		return $query;
	};
	add_filter( 'query', $fail );
	$previous = $wpdb->suppress_errors();
	$failed   = false;
	try {
		kagg_test_call( $generator, 'store_items', [ $file ] );
	} catch ( RuntimeException $exception ) {
		$failed = false !== strpos( $exception->getMessage(), 'kagg_missing_table' );
	} finally {
		remove_filter( 'query', $fail );
		$wpdb->suppress_errors( $previous );
	}
	kagg_test_check( $failed, 'Import did not report the original error.' );
	kagg_test_check( $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ), 'A failed chunk left partial rows.' );
	echo "SQLite escaping, SQL export and rollback checks passed.\n";
}

// Exercise the public count-update handler with its normal permission checks.
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function () {
			throw new RuntimeException( 'kagg-test-json-response' );
		};
	}
);
$settings->init();
$_REQUEST['nonce'] = wp_create_nonce( 'kagg-generator-update-comment-counts' );
ob_start();
try {
	$settings->update_comment_counts();
} catch ( RuntimeException $exception ) {
	kagg_test_check( 'kagg-test-json-response' === $exception->getMessage(), $exception->getMessage() );
}
$response = json_decode( ob_get_clean(), true );
kagg_test_check( ! empty( $response['success'] ), 'Comment count update failed.' );
$mismatches = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE comment_count != (SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = $wpdb->posts.ID)" );
kagg_test_check( 0 === (int) $mismatches, 'Comment counts are incorrect.' );
kagg_test_check( '' === $wpdb->last_error, $wpdb->last_error );

// Also run the complete generation handler, including request format and mode selection.
$data = [
	(object) [
		'name'  => 'generation_id',
		'value' => 'kagg-test',
	],
];
foreach ( [
	'post_type'  => 'post',
	'number'     => '1',
	'chunk_size' => '1',
] as $name => $value ) {
	$data[] = (object) [
		'name'  => 'kagg_generator_settings[' . $name . ']',
		'value' => $value,
	];
}
$_POST['data']     = wp_slash( wp_json_encode( $data ) );
$_POST['index']    = '0';
$_REQUEST['nonce'] = wp_create_nonce( KAGG\Generator\Settings::GENERATE_ACTION );
ob_start();
try {
	( new KAGG\Generator\Generator\Generator() )->run();
} catch ( RuntimeException $exception ) {
	kagg_test_check( 'kagg-test-json-response' === $exception->getMessage(), $exception->getMessage() );
}
$response = json_decode( ob_get_clean(), true );
kagg_test_check( ! empty( $response['success'] ), 'Generation handler failed: ' . wp_json_encode( $response ) );

// Deletion must preserve existing records, schema and indexes.
foreach ( $generator->get_registered_items() as $classname ) {
	$item   = new $classname();
	$table  = $item->get_table();
	$marker = $item->get_marker_field();
	$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE `$marker` NOT LIKE %s", KAGG\Generator\Settings::MARKER . '%' ) );
	kagg_test_call( $settings, 'delete_items', [ $item ] );
	kagg_test_check( $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ), 'Deletion removed existing records or kept generated rows.' );
	if ( $generator->is_sqlite() ) {
		kagg_test_check( (bool) $wpdb->get_results( "SHOW INDEX FROM `$table`" ), 'Deletion lost table indexes.' );
	}
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
unlink( $file );

echo "Database integration checks passed.\n";
