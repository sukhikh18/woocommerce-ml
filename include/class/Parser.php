<?php

namespace NikolayS93\Exchanger;

use CommerceMLParser\Model\Types\PropertyValue;
use NikolayS93\Exchanger\Model\AttributeValue;
use NikolayS93\Exchanger\Model\Category;
use NikolayS93\Exchanger\Model\Attribute;
use NikolayS93\Exchanger\Model\ExchangeProduct;
use NikolayS93\Exchanger\Model\ExchangeOffer;
use NikolayS93\Exchanger\Model\Warehouse;
use NikolayS93\Exchanger\ORM\Collection;
use NikolayS93\Exchanger\ORM\CollectionAttributes;
use NikolayS93\Exchanger\ORM\CollectionPosts;
use NikolayS93\Exchanger\ORM\CollectionTerms;
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
		$ExchangeProduct = new ExchangeProduct( array(
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
		foreach ( $product->getCategories() as $category ) {
			$ExchangeTerm = new Category( array( 'taxonomy' => 'product_cat' ), $category );
			$ExchangeProduct->add_category( $ExchangeTerm );
		}

		/**
		 * Set properties
		 *
		 * @var \CommerceMLParser\Model\Types\PropertyValue $productProperty
		 */
		$parseAttributes = function ( $item ) use ( &$ExchangeProduct ) {
			$ExchangeProduct->properties[] = (object) array(
				'id'    => method_exists( $item, 'getId' ) ? $item->getId() : '',
				'name'  => method_exists( $item, 'getName' ) ? $item->getName() : '',
				'value' => method_exists( $item, 'getValue' ) ? $item->getValue() : '',
			);
		};

		array_map( $parseAttributes, $product->getProperties()->fetch() );
		array_map( $parseAttributes, $product->getRequisites()->fetch() );
		array_map( $parseAttributes, $product->getManufacturer()->fetch() );

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
			$ExchangeProduct->del_meta( $excludeRequisite );
		}, $this->requisites_exclude );

		// ================================================================= //
		$this->parse_requisites_as_categories( $ExchangeProduct );
		// $this->parse_requisites_as_developers( $ExchangeProduct );
		$this->parse_requisites_as_warehouses( $ExchangeProduct );
		$this->parse_requisites_as_properties( $ExchangeProduct );
		// ================================================================= //

		$this->products->add( $ExchangeProduct );
	}

	function offer_event( Event\OfferEvent $offerEvent ) {
		/** @var \CommerceMLParser\Model\Offer */
		$offer = $offerEvent->getOffer();
		// @list($product_id, $offer_id) = explode('#', $id);
		$id       = $offer->getId();
		$quantity = $offer->getQuantity();

		$ExchangeOffer = new ExchangeOffer( array(
			'post_title' => $offer->getName(),
			'post_type'  => 'offer',
//		    'post_excerpt' => $offer->getDescription(),
		), $id );

		$price = $ExchangeOffer->get_current_price( $offer->getPrices() );

		$ExchangeOffer
			->set_price( $price )
			->set_quantity( $quantity );

		/**
		 * Set warehouses
		 * @var \CommerceMLParser\ORM\Collection
		 */
		$warehousesCollection = $offer->getWarehouses();

		if ( ! $warehousesCollection->isEmpty() ) {

			$stock_wh = array();

			foreach ( $warehousesCollection as $warehouse ) {
				$warehouse_id = $warehouse->getId();
				$qty          = $warehouse->getQuantity();

				$warehouse = new Warehouse( array(), $warehouse_id );

				if ( 0 < $qty ) {
					$ExchangeOffer->add_warehouse( $warehouse );
					// @todo else: remove relationship
				}

				$stock_wh[ $warehouse->get_external() ] = $qty;
			}

			$ExchangeOffer->set_meta( '_stock_wh', $stock_wh );
		}

		/** @var string $ext for ex. b9006805-7dde-11e8-80cb-70106fc831cf#d3e195ce-746f-11e8-80cb-70106fc831cf */
		$ext = $ExchangeOffer->get_raw_external();

		if ( false !== strpos( $ext, '#' ) ) {
			$offer_ext   = false;
			$product_ext = $ext;
		} else {
			@list( $product_ext, $offer_ext ) = explode( '#', $ext );
		}

		/**
		 * if is have several offers, merge them to single
		 * @todo: check the link
		 */
		if ( $offer_ext && defined( 'DISABLE_VARIATIONS' ) && DISABLE_VARIATIONS ) {
			/** @var ExchangeOffer $definedOffer */
			$definedOffer = $this->offers->offsetGet( $product_ext );
			// do not sell all in low price
			$definedOffer->set_price(
				max( $definedOffer->get_price(), $ExchangeOffer->get_price() ) );
			// increase to twice quantity
			$definedOffer->set_quantity(
				$definedOffer->get_quantity() + $ExchangeOffer->get_quantity() );
		}

		$this->offers->add( $ExchangeOffer );
	}

// ====================================================================== //
	function parse_requisites_as_categories( ExchangeProduct $ExchangeProduct ) {
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

				// Set product relative
				$ExchangeProduct->add_category( $term );
			}

			// Delete replaced or empty
			$ExchangeProduct->del_meta( $term_name );
		}
	}

	function parse_requisites_as_warehouses( ExchangeProduct $ExchangeProduct ) {
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
			$ExchangeProduct->del_meta( $term_name );
		}
	}

	function parse_requisites_as_properties( ExchangeProduct $ExchangeProduct ) {
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
			$ExchangeProduct->del_meta( $taxonomy_name );
		}
	}
}
