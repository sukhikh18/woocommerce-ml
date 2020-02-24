<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Interfaces\Taxonomy;
use NikolayS93\Exchange\Model\Interfaces\Value;
use NikolayS93\Exchange\ORM\Collection;

/**
 * Works with woocommerce_attribute_taxonomies
 */
class Attribute implements ExternalCode { // implements Taxonomy, Identifiable, Value
	private $ext;

	private $name;
	private $label;
	private $type = 'text';
	private $orderby = 'menu_order';
	private $public = 1;

	/**
	 * @var Collection of Terms
	 */
	private $values;

	public static function sanitize_slug( $str ) {
		return ( 0 !== strpos( $str, 'pa_' ) ) ? \wc_attribute_taxonomy_name( $str ) :
			\wc_sanitize_taxonomy_name( $str );
	}

	static function get_external_key() {
		return apply_filters( 'Attribute::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
	}

	public function get_external() {
		return $this->ext;
	}

	function get_raw_external() {
		return $this->ext;
	}

	public function set_external( $ext = '' ) {
		if( $ext ) {
			$this->ext = $ext;
		}
	}

	public function get_type() {
		return $this->type;
	}

	function __construct( $args = array(), $ext = '' ) {
		$this->values = new Collection();

		foreach ( (array) $args as $k => $arg ) {
			$this->$k = $arg;
		}

		$this->set_external( $ext );
	}

	public function get_slug() {
		return static::sanitize_slug( $this->name );
	}

	/**
	 * Object params to array
	 * @return array
	 */
	public function fetch() {
		$attribute = array(
			'slug'         => $this->get_slug(),
			'name'         => $this->label,
			'type'         => $this->type,
			'order_by'     => $this->orderby,
			'has_archives' => $this->public,
		);

		return $attribute;
	}

	public function add_value( $AttributeValue ) {
		$this->values->add( $AttributeValue );

		return $this;
	}

	public function get_values() {
		return $this->values;
	}
}
