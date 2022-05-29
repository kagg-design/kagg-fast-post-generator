<?php
/**
 * Item class file.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator\Generator;

/**
 * Class Item.
 */
abstract class Item {

	/**
	 * Item type.
	 *
	 * @var string
	 */
	protected $item_type;

	/**
	 * Item DB table name without prefix.
	 *
	 * @var string
	 */
	protected $table = 'posts';

	/**
	 * Item DB table field name containing added items' marker.
	 *
	 * @var string
	 */
	protected $marker_field = 'guid';

	/**
	 * Item stub.
	 *
	 * @var array
	 */
	protected $item_stub = [];

	/**
	 * Class constructor.
	 *
	 * @param string $item_type Post type.
	 */
	public function __construct( $item_type = 'post' ) {
		global $wpdb;

		$this->item_type = $this->item_type ?: $item_type;
		$this->table     = $wpdb->prefix . $this->table;

		$this->prepare_stub();
	}

	/**
	 * Get item type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->item_type;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table() {
		return $this->table;
	}

	/**
	 * Get marker field name.
	 *
	 * @return string
	 */
	public function get_marker_field() {
		return $this->marker_field;
	}

	/**
	 * Get item fields.
	 *
	 * @return array
	 */
	public function get_item_fields() {
		return array_keys( $this->item_stub );
	}

	/**
	 * Prepare item stub.
	 *
	 * @return void
	 */
	abstract public function prepare_stub();

	/**
	 * Generate item.
	 *
	 * @return array
	 */
	abstract public function generate();
}
