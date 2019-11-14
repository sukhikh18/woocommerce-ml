<?php

namespace NikolayS93\Exchanger\Model;

use NikolayS93\Exchanger\Model\Interfaces\ExternalCode;
use NikolayS93\Exchanger\Model\Interfaces\Identifiable;
use NikolayS93\Exchanger\Model\Interfaces\Taxonomy;
use NikolayS93\Exchanger\Model\Interfaces\Value;

use NikolayS93\Exchanger\ORM\Collection;
use function NikolayS93\Exchanger\esc_cyr;

/**
 * Works with woocommerce_attribute_taxonomies
 */
class Attribute implements Taxonomy, ExternalCode, Identifiable, Value {

	/**
	 * @todo
	 */
	static function valid_attribute_name() {
		return true;
	}

	private $id;

	private $attribute_name;
	private $attribute_label;
	private $attribute_type = 'select';
	private $attribute_orderby = 'menu_order';
	private $attribute_public = 1;
	private $attribute_value = '';

	private $ext;

	/**
	 * @var Collection of ExchangeTerm
	 */
	private $values;

	function __set( $name, $value ) {
		// TODO: Implement __set() method.
	}

	public static function sanitize_slug( $str ) {
		if ( 0 !== strpos( $str, 'pa_' ) ) {
			return \wc_attribute_taxonomy_name( $str );
		} else {
			return \wc_sanitize_taxonomy_name( $str );
		}
	}

	function __construct( $args = array(), $ext = '' ) {
		$this->ext = $ext;

		foreach ( (array) $args as $k => $arg ) {
			$this->$k = $arg;
		}

		$name = esc_cyr( $this->attribute_name ? $this->attribute_name : $this->attribute_label );
		$this->set_slug( $name );

		$this->reset_values();
	}

	/**
	 * Object params to array
	 * @return array
	 */
	public function fetch() {
		$attribute = array(
			'slug'         => str_replace( 'pa_', '', $this->attribute_name ),
			'name'         => $this->attribute_label,
			'type'         => $this->attribute_type,
			'order_by'     => $this->attribute_orderby,
			'has_archives' => $this->attribute_public,
		);

		return $attribute;
	}

	public function get_slug() {
		return $this->attribute_name;
	}

	public function set_slug( $slug ) {
		$this->attribute_name = static::sanitize_slug( $slug );
	}

	public function get_name() {
		return $this->attribute_label;
	}

	public function get_type() {
		return $this->attribute_type;
	}

	/**
	 * @param AttributeValue $term
	 */
	public function add_value( $term ) {
		$term->set_taxonomy( $this->attribute_name );

		$this->values->add( $term );
	}

	public function get_values() {
		return $this->values;
	}

	public function reset_values() {
		$this->values = new Collection();
	}

	public function get_value() {
		return $this->attribute_value;
	}

	public function set_value( $value ) {
		if ( $value instanceof Category ) {
			$this->attribute_value = $value;
		} else {
			$this->attribute_value = ( $relationTerm = $this->get_values()->offsetGet( $value ) )
				? $relationTerm : (string) $value;
		}
	}

	public function get_id() {
		return (int) $this->id;
	}

	public function set_id( $id ) {
		$this->id = (int) $id;
	}

	static function get_external_key() {
		return apply_filters( 'Attribute::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
	}

	public function get_external() {
		return $this->ext;
	}

	public function get_raw_external() {
	}

	public function set_external( $ext ) {
		$this->ext = (String) $ext;
	}
}
