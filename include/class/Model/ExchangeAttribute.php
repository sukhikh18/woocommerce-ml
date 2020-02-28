<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\ORM\Collection;

/**
 * Works with woocommerce_attribute_taxonomies
 */
class ExchangeAttribute implements Interfaces\ExternalCode {
	const EXT_ID = '_ext_ID';

	static function get_ext_ID() {
		return apply_filters( 'ExchangeTerm::get_ext_ID', self::EXT_ID );
	}

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

	// private $taxonomymeta; ?

	function __construct( $args = array(), $ext = '' ) {
		foreach ( get_object_vars( (object) $args ) as $k => $arg ) {
			if ( property_exists( $this, $k ) ) {
				$this->$k = $arg;
			}
		}

		if ( $this->attribute_name ) {
			if ( 0 !== strpos( $this->attribute_name, 'pa_' ) ) {
				$this->attribute_name = 'pa_' . wc_sanitize_taxonomy_name( $this->attribute_name );
			}
		} else {
			$this->attribute_name = wc_attribute_taxonomy_name( \NikolayS93\Exchange\esc_cyr( $this->attribute_label ) );
		}

		if ( $ext ) {
			$this->ext = $ext;
		}
		$this->reset_terms();
	}

	function reset_terms() {
		$this->terms = new Collection();
	}

	public function get_terms() {
		return $this->terms;
	}

	/**
	 * @param ExchangeTerm $term
	 */
	function add_term( $term ) {
		$term->set_taxonomy( $this->attribute_name );

		$this->terms->add( $term );
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

	public function get_name() {
		return $this->attribute_label;
	}

	public function get_value() {
		return $this->attribute_value;
	}

	public function set_value( $value ) {
		if ( $value instanceof ExchangeTerm ) {
			$this->attribute_value = $value;
		} else {
			/** @var Collection */
			$terms = $this->get_terms();

			$this->attribute_value = ( $relationTerm = $terms->offsetGet( $value ) ) ? $relationTerm : (string) $value;
		}

		$this->reset_terms();
	}

	/**
	 * For demonstration
	 */
	public function slice_terms() {
		$sliced = new Collection( $this->terms->first() );
		$sliced->add( $this->terms->last() );

		$this->terms = $sliced;
	}

	public function get_id() {
		return (int) $this->id;
	}

	public function set_id( $id ) {
		$this->id = (int) $id;
	}

	function get_external() {
		return $this->ext;
	}

	function set_external( $ext ) {
		$this->ext = (String) $ext;
	}

	function get_type() {
		return $this->attribute_type;
	}

	static public function fill_exists_from_DB( &$obAttributeTaxonomies ) // , $taxonomy = ''
	{
		/** @global wpdb wordpress database object */
		global $wpdb;

		/** @var boolean get data for items who not has term_id */
		// $orphaned_only = true;

		/** @var List of external code items list in database attribute context (%s='%s') */
		$taxExternals  = array();
		$termExternals = array();

		/** @var $obAttributeTaxonomy ExchangeAttribute */
		foreach ( $obAttributeTaxonomies as $obAttributeTaxonomy ) {
			/**
			 * Get taxonomy (attribute)
			 */
			if ( ! $obAttributeTaxonomy->get_id() ) {
				$taxExternals[] = "`meta_value` = '" . $obAttributeTaxonomy->get_external() . "'";
			}

			/**
			 * Get terms (attribute values)
			 * @var ExchangeTerm $term
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
                WHERE `meta_key` = '" . ExchangeTerm::get_ext_ID() . "'
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
                WHERE `meta_key` = '" . ExchangeTerm::get_ext_ID() . "'
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
			 * @var ExchangeTerm $term
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