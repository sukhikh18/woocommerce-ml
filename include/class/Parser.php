<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\AttributeValue;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\Product;
use NikolayS93\Exchange\Model\Offer;
use NikolayS93\Exchange\Model\Relationship;
use NikolayS93\Exchange\Model\Property;
use NikolayS93\Exchange\Model\Warehouse;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\ORM\CollectionAttributes;
use NikolayS93\Exchange\ORM\CollectionPosts;
use NikolayS93\Exchange\ORM\CollectionTerms;
use CommerceMLParser\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You shall not pass' );
}

class Parser {
	/** @var CollectionTerms $categories */
	private $categories;
	/** @var CollectionTerms $warehouses */
	private $warehouses;
	/** @var CollectionAttributes $properties */
	private $properties;
	/** @var CollectionPosts $products */
	private $products;
	/** @var CollectionPosts $offers */
	private $offers;

	private $properties_as_requisites;
	private $requisites_as_categories;
	private $requisites_as_developers;
	private $requisites_as_warehouses;
	private $requisites_as_properties;
	private $requisites_exclude;

	public function __construct() {

		$this->categories = new CollectionTerms();
		$this->warehouses = new CollectionTerms();
		$this->properties = new CollectionAttributes();
		$this->products   = new CollectionPosts();
		$this->offers     = new CollectionPosts();

		$this->properties_as_requisites = (array) apply_filters( PLUGIN::PREFIX . 'ParsePropertiesAsRequisites', array(
			'hotsale' => 'a35a3bd2-d12a-11e7-a4f2-0025904bff5d',
			'newer'   => 'b0eff642-d12a-11e7-a4f2-0025904bff5d',
		) );

		/**
		 * @var array $ParseRequisitesAsCategories as $termSlug => $termLabel
		 * @todo think about: maybe need custom taxonomies instead cats
		 */
		$this->requisites_as_categories = (array) apply_filters( PLUGIN::PREFIX . 'ParseRequisitesAsCategories', array(
			'new' => 'Новинка',
		) );

		/**
		 * @var array $ParseRequisitesAsDevelopers ,
		 * @var array $ParseRequisitesAsWarehouses as $termLabel
		 */
		$this->requisites_as_developers = (array) apply_filters( PLUGIN::PREFIX . 'ParseRequisitesAsDevelopers',
			array() );

		$this->requisites_as_warehouses = (array) apply_filters( PLUGIN::PREFIX . 'ParseRequisitesAsWarehouses', array(
			'Склад',
		) );

		/**
		 * @var array $ParseRequisitesAsProperties as $taxonomySlug => $taxonomyLabel
		 */
		$this->requisites_as_properties = (array) apply_filters( PLUGIN::PREFIX . 'ParseRequisitesAsProperties', array(
			'size'  => 'Размер',
			'brand' => 'Производитель',
		) );

		$this->requisites_exclude = (array) apply_filters( PLUGIN::PREFIX . 'parseProductExcludeRequisites', array(
			'ВидНоменклатуры',
			'ТипНоменклатуры',
		) );
	}

//	public function addOwnerListener()
//	{
//		$this->CommerceParser->addListener("OwnerEvent", function (Event\OwnerEvent $ownerEvent) {
//			$Partner = $ownerEvent->getPartner();
//		});
//	}

	/**
	 * Get categories collection
	 *
	 * @return CollectionTerms
	 */
	public function get_categories() {
		return $this->categories;
	}

	/**
	 * Get warehouse collection
	 *
	 * @return CollectionTerms
	 */
	public function get_warehouses() {
		return $this->warehouses;
	}

	/**
	 * Get attributes (properties) collection.
	 *
	 * @return CollectionAttributes
	 */
	public function get_properties() {
		return $this->properties;
	}

	/**
	 * Get products collection.
	 *
	 * @return CollectionPosts
	 */
	public function get_products() {
		return $this->products;
	}

	/**
	 * Get offers collection.
	 *
	 * @return CollectionPosts
	 */
	public function get_offers() {
		return $this->offers;
	}

	/********************************* Events *********************************/

	/**
	 * @param \CommerceMLParser\Model\Category $parent
	 *
	 * @var \CommerceMLParser\Model\Category $category
	 */
	private function add_category_recursive( $category, $parent = null ) {
		$new_category = new Category( array(
			'name'        => $category->getName(),
			'taxonomy'    => 'product_cat',
			'description' => '', // 1c 8.2 not has a cat description?
		),
			$category->getId(),
			$category->getProperties()->fetch()
		);

		if ( $parent ) {
			$new_category->set_parent_external( $parent->getId() );
		}

		$this->categories->add( $new_category );

		/** @var Collection [description] */
		$children = $category->getChilds();
		if ( ! $children->isEmpty() ) {
			foreach ( $children->fetch() as $child ) {
				$this->add_category_recursive( $child, $category );
			}
		}
	}

	function category_event( Event\CategoryEvent $categoryEvent ) {
		/** @todo check this $flatCategory = $categoryEvent->getFlatCategories()->fetch(); */
		/** @var \CommerceMLParser\ORM\Collection */
		$categories = $categoryEvent->getCategories();

		if ( ! $categories->isEmpty() ) {
			foreach ( $categories->fetch() as $category ) {
				$this->add_category_recursive( $category );
			}
		}
	}

	function warehouse_event( Event\WarehouseEvent $warehouseEvent ) {
		/** @var \CommerceMLParser\Model\Warehouse */
		$warehouse = $warehouseEvent->getWarehouse();

		$term = array(
			'name' => $warehouse->getName(),
		);

		$this->warehouses->add( new Warehouse( $term, $warehouse->getId() ) );
	}

	function property_event( Event\PropertyEvent $propertyEvent ) {
		/** @var \CommerceMLParser\Model\Property */
		$property = $propertyEvent->getProperty();

		$attribute = new Attribute( array(
			'attribute_label' => $property->getName(),
			'attribute_type'  => $property->getType() === 'Строка' ? 'text' : 'select',
		), $property->getId() );

		// Fill ExchangeTerm values
		foreach ( $property->getValues() as $term_id => $name ) {
			$newTerm = new AttributeValue( array(
				'name'     => $name,
				'taxonomy' => $attribute->get_slug(),
			), $term_id );

			$attribute->add_value( $newTerm );
		}

		$this->properties->add( $attribute );
	}

	function product_event( Event\ProductEvent $productEvent ) {
		/** @var \CommerceMLParser\Model\Product $product */
		$product = $productEvent->getProduct();

		$product_id      = $product->getId();
		$ExchangeProduct = new Product( array(
			'post_title'   => $product->getName(),
			'post_excerpt' => $product->getDescription(),
		), $product_id );

		$ExchangeProduct->set_meta( '_sku', $product->getSku() );
		$ExchangeProduct->set_meta( '_barcode', $product->getBarcode() );
		$ExchangeProduct->set_meta( '_unit', $ExchangeProduct->get_current_base_unit( $product->getBaseUnit() ) );
		$ExchangeProduct->set_meta( '_tax', $ExchangeProduct->get_current_tax_rate( $product->getTaxRate() ) );

		/**
		 * Set categories
		 *
		 * @var String $category External code
		 */
		foreach ( $product->getCategories() as $source ) {
			$ExchangeProduct->add_relationship( new Relationship( array(
				'taxonomy'    => 'product_cat',
				'term_source' => $source,
			) ) );
		}

		$arProperties = array();

		/**
		 * Set properties
		 *
		 * @param \CommerceMLParser\Model\Types\PropertyValue $item
		 */
		$parseAttributes = function ( $item ) use ( &$ExchangeProduct, &$arProperties ) {

			$property = $this->properties->offsetGet( $item->getId() );

			if ( 'select' === $property->get_type() ) {

				$Relationship = new Relationship( array(
					'taxonomy'    => $property->get_slug(),
					'term_source' => $item->getId(),
				) );

				$ExchangeProduct->add_relationship( $Relationship );

				$arProperties[ $property->get_slug() ] = new Property( array(
					'name'        => $property->get_slug(),
					'value'       => '',
					'is_taxonomy' => 1,
				) );
			} else {
				$arProperties[ $property->get_name() ] = new Property( array(
					'name'  => $property->get_name(),
					'value' => $item->getValue(),
				) );
			}
		};

		array_map( $parseAttributes, $product->getProperties()->fetch() );

		/**
		 * @var \CommerceMLParser\Model\Types\RequisiteValue $productProperty
		 */
		$parseRequisites = function ( $item ) use ( &$arProperties ) {
			$arProperties[ $item->getName() ] = new Property( array(
				'name'  => $item->getName(),
				'value' => $item->getValue(),
			) );
		};

		array_map( $parseRequisites, $product->getRequisites()->fetch() );

		$ExchangeProduct->set_meta( '_product_attributes', $arProperties );

		// foreach ($product->getProperties() as $productProperty) {
		// 	if ( isset( $this->arProperties[ $propertyExternal ] ) ) {
		// 		/** @var Attribute $productAttributeValue */
		// 		$productAttributeValue = clone $this->arProperties[ $propertyExternal ];
		// 		$productAttributeValue->set_value( $propertyValue );
		// 		$productAttributeValue->reset_terms();

		// 		$ExchangeProduct->add_attribute( $productAttributeValue );
		// 	}
		// }


//		$characteristics = array();
//		foreach ( $product->getCharacteristics() as $characteristic ) {
//			$characteristics[] = $characteristic->getId();
//		}
//		$ExchangeProduct->set_meta( $characteristics );
		array_map( function ( $excludeRequisite ) use ( $ExchangeProduct ) {
			$ExchangeProduct->delete_meta( $excludeRequisite );
		}, $this->requisites_exclude );

		// ================================================================= //
		$this->parse_requisites_as_categories( $ExchangeProduct );
		$this->parse_requisites_as_warehouses( $ExchangeProduct );
		$this->parse_requisites_as_properties( $ExchangeProduct );
		// ================================================================= //

		$this->products->add( $ExchangeProduct );
	}

	function offer_event( Event\OfferEvent $offerEvent ) {
		/** @var \CommerceMLParser\Model\Offer */
		$offer = $offerEvent->getOffer();

		$source_full = $offer->getId();

		if ( defined( 'MERGE_VARIATIONS' ) && 'Y' === MERGE_VARIATIONS ) {
			// Offer source equal product source.
			list( $source ) = explode( '#', $source_full );
		} else {
			// Its possible?
//			if ( false === strpos( $source_full, '#' ) ) {
//				$source_product = $source_full;
//			    $source = '';
//			} else {
//				list( $source_product, $source ) = explode( '#', $source_full );
//			}
			// @todo
			return;
		}

		$price    = Offer::get_current_price( $offer->getPrices() );
		$quantity = $offer->getQuantity();

		/**
		 * if is have several offers, merge them to single
		 *
		 * @var Offer $ExchangeOffer
		 */
		if ( defined( 'MERGE_VARIATIONS' ) && 'Y' === MERGE_VARIATIONS ) {
			$ExchangeOffer = $this->offers->offsetGet( $source );
		}

		if ( ! $ExchangeOffer ) {
			$ExchangeOffer = new Offer( array(
				'post_title' => $offer->getName(),
				'post_type'  => 'offer',
			), $source );
		}

		$ExchangeOffer
			->set_price( $price )
			->set_quantity( $quantity );

		/**
		 * Set warehouses
		 *
		 * @todo merge warehouses
		 * @var \CommerceMLParser\ORM\Collection
		 */
		$warehousesCollection = $offer->getWarehouses();
		$whStock              = $ExchangeOffer->get_meta( 'stock_wh', array() );

		foreach ( $warehousesCollection as $warehouse ) {
			$source = $warehouse->getId();
			$qty    = floatval( $warehouse->getQuantity() );

			$whStock[ $source ] = floatval( isset( $whStock[ $source ] ) ? $whStock[ $source ] + $qty : $qty );

			if ( $qty > 0 ) {
				$relation = new Relationship( array(
					'term_source' => $source,
					'taxonomy'    => Register::WAREHOUSE_SLUG,
				) );

				$ExchangeOffer->add_relationship( $relation );
			}
		}

		$ExchangeOffer->set_meta( '_stock_wh', $whStock );

		$this->offers->add( $ExchangeOffer );
	}

// ====================================================================== //
	function parse_requisites_as_categories( Product $ExchangeProduct ) {
		if ( empty( $this->requisites_as_categories ) ) {
			return;
		}

		foreach ( $this->requisites_as_categories as $term_slug => $term_name ) {
			// Get term from product by term name
			if ( $meta = $ExchangeProduct->get_meta( $term_name ) ) {
				/** @var Category $term */
				$term = new Category( array(
					'name'     => $term_name,
					'slug'     => $term_slug,
					'taxonomy' => 'product_cat',
				) );

				// Add term. Sort (unique) by external code
				$this->categories->add( $term );

				$relation = new Relationship( array(
					'term_id'            => $term->get_id(),
					'term_source'        => $term->get_external(),
				) );

				// Set product relative
				$ExchangeProduct->add_relationship( $relation );
			}

			// Delete replaced or empty
			$ExchangeProduct->delete_meta( $term_name );
		}
	}

	function parse_requisites_as_warehouses( Product $ExchangeProduct ) {
		if ( empty( $this->requisites_as_warehouses ) ) {
			return;
		}

		foreach ( $this->requisites_as_warehouses as $term_name ) {
			// Get term from product by term name
			if ( $meta = $ExchangeProduct->get_meta( $term_name ) ) {
				/** @var Warehouse $warehouse */
				$warehouse = new Warehouse( array(
					'name'     => $meta,
					'taxonomy' => Register::get_warehouse_taxonomy_slug(),
				) );

				// Add term. Sort (unique) by external code
				$this->warehouses->add( $warehouse );

				// Set product relative
				$ExchangeProduct->add_warehouse( $warehouse );
			}

			// Delete replaced or empty
			$ExchangeProduct->delete_meta( $term_name );
		}
	}

	function parse_requisites_as_properties( Product $ExchangeProduct ) {
		if ( empty( $this->requisites_as_properties ) ) {
			return;
		}

		foreach ( $this->requisites_as_properties as $taxonomy_slug => $taxonomy_name ) {
			if ( $meta = $ExchangeProduct->get_meta( $taxonomy_name ) ) {
				// If this taxonomy not exists
				if ( empty( $this->properties[ $taxonomy_slug ] ) ) {
					$attribute = new Attribute( array(
						'attribute_label' => $taxonomy_name,
						'attribute_name'  => $taxonomy_slug,
					), $taxonomy_slug );

					// Need create for collect terms
					$this->properties->add( $attribute );
				} else {
					/**
					 * Next work with created/exists taxonomy
					 * @var Attribute
					 */
					$attribute = $this->properties->offsetGet( $taxonomy_slug );
				}

				$attribute_value = new AttributeValue( ! is_array( $meta ) ? array( 'name' => $meta ) : $meta );

				/**
				 * Unique external
				 */
				$ext_slug = $attribute->get_slug();
				if ( 0 !== strpos( $ext_slug, 'pa_' ) ) {
					$ext_slug = 'pa_' . $ext_slug;
				}

				$attribute_value->set_external( $ext_slug . '/' . $attribute_value->get_slug() );

				$term_slug = $attribute->get_external() . '-' . $attribute_value->get_slug();

				/**
				 * Unique slug (may be equal slugs on other taxonomy)
				 */
				$attribute_value->set_slug( $term_slug );

				$attribute_value = new AttributeValue( ! is_array( $meta ) ? array( 'name' => $meta ) : $meta );

				/**
				 * Collect in taxonomy
				 * @note correct taxonomy in term by attribute
				 */
				$attribute->add_value( $attribute_value );

				$attribute_value->set_external( $ext_slug . '/' . $attribute_value->get_slug() );

				$term_slug = $attribute->get_external() . '-' . $attribute_value->get_slug();

				/**
				 * Set product relative
				 *
				 * @param Object property name with list of terms
				 */
				$ExchangeProduct->add_attribute( $attribute );
			}
//
			/**
			 * Delete replaced or empty
			 */
			$ExchangeProduct->delete_meta( $taxonomy_name );
		}
	}

	/**
	 * @todo add documentation
	 */
	function fill_exists() {
//		if ( ! empty( $this->arCategories ) ) {
//			Category::fill_exists( $this->arCategories );
//		}

//        if ( ! empty( $this->arWarehouses ) ) {
//            Category::fill_exists( $this->arWarehouses );
//        }
//
//        if ( ! empty( $this->arProperties ) ) {
//            Attribute::fill_exists( $this->arProperties );
//        }

		// Я что то не понял как, но ID уже присвоены заранее
		// Кроме категорий (надо разобраться)
//        if ( ! empty( $this->arProducts ) ) {
//            /** Get exists product information by database */
//            ExchangeProduct::fill_exists( $this->arProducts );
//
//            /** Fill id if is term exists in file data */
//            foreach ( $this->arProducts as &$product ) {
//                /** @var Category $product_cat */
//                foreach ( $product->product_cat as &$product_cat ) {
//                    if ( $product_cat->get_id() ) {
//                        continue;
//                    }
//
//                    if ( isset( $this->arCategories[ $product_cat->get_external() ] ) ) {
//                        $product_cat->set_id( $this->arCategories[ $product_cat->get_external() ]->get_id() );
//                    }
//                }
//                /** @var Warehouse $warehouse */
//                foreach ( $product->warehouse as &$warehouse ) {
//                    if ( $warehouse->get_id() ) {
//                        continue;
//                    }
//
//                    if ( isset( $this->arWarehouses[ $warehouse->get_external() ] ) ) {
//                        $warehouse->set_id( $this->arWarehouses[ $warehouse->get_external() ]->get_id() );
//                    }
//                }
//                /** @var PropertyValue $property */
//                foreach ( $product->properties as $property ) {
//                    $terms = array();
//                    $value = $property->get_value();
//
//                    if ( isset( $this->arProperties[ $property->get_external() ] ) ) {
//                        $attribute = $this->arProperties[ $property->get_external() ];
//                        /** @var Collection $attributeTerms */
//                        $attributeTerms = $attribute->getTerms();
//                        $terms          = ( $value instanceof Category ) ? array( $value ) : $property->getTerms();
//                    }
//
//                    foreach ( $terms as $term ) {
//                        if ( $term->get_id() ) {
//                            continue;
//                        }
//
//                        if ( $filledRelation = $attributeTerms->offsetGet( $term->getExternal() ) ) {
//                            $property->set_value( $filledRelation );
//                        }
//                    }
//                }
//
//                /** Fill relative exists who not exists in this exchange (filedata) */
//                $product->fill_exists_terms();
//            }
//        }
//
//        if ( ! empty( $this->arOffers ) ) {
//            ExchangeProduct::fill_exists( $this->arOffers );
//            foreach ( $this->arOffers as &$offer ) {
//                $offer->fill_exists_terms();
//            }
//        }
	}
}
