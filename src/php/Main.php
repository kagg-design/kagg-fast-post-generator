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
	public function init() {
		( new Settings() )->init();
	}
}
