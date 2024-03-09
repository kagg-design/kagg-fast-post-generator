<?php
/**
 * Randomizer class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

/**
 * Class Randomizer.
 */
class Randomizer {

	/**
	 * Elements keys.
	 *
	 * @var array
	 */
	private $keys = [];

	/**
	 * Elements.
	 *
	 * @var array
	 */
	private $elements;

	/**
	 * Elements count.
	 *
	 * @var int
	 */
	private $count;

	/**
	 * Current index in elements array.
	 *
	 * @var int
	 */
	private $index = 0;

	/**
	 * CLass constructor.
	 *
	 * @param array $elements Elements to randomize.
	 */
	public function __construct( array $elements ) {
		$elements       = $elements ?: [ 0 ];
		$this->elements = $elements;
		$this->count    = count( $this->elements );

		$this->prepare_random_keys();
	}

	/**
	 * Get elements.
	 *
	 * @param int $quantity Number of elements to get.
	 *
	 * @return array
	 * @noinspection RandomApiMigrationInspection
	 */
	public function get( int $quantity = 1 ): array {
		$quantity = min( $quantity, $this->count );

		if ( ( $this->index + $quantity ) > $this->count ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$this->index = mt_rand( 0, max( $this->count - $quantity, 0 ) );
		}

		$result    = [];
		$max_index = $this->index + $quantity;

		while ( $this->index < $max_index ) {
			$result[] = $this->elements[ $this->keys[ $this->index ] ];
			++$this->index;
		}

		return $result;
	}

	/**
	 * Get count.
	 *
	 * @return int
	 */
	public function count(): int {
		return $this->count;
	}

	/**
	 * Prepare random keys.
	 *
	 * @noinspection RandomApiMigrationInspection*/
	private function prepare_random_keys() {
		for ( $i = 0; $i < $this->count; $i++ ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$this->keys[] = mt_rand( 0, $this->count - 1 );
		}
	}
}
