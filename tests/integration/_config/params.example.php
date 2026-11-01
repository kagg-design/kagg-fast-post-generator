<?php
/**
 * Copy to params.local.php and point at a dedicated test database.
 *
 * @package kagg/generator
 */

return [
	'WP_ROOT_PATH'    => 'C:/path/to/wordpress',
	'DB_HOST'         => '127.0.0.1',
	'DB_NAME'         => 'kagg_generator_tests',
	'DB_USER'         => 'root',
	'DB_PASSWORD'     => '',
	'DB_TABLE_PREFIX' => 'kaggtests_',
];
