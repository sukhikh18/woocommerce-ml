<?php

namespace NikolayS93\Exchange\ORM;

use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\AttributeValue;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;

/**
 * Class CollectionAttributes
 */
class CollectionAttributes extends Collection {
	/**
	 * @param Collection $terms
	 * @param bool $orphaned_only @todo get data for items who not has id
	 *
	 * @return $this
	 */
	public function fill_exists( $orphaned_only = true ) {
		return $this;
		/** @global \wpdb $wpdb wordpress database object */
		global $wpdb;

		/** @var array $externals List of external code items list in database attribute context (%s='%s') */
		$externals      = array();
		$term_externals = array();

		/**
		 * @param Attribute $attribute
		 */
		$build_query = function ( $attribute ) use ( &$externals, &$term_externals ) {
			/** @var bool $attributeRequired Require attribute if value id is empty */
			$attributeRequired = false;

			foreach ( $attribute->get_values() as $attributeValue ) {
				if ( ! $attributeValue->get_id() && $val_ext = $attributeValue->get_external() ) {
					$attributeRequired = true;
					$term_externals[]  = "`meta_value` = '" . $val_ext . "'";
				}
			}

			if ( ( ! $attribute->get_id() || $attributeRequired ) && $ext = $attribute->get_external() ) {
				$externals[] = "`meta_value` = '$ext'";
			}
		};

		$this->walk( $build_query );

		$externals         = array_unique( $externals );
		$term_externals    = array_unique( $term_externals );
		$exists_attributes = array();
		$exists_terms      = array();

		if ( ! empty( $externals ) ) {
			$external_key   = Attribute::get_external_key();
			$externals_args = implode( " \t\n OR ", $externals );

			$exists_query = "
                SELECT meta_id, tax_id, meta_key, meta_value
                FROM {$wpdb->prefix}woocommerce_attribute_taxonomymeta
                WHERE `meta_key` = '$external_key' AND ($externals_args)";

			$exists_attributes = $wpdb->get_results( $exists_query );
		}

		if ( ! empty( $term_externals ) ) {
			// @todo
			$external_key   = AttributeValue::get_external_key();
			$externals_args = implode( " \t\n OR ", $term_externals );

			$exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM {$wpdb->prefix}termmeta tm
                	INNER JOIN {$wpdb->prefix}terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '$external_key' AND ($externals_args)";

			$exists_terms = $wpdb->get_results( $exists_query );
		}

		/**
		 * Fill ids
		 */
		array_walk( $exists_attributes, function ( $result ) use ( $exists_terms ) {
			$external_from_db = $result->meta_value;
			/** @var Attribute $attribute */
			$attribute = $this->offsetGet( $external_from_db );

			$attribute->set_id( $result->tax_id );
			$attribute->meta_id = $result->meta_id;

			foreach ( $attribute->get_values() as $value ) {
				if ( false !== $key = array_search( $value->get_external(),
						wp_list_pluck( $exists_terms, 'meta_value' ) ) ) {
					$value->set_id( $exists_terms[ $key ]->term_id );
					$value->meta_id = $exists_terms[ $key ]->meta_id;
				}
			}
		} );

		return $this;
	}
}
