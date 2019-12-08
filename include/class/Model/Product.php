<?php

namespace NikolayS93\Exchange\Model;

use CommerceMLParser\Model\Types\BaseUnit;
use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Parser;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\Error;
use function NikolayS93\Exchange\Plugin;

class Product extends Post {

	/**
	 * @param \CommerceMLParser\ORM\Collection $base_unit
	 */
	public function get_current_base_unit( $base_unit ) {
		/** @var BaseUnit */
		$base_unit_current = $base_unit->current();
		if ( ! $base_unit_name = $base_unit_current->getNameInterShort() ) {
			$base_unit_name = $base_unit_current->getNameFull();
		}

		return $base_unit_name;
	}

	/**
	 * @param \CommerceMLParser\ORM\Collection $taxRatesCollection СтавкиНалогов
	 */
	public function get_current_tax_rate( $taxRatesCollection ) {
		return $taxRatesCollection->current()->getRate();
	}

	function __construct( Array $post, $ext = '', $meta = array() ) {
		parent::__construct( $post, $ext, $meta );
	}

	/****************************************************** CRUD ******************************************************/
	// public function fetch( $key = null ) {
	// 	$data                       = parent::fetch();
	// 	$data['term_relationships'] = array();

	// 	$fetch = function ( Identifiable $item ) use ( &$data ) {
	// 		if ( $this->get_id() && $item->get_id() ) {
	// 			$data['term_relationships'][] = array(
	// 				'object_id'        => $this->get_id(),
	// 				'term_taxonomy_id' => $item->get_id(),
	// 				'term_order'       => 0,
	// 			);
	// 		}
	// 	};

	// 	array_map( $fetch, $this->relationships->fetch() );
	// 	// array_map( $fetch, $this->attributes->fetch() );

	// 	if ( null === $key || ( $key && ! isset( $data[ $key ] ) ) ) {
	// 		return $data;
	// 	}

	// 	return $data[ $key ];
	// }

	function update_object_terms() {
		$product_id = $this->get_id();
		$count      = 0;

		/**
		 * @param Term $term
		 */
		$update_object_terms = function ( $term ) use ( $product_id, &$count ) {
			if ( ! $product_id || ! $term->term_id ) {
				// @todo add error log
				return false;
			}

			if( 'product_cat' === $term->taxonomy ) {
				$result = null;

				if ( 'off' === ( $post_relationship = Plugin()->get_setting( 'post_relationship' ) ) ) {
					return false;
				}

				if ( 'default' == $post_relationship ) {
					$object_terms    = wp_get_object_terms( $product_id, $term->taxonomy, array( 'fields' => 'ids' ) );
					$default_term_id = (int) get_option( 'default_' . $term->taxonomy );

					// is relatives not exists try set default term
					if ( is_wp_error( $object_terms ) || empty( $object_terms ) ) {
						if ( $default_term_id ) {
							$result = wp_set_object_terms( $product_id, $default_term_id, $term->taxonomy, $append = false );
						}
					}
				} else {
					$result = wp_set_object_terms( $product_id, $term->term_id, $term->taxonomy, $append = true );
				}

				return;
			}
			elseif( 0 === strpos($term->taxonomy, 'pa_') ) {
				// @todo
				echo "<pre>";
				var_dump( $term );
				echo "</pre>";
				die();

				// @todo disable text attributes on this mode.
				if ( 'off' === ( $post_attribute_mode = Plugin()->get_setting( 'post_attribute' ) ) ) {
					return;
				}
			}
			else {
				// @todo
				echo "<pre>";
				var_dump( $term );
				echo "</pre>";
				die();
			}

			// as default:
			$result = wp_set_object_terms( $product_id, $term->term_id, $term->taxonomy, $append = true );
			if ( $result && ! is_wp_error( $result ) ) {
				$count ++;
			} else {
				\NikolayS93\Exchange\error()
					->add_message( $result, 'Warning', true )
					->add_message( $this, 'Target', true );
			}
		};

		$this->relationships->walk( $update_object_terms );

		return $count;
	}
}
