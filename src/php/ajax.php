<?php
/**
 * Ajax action with SHORTINIT.
 *
 * @package kagg/generator
 */

/**
 * Set short WordPress init.
 */
const SHORTINIT = true;

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
$root = isset( $_SERVER['SCRIPT_FILENAME'] ) ? filter_var( $_SERVER['SCRIPT_FILENAME'], FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
$root = dirname( $root, 6 );

require $root . '/wp-load.php';

// Components needed for check_ajax_referer() to work.
require $root . '/wp-includes/capabilities.php';
require $root . '/wp-includes/class-wp-roles.php';
require $root . '/wp-includes/class-wp-role.php';
require $root . '/wp-includes/class-wp-user.php';
require $root . '/wp-includes/user.php';
require $root . '/wp-includes/class-wp-session-tokens.php';
require $root . '/wp-includes/class-wp-user-meta-session-tokens.php';
require $root . '/wp-includes/kses.php';
require $root . '/wp-includes/rest-api.php';
require $root . '/wp-includes/blocks.php';

wp_plugin_directory_constants();
wp_cookie_constants();

// Components needed for i18n to work properly.
wp_load_translations_early();

require $root . '/wp-includes/pluggable.php';

// Load generator class.
require '../../vendor/autoload.php';

( new \KAGG\Generator\Generator\Generator() )->run();
