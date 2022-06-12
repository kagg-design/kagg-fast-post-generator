<?php
/**
 * Fast post generator.
 *
 * @package           kagg/generator
 * @author            KAGG Design
 * @license           GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:       KAGG Fast Post Generator
 * Plugin URI:        https://wordpress.org/plugins/kagg-fast-post-generator/
 * Description:       Generates posts/pages. Useful to generate millions of records in wp_posts table.
 * Version:           1.4.0
 * Requires at least: 5.3
 * Requires PHP:      5.6
 * Author:            KAGG Design
 * Author URI:        https://profiles.wordpress.org/kaggdesign/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kagg-generator
 * Domain Path:       /languages/
 */

namespace KAGG\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

if ( defined( 'KAGG_GENERATOR_VERSION' ) ) {
	return;
}

/**
 * Plugin version.
 */
define( 'KAGG_GENERATOR_VERSION', '1.4.0' );

/**
 * Path to the plugin dir.
 */
define( 'KAGG_GENERATOR_PATH', __DIR__ );

/**
 * Plugin dir url.
 */
define( 'KAGG_GENERATOR_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 */
define( 'KAGG_GENERATOR_FILE', __FILE__ );

/**
 * Init plugin on plugin load.
 */
require_once constant( 'KAGG_GENERATOR_PATH' ) . '/vendor/autoload.php';

( new Main() )->init();
