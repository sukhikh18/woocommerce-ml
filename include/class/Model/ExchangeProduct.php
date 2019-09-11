<?php

namespace NikolayS93\Exchange\Model;

use CommerceMLParser\Model\Types\BaseUnit;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Parser;
use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\ORM\Collection;

class ExchangeProduct extends ExchangePost {
	/**
	 * "Product_cat" type wordpress terms
	 * @var Collection
	 */
	public $categories;

	/**
	 * Product properties with link by term (has taxonomy/term)
	 * @var Collection
	 */
	public $attrubutes;

	/**
	 * Single term. Link to developer (prev. created)
	 * @var Collection
	 */
	public $developers;

	function __construct( Array $post, $ext = '', $meta = array() ) {
		parent::__construct( $post, $ext, $meta );

		$this->categories = new Collection();
		$this->attrubutes = new Collection();
		$this->developers = new Collection();
	}

	public function fetch() {
		$el = parent::fetch();

		$el['term_relationships'] = array();

		array_map( function ( Identifiable $item ) use ( &$el ) {

			if ( $this->get_id() && $item->get_id() ) {
				$el['term_relationships'][] = array(
					'object_id'        => $this->get_id(),
					'term_taxonomy_id' => $item->get_id(),
					'term_order'       => 0,
				);
			}

		},
			$this->categories->fetch(),
			$this->attrubutes->fetch(),
			$this->developers->fetch()
		);

		return $el;
	}

	function add_category( Category $ExchangeTerm ) {
		return $this->categories->add( $ExchangeTerm );
	}

	function add_developer( Developer $ExchangeTerm ) {
		return $this->developers->add( $ExchangeTerm );
	}

	function add_attribute( Attribute $ProductAttribute ) {
		return $this->attrubutes->add( $ProductAttribute );
	}

	function get_category( $CollectionItemKey = '' ) {
		$category = $CollectionItemKey ?
			$this->categories->offsetGet( $CollectionItemKey ) :
			$this->categories->first();

		return $category;
	}

	function get_developer( $CollectionItemKey = '' ) {
		$developer = $CollectionItemKey ?
			$this->developers->offsetGet( $CollectionItemKey ) :
			$this->developers->first();

		return $developer;
	}

	function get_attribute( $CollectionItemKey = '' ) {
		$attribute = $CollectionItemKey ?
			$this->attrubutes->offsetGet( $CollectionItemKey ) :
			$this->attrubutes->first();

		return $attribute;
	}

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


	function updateAttributes() {
		/**
		 * Set attribute properties
		 */
		$arAttributes = array();

		if ( 'off' === ( $post_attribute_mode = Plugin::get( 'post_attribute' ) ) ) {
			return;
		}

		foreach ( $this->properties as $property ) {
			$label          = $property->get_name();
			$external_code  = $property->get_external();
			$property_value = $property->get_value();
			$taxonomy       = $property->get_slug();
			$type           = $property->get_type();
			$is_visible     = 0;

			/**
			 * I can write relation if term exists (term as value)
			 */
			if ( $property_value instanceof Category ) {
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
						$Parser        = Parser::get_instance();
						$allProperties = $Parser->get_properties();
					}

					foreach ( $allProperties as $_property ) {
						if ( $_property->get_slug() == $taxonomy && ( $_terms = $_property->get_terms() ) ) {
							if ( isset( $_terms[ $external_code ] ) ) {
								$_term = $_terms[ $external_code ];

								if ( $_term instanceof Category ) {
									$label = $_property->get_name();
									$property->set_value( $_term->get_name() );
									break;
								}
							}
						}
					}
				}

				$arAttributes[ $taxonomy ] = array(
					'name'         => $label ? $label : $taxonomy,
					'value'        => $property->get_value(),
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

		if ( 'product_cat' == $taxonomy ) {
			$default_term_id = get_option( 'default_' . $taxonomy );

			if ( 'off' === ( $post_relationship = Plugin::get( 'post_relationship' ) ) ) {
				return 0;
			} elseif ( 'default' == $post_relationship ) {
				$object_terms = wp_get_object_terms( $product_id, $taxonomy, array( 'fields' => 'ids' ) );

				if ( is_wp_error( $object_terms ) || empty( $object_terms ) ) {
					$result = wp_set_object_terms( $product_id, (int) $default_term_id, $taxonomy );
				}
			}

			$is_object_in_term = is_object_in_term( $product_id, $taxonomy, 'uncategorized' );
			$append            = $is_object_in_term && ! is_wp_error( $is_object_in_term ) ? false : $append;
		} elseif ( apply_filters( 'developerTaxonomySlug',
				\NikolayS93\Exchange\DEFAULT_DEVELOPER_TAX_SLUG ) == $taxonomy ) {
			$result = wp_set_object_terms( $product_id, array_map( 'intval', $terms ), $taxonomy, $append );
		} elseif ( apply_filters( 'warehouseTaxonomySlug',
				\NikolayS93\Exchange\DEFAULT_WAREHOUSE_TAX_SLUG ) == $taxonomy ) {
			$result = wp_set_object_terms( $product_id, array_map( 'intval', $terms ), $taxonomy, $append );
		} // Attributes
		else {
			$result = wp_set_object_terms( $product_id, array_map( 'intval', $terms ), $taxonomy, $append );
		}

		if ( is_wp_error( $result ) ) {
			Error::set_wp_error( $result, 'Warning', true );
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
		// if( !$this->is_new() ) return $count;

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

			/** @var Attribute attribute */
			foreach ( $this->properties as $attribute ) {
				if ( $taxonomy = $attribute->getSlug() ) {
					if ( ! isset( $terms_id[ $taxonomy ] ) ) {
						$terms_id[ $taxonomy ] = array();
					}

					$value = $attribute->getValue();
					if ( $term_id = $value->get_id() ) {
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
