<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Parser;
use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeTaxonomy;
use NikolayS93\Exchange\ORM\Collection;

class ExchangeProduct extends ExchangePost {
	/**
	 * "Product_cat" type wordpress terms
	 * @var Collection
	 */
	public $product_cat;

	/**
	 * Product properties with link by term (has taxonomy/term)
	 * @var Collection
	 */
	public $properties;

	/**
	 * Single term. Link to developer (prev. created)
	 * @var Collection
	 */
	public $developer;

	function __construct( Array $post, $ext = '', $meta = array() ) {
		parent::__construct( $post, $ext, $meta );

		$this->product_cat = new Collection();
		$this->properties  = new Collection();
		$this->developer   = new Collection();
	}

	function getAttribute( $attrExternal = '' ) {
		$attribute = $this->properties->offsetGet( $attrExternal );

		return $attribute;
	}

	function updateAttributes() {
		/**
		 * Set attribute properties
		 */
		$arAttributes = array();

		if ( 'off' === ( $post_attribute_mode = Plugin::get( 'post_attribute' ) ) ) {
			return;
		}

		/**
		 * @var $property Relationship
		 */
		foreach ( $this->properties as $property ) {
			$label          = $property->getName();
			$external_code  = $property->getExternal();
			$property_value = $property->getValue();
			$taxonomy       = $property->getSlug();
			$type           = $property->getType();
			$is_visible     = 0;

			/**
			 * I can write relation if term exists (term as value)
			 */
			if ( $property_value instanceof ExchangeTerm ) {
				$arAttributes[ $taxonomy ] = array(
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => 10,
					'is_visible'   => 0,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);
			} else {
				// Try set as text if term is not exists
				// @todo check this
				if ( 'text' != $type && 'text' == $post_attribute_mode && $taxonomy && $external_code ) {
					$is_visible = 0;

					/**
					 * Try set attribute name by exists terms
					 * Get all properties from parser
					 */
					if ( empty( $allProperties ) ) {
						$Parser        = Parser::getInstance();
						$allProperties = $Parser->getProperties();
					}

					foreach ( $allProperties as $_property ) {
						if ( $_property->getSlug() == $taxonomy && ( $_terms = $_property->getTerms() ) ) {
							if ( isset( $_terms[ $external_code ] ) ) {
								$_term = $_terms[ $external_code ];

								if ( $_term instanceof ExchangeTerm ) {
									$label = $_property->getName();
									$property->setValue( $_term->get_name() );
									break;
								}
							}
						}
					}
				}

				$arAttributes[ $taxonomy ] = array(
					'name'         => $label ? $label : $taxonomy,
					'value'        => $property->getValue(),
					'position'     => 10,
					'is_visible'   => $is_visible,
					'is_variation' => 0,
					'is_taxonomy'  => 0,
				);
			}
		}

		update_post_meta( $this->get_id(), '_product_attributes', $arAttributes );
	}

	private function updateObjectTerm( $product_id, $terms, $taxonomy, $append = true ) {
		$result = array();
		if ( ! is_array( $terms ) ) {
			$terms = array( $terms );
		}
		foreach ( $terms as $i => &$term ) {
			if ( ! $term = intval( $term ) ) {
				unset( $terms[ $i ] );
			}
		}

		if ( empty( $terms ) ) {
			return;
		}

		if ( 'product_cat' == $taxonomy ) {
			$default_product_cat_id = (int) get_option( 'default_product_cat' );

			if ( 'off' === ( $post_relationship = Utils::get( 'post_relationship' ) ) ) {
				return 0;
			} // Force default.
			elseif ( 'default' == $post_relationship ) {
				$object_terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

				if ( is_wp_error( $object_terms ) || empty( $object_terms ) ) {
					$result = wp_set_object_terms( $product_id, $default_product_cat_id, $taxonomy );
				}
			} // Param not selected.
			else {
				$is_object_in_term = is_object_in_term( $product_id, $taxonomy, $default_product_cat_id );
				$append            = $is_object_in_term && ! is_wp_error( $is_object_in_term ) ? false : $append;

				$result = wp_set_object_terms( $product_id, $terms, $taxonomy, $append );
			}
		} elseif ( apply_filters( 'developerTaxonomySlug',
				\NikolayS93\Exchange\DEFAULT_DEVELOPER_TAX_SLUG ) == $taxonomy ) {
			$result = wp_set_object_terms( $product_id, $terms, $taxonomy, $append );
		} elseif ( apply_filters( 'warehouseTaxonomySlug',
				\NikolayS93\Exchange\DEFAULT_WAREHOUSE_TAX_SLUG ) == $taxonomy ) {
			$result = wp_set_object_terms( $product_id, $terms, $taxonomy, $append );
		} // Attributes
		else {
			$result = wp_set_object_terms( $product_id, $terms, $taxonomy, $append );
		}

		if ( is_wp_error( $result ) ) {
			// Utils::addLog( $result, array(
			// 	'product_id' => $product_id,
			// 	'taxonomy'   => $taxonomy,
			// 	'terms'      => $terms,
			// ) );
		} else {
			return sizeof( $result );
		}

		return 0;
	}

	/**
	 * @note Do not merge data for KISS
	 */
	function updateObjectTerms() {
		$count      = 0;
		$product_id = $this->get_id();
		if ( empty( $product_id ) ) {
			return $count;
		}

		/**
		 * Update product's cats
		 */
		$terms = array();
		foreach ( $this->product_cat as $ExchangeTerm ) {
			if ( $term_id = $ExchangeTerm->get_id() ) {
				$terms[] = $term_id;
			}
		}

		if ( ! empty( $terms ) ) {
			$count += $this->updateObjectTerm( $product_id, $terms, 'product_cat' ); // , 0 < $count
		}

		// @todo think about it
		// if( !$this->isNew() ) return $count;

		/**
		 * Update product's war-s
		 */
		$terms = array();
		foreach ( $this->warehouse as $ExchangeTerm ) {
			if ( $term_id = $ExchangeTerm->get_id() ) {
				$terms[] = $term_id;
			}
		}

		if ( ! empty( $terms ) ) {
			$count += $this->updateObjectTerm( $product_id, $terms,
				apply_filters( 'warehouseTaxonomySlug', \NikolayS93\Exchange\DEFAULT_WAREHOUSE_TAX_SLUG ) );
		}

		/**
		 * Update product's developers
		 */
		$terms = array();
		foreach ( $this->developer as $ExchangeTerm ) {
			if ( $term_id = $ExchangeTerm->get_id() ) {
				$terms[] = $term_id;
			}
		}

		if ( ! empty( $terms ) ) {
			$count += $this->updateObjectTerm( $product_id, $terms,
				apply_filters( 'developerTaxonomySlug', \NikolayS93\Exchange\DEFAULT_DEVELOPER_TAX_SLUG ) );
		}

		/**
		 * Update product's properties
		 */
		if ( 'off' !== ( $post_attribute = Plugin::get( 'post_attribute' ) ) && ! $this->properties->isEmpty() ) {
			$terms_id = array();

			/** @var ExchangeAttribute attribute */
			foreach ( $this->properties as $attribute ) {
				if ( $taxonomy = $attribute->getSlug() ) {
					if ( ! isset( $terms_id[ $taxonomy ] ) ) {
						$terms_id[ $taxonomy ] = array();
					}

					$value = $attribute->getValue();
					if ( is_object( $value ) && method_exists( $value, 'get_id' ) && ( $term_id = $value->get_id() ) ) {
						$terms_id[ $taxonomy ][] = $term_id;
					}
				}
			}

			foreach ( $terms_id as $tax => $terms ) {
				$count += $this->updateObjectTerm( $product_id, $terms, $tax );
			}
		}

		return $count;
	}
}
