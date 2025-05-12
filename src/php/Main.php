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
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		( new ErrorHandler() )->init();
		( new Settings() )->init();

		$this->hooks();
	}

	/**
	 * Class hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain(): void {
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
