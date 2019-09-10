<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Interfaces\Taxonomy;
use NikolayS93\Exchange\Model\Interfaces\Value;

use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\esc_cyr;
use const NikolayS93\Exchange\EXTERNAL_CODE_KEY;

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
	private $terms;

	function __set( $name, $value ) {
		// TODO: Implement __set() method.
	}

	public static function sanitize_slug( $str ) {
		if ( 0 !== strpos( $str, 'pa_' ) ) {
			return wc_attribute_taxonomy_name( $str );
		} else {
			return wc_sanitize_taxonomy_name( $str );
		}
	}

	function __construct( $args = array(), $ext = '' ) {
		$this->ext = $ext;

		foreach ( (array) $args as $k => $arg ) {
			$this->$k = $arg;
		}

		$name = esc_cyr( $this->attribute_name ? $this->attribute_name : $this->attribute_label );
		$this->set_slug( $name );

		$this->reset_terms();
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
	public function add_term( $term ) {
		$term->set_taxonomy( $this->attribute_name );

		$this->terms->add( $term );
	}

	public function get_terms() {
		return $this->terms;
	}

	public function reset_terms() {
		$this->terms = new Collection();
	}

	public function get_value() {
		return $this->attribute_value;
	}

	public function set_value( $value ) {
		if ( $value instanceof Category ) {
			$this->attribute_value = $value;
		} else {
			$this->attribute_value = ( $relationTerm = $this->get_terms()->offsetGet( $value ) )
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
		return apply_filters( 'ExchangeAttribute::get_external_key', EXTERNAL_CODE_KEY );
	}

	public function get_external() {
		return $this->ext;
	}

	public function get_raw_external() {
	}

	public function set_external( $ext ) {
		$this->ext = (String) $ext;
	}

	static public function fillExistsFromDB( &$obAttributeTaxonomies ) // , $taxonomy = ''
	{
		/** @global \wpdb wordpress database object */
		global $wpdb;

		/** @var boolean get data for items who not has term_id */
		// $orphaned_only = true;

		/** @var array List of external code items list in database attribute context (%s='%s') */
		$taxExternals  = array();
		$termExternals = array();

		/** @var $obAttributeTaxonomy Attribute */
		foreach ( $obAttributeTaxonomies as $obAttributeTaxonomy ) {
			/**
			 * Get taxonomy (attribute)
			 */
			if ( ! $obAttributeTaxonomy->get_id() ) {
				$taxExternals[] = "`meta_value` = '" . $obAttributeTaxonomy->get_external() . "'";
			}

			/**
			 * Get terms (attribute values)
			 * @var Category $term
			 * @todo maybe add parents?
			 */
			foreach ( $obAttributeTaxonomy->get_terms() as $obExchangeTerm ) {
				$termExternals[] = "`meta_value` = '" . $obExchangeTerm->get_external() . "'";
			}
		}

		$results = array();

		$taxExists = array();
		if ( ! empty( $taxExternals ) ) {
			$exists_query = "
                SELECT meta_id, tax_id, meta_key, meta_value
                FROM {$wpdb->prefix}woocommerce_attribute_taxonomymeta
                WHERE `meta_key` = '" . self::get_external_key() . "'
                    AND (" . implode( " \t\n OR ", array_unique( $taxExternals ) ) . ")";

			$results = $wpdb->get_results( $exists_query );
		}

		foreach ( $results as $exist ) {
			$taxExists[ $exist->meta_value ] = $exist;
		}
		$results = array();

		/**
		 * Get from database
		 * @var array list of objects exists from posts db
		 */
		$exists = array();
		if ( ! empty( $termExternals ) ) {
			$exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '" . Category::getExtID() . "'
                    AND (" . implode( " \t\n OR ", array_unique( $termExternals ) ) . ")";

			$results = $wpdb->get_results( $exists_query );
		}

		/**
		 * Resort for convenience
		 */
		foreach ( $results as $exist ) {
			$exists[ $exist->meta_value ] = $exist;
		}
		$results = array();

		foreach ( $obAttributeTaxonomies as &$obAttributeTaxonomy ) {
			/**
			 * Get taxonomy (attribute)
			 */
			if ( ! empty( $taxExists[ $obAttributeTaxonomy->get_external() ] ) ) {
				$obAttributeTaxonomy->set_id( $taxExists[ $obAttributeTaxonomy->get_external() ]->tax_id );
			}

			/**
			 * Get terms (attribute values)
			 * @var Category $term
			 */
			foreach ( $obAttributeTaxonomy->get_terms() as &$obExchangeTerm ) {
				$ext = $obExchangeTerm->get_external();

				if ( ! empty( $exists[ $ext ] ) ) {
					$obExchangeTerm->set_id( $exists[ $ext ]->term_id );
					$obExchangeTerm->meta_id = $exists[ $ext ]->meta_id;
				}
			}
		}
	}
}