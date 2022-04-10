<?php
/**
 * Main class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

/**
 * Class Main.
 */
class Main {

	/**
	 * AdminNotices instance.
	 *
	 * @var AdminNotices
	 */
	private $admin_notices;

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}

		$this->admin_notices = new AdminNotices();
		$this->requirements();

		( new Settings() )->init();

		$this->hooks();
	}

	/**
	 * Show requirements notice.
	 *
	 * @return void
	 */
	private function requirements() {
		// Show notice on plugin activation only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['activate'] ) ) {
			return;
		}

		if ( ! ( new Generator() )->use_local_infile() ) {
			return;
		}

		if ( ini_get( 'mysqli.allow_local_infile' ) ) {
			return;
		}

		$this->admin_notices->add_notice(
			__( 'To work properly on your server, the KAGG Fast Post Generator plugin needs `mysqli.allow_local_infile = On` set in the php.ini file.', 'kagg-generator' ) .
			'<br>' .
			__( 'Ask your hosting provider to set this configuration option.', 'kagg-generator' ),
			'notice notice-error'
		);
	}

	/**
	 * Class hooks.
	 *
	 * @return void
	 */
	private function hooks() {
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		global $l10n;

		$domain = 'kagg-generator';

		if ( isset( $l10n[ $domain ] ) ) {
			return;
		}

		load_plugin_textdomain(
			$domain,
			false,
			dirname( plugin_basename( KAGG_GENERATOR_FILE ) ) . '/languages/'
		);
	}
}
