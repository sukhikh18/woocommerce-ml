<?php

namespace NikolayS93\Exchanger;

use CommerceMLParser\Model\Types\PropertyValue;
use NikolayS93\Exchanger\Model\AttributeValue;
use NikolayS93\Exchanger\Model\Category;
use NikolayS93\Exchanger\Model\Developer;
use NikolayS93\Exchanger\Model\Attribute;
use NikolayS93\Exchanger\Model\ExchangeProduct;
use NikolayS93\Exchanger\Model\ExchangeOffer;
use NikolayS93\Exchanger\Model\Warehouse;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\ORM\CollectionAttributes;
use NikolayS93\Exchange\ORM\CollectionPosts;
use NikolayS93\Exchange\ORM\CollectionTerms;
use CommerceMLParser\Event;


if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You shall not pass' );
}

class Parser {

//	use Singleton;

	private $files;

	/** @var \CommerceMLParser\Parser */
	private $CommerceParser;

	/** @var CollectionTerms $arCategories */
	private $arCategories;
	/** @var CollectionTerms $arDevelopers */
	private $arDevelopers;
	/** @var CollectionTerms $arWarehouses */
	private $arWarehouses;
	/** @var CollectionAttributes $arProperties */
	private $arProperties;
	/** @var CollectionPosts $arProducts */
	private $arProducts;
	/** @var CollectionPosts $arOffers */
	private $arOffers;

	private $properties_as_requisites;
	private $requisites_as_categories;
	private $requisites_as_developers;
	private $requisites_as_warehouses;
	private $requisites_as_properties;
	private $requisites_exclude;

	function __construct( $files = array() ) {
		$this->files          = $files;
		$this->CommerceParser = \CommerceMLParser\Parser::getInstance();

		$this->arCategories = new CollectionTerms();
		$this->arDevelopers = new CollectionTerms();
		$this->arWarehouses = new CollectionTerms();
		$this->arProperties = new CollectionAttributes();
		$this->arProducts   = new CollectionPosts();
		$this->arOffers     = new CollectionPosts();

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

	public function get_filenames() {
	    return array_map(function($file) {
	        return basename($file);
        }, $this->files);
    }

	//	public function addOwnerListener()
	//	{
	//		$this->CommerceParser->addListener("OwnerEvent", function (Event\OwnerEvent $ownerEvent) {
	//			$Partner = $ownerEvent->getPartner();
	//		});
	//	}
	/**
	 * @note Products has requisites
	 */
	public function listen_product() {
		$this->CommerceParser->addListener( "ProductEvent",
			array( $this, 'parse_products_event' ) );

		return $this;
	}

	public function listen_offer() {
		$this->CommerceParser->addListener( "OfferEvent",
			array( $this, 'parse_offers_event' ) );

		return $this;
	}

	public function listen_category() {
		$this->CommerceParser->addListener( "CategoryEvent",
			array( $this, 'parse_categories_event' ) );

		if ( ! empty( $this->requisites_as_categories ) ) {
			$this->listen_product();
		}

		return $this;
	}

	public function listen_developer() {
		$this->listen_product();

		if ( ! empty( $this->requisites_as_developers ) ) {
			$this->listen_product();
		}

		return $this;
	}

	public function listen_warehouse() {
		$this->CommerceParser->addListener( "WarehouseEvent",
			array( $this, 'parse_warehouses_event' ) );

		if ( ! empty( $this->requisites_as_warehouses ) ) {
			$this->listen_product();
		}

		return $this;
	}

	public function listen_property() {
		$this->CommerceParser->addListener( "PropertyEvent",
			array( $this, 'parse_properties_event' ) );

		if ( ! empty( $this->requisites_as_properties ) ) {
			$this->listen_product();
		}

		return $this;
	}

	function parse() {
		array_map( function ( $file ) {
			if ( ! is_readable( $file ) ) {
				Error()->add_message( sprintf( __( 'File %s is not readable.' ), $file ), "Warning", true );
			}

			$this->CommerceParser->parse( $file );
		}, $this->files );

		return $this;
	}

	public function get_categories() {
		return $this->arCategories;
	}

	public function get_developers() {
		return $this->arDevelopers;
	}

	public function get_warehouses() {
		return $this->arWarehouses;
	}

	public function get_properties() {
		return $this->arProperties;
	}

	public function get_products() {
		return $this->arProducts;
	}

	public function get_offers() {
		return $this->arOffers;
	}

	/********************************* Events *********************************/

	/**
	 * @param \CommerceMLParser\Model\Category $parent
	 *
	 * @var \CommerceMLParser\Model\Category $category
	 */
	private function add_category_recursive( $category, $parent = null ) {
		$newCategory = new Category( array(
			'name'        => $category->getName(),
			'taxonomy'    => 'product_cat',
			'description' => '', // 1c 8.2 not has a cat description?
		),
			$category->getId(),
			$category->getProperties()->fetch()
		);

		if ( $parent ) {
			$newCategory->set_parent_external( $parent->getId() );
		}

		$this->arCategories->add( $newCategory );

		/** @var Collection [description] */
		$children = $category->getChilds();
		if ( ! $children->isEmpty() ) {
			foreach ( $children->fetch() as $child ) {
				$this->add_category_recursive( $child, $category );
			}
		}
	}

	function parse_categories_event( Event\CategoryEvent $categoryEvent ) {
		/** @todo check this */
		// $flatCategory = $categoryEvent->getFlatCategories()->fetch();

		/** @var \CommerceMLParser\ORM\Collection */
		$categories = $categoryEvent->getCategories();

		if ( ! $categories->isEmpty() ) {
			foreach ( $categories->fetch() as $category ) {
				$this->add_category_recursive( $category );
			}
		}
	}

	function parse_warehouses_event( Event\WarehouseEvent $warehouseEvent ) {
		/** @var \CommerceMLParser\Model\Warehouse */
		$warehouse = $warehouseEvent->getWarehouse();

		$term = array(
			'name' => $warehouse->getName(),
		);

		$this->arWarehouses->add( new Warehouse( $term, $warehouse->getId() ) );
	}

	function parse_properties_event( Event\PropertyEvent $propertyEvent ) {
		/** @var \CommerceMLParser\Model\Property */
		$property      = $propertyEvent->getProperty();

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

		$this->arProperties->add( $attribute );
	}

	/**
	 * @param PropertyValue[]|Collection $propertiesCollection
	 */
	private function properties_reduce( &$propertiesCollection ) {
		/**
		 * @param \CommerceMLParser\Model\Types\PropertyValue $productPropertyValue
		 *
		 * @return bool
		 */
		$closure = function ( $productPropertyValue ) {
			foreach ( $this->properties_as_requisites as $propertyAsRequisite ) {
				if ( $productPropertyValue->getId() == $propertyAsRequisite ) {
					return true;
				}
			}

			return false;
		};

		$requisites = $propertiesCollection->filter( $closure );

		return $requisites;
	}

	function parse_products_event( Event\ProductEvent $productEvent ) {
		/** @var \CommerceMLParser\Model\Product $product */
		$product = $productEvent->getProduct();

		$product_id      = $product->getId();
		$ExchangeProduct = new ExchangeProduct( array(
			'post_title'   => $product->getName(),
			'post_excerpt' => $product->getDescription(),
		), $product_id );

		$requisites = new Collection();

		/**
		 * Set categories
		 * @var Collection $categoriesCollection of EXT
		 */
		$categoriesCollection = $product->getCategories();

		if ( ! $categoriesCollection->isEmpty() ) {
			/** @var String $category External code */
			foreach ( $categoriesCollection as $category ) {
				$ExchangeTerm = new Category( array( 'taxonomy' => 'product_cat' ), $category );
				$ExchangeProduct->add_category( $ExchangeTerm );
			}
		}

		/**
		 * Set developer
		 * @var Collection $developersCollection List of..
		 */
		$developersCollection = $product->getManufacturer();

		if ( ! $developersCollection->isEmpty() ) {
			/** @var \CommerceMLParser\Model\Types\Partner $developer Изготовитель */
			foreach ( $developersCollection as $developer ) {
				$developer_id   = $developer->getId();
				$developer_args = array(
					'name'        => $developer->getName(),
					'description' => $developer->getComment(),
				);

				$ExchangeProduct->set_meta( 'Производитель',
					array_merge( $developer_args, array( 'external' => $developer_id ) ) );
			}
		}

		/**
		 * Set properties
		 * @todo
		 */
//		$propertiesCollection = $product->getProperties();
//
//		if ( ! $propertiesCollection->isEmpty() ) {
//
//			/**
//			 * Explode Requisites(meta)/Properties(tax\term)
//			 */
//			$requisites = $this->properties_reduce( $propertiesCollection );
//			$properties = array_diff( $propertiesCollection, $requisites->fetch() );
//
//			/** @var \CommerceMLParser\Model\Types\PropertyValue $productProperty */
//			foreach ( $properties as $productProperty ) {
//
//				$propertyExternal = $productProperty->getId();
//				$propertyValue    = $productProperty->getValue();
//
//				if ( isset( $this->arProperties[ $propertyExternal ] ) ) {
//					/** @var Attribute $productAttributeValue */
//					$productAttributeValue = clone $this->arProperties[ $propertyExternal ];
//					$productAttributeValue->set_value( $propertyValue );
//					$productAttributeValue->reset_terms();
//
//					$ExchangeProduct->add_attribute( $productAttributeValue );
//				}
//			}
//		}

		/**
		 * Set requisites
		 */
		$ExchangeProduct->set_meta( '_sku', $product->getSku() );
		$ExchangeProduct->set_meta( '_barcode', $product->getBarcode() );
		$ExchangeProduct->set_meta( '_unit', $ExchangeProduct->get_current_base_unit( $product->getBaseUnit() ) );
		$ExchangeProduct->set_meta( '_tax', $ExchangeProduct->get_current_tax_rate( $product->getTaxRate() ) );

		/** @var \CommerceMLParser\Model\Types\PropertyValue $requisite */
		foreach ( $requisites as $requisite ) {
			$ExchangeProduct->set_meta( $requisite->getId(), $requisite->getValue() );
		}

		/** @var \CommerceMLParser\Model\Types\RequisiteValue $requisite */
		foreach ( $product->getRequisites() as $requisite ) {
			$ExchangeProduct->set_meta( $requisite->getName(), $requisite->getValue() );
		}

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
		$this->parse_requisites_as_developers( $ExchangeProduct );
		$this->parse_requisites_as_warehouses( $ExchangeProduct );
		$this->parse_requisites_as_properties( $ExchangeProduct );
		// ================================================================= //

		$this->arProducts->add( $ExchangeProduct );
	}

	function parse_offers_event( Event\OfferEvent $offerEvent ) {
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
			$definedOffer = $this->arOffers->offsetGet( $product_ext );
			// do not sell all in low price
			$definedOffer->set_price(
				max( $definedOffer->get_price(), $ExchangeOffer->get_price() ) );
			// increase to twice quantity
			$definedOffer->set_quantity(
				$definedOffer->get_quantity() + $ExchangeOffer->get_quantity() );
		}

		$this->arOffers->add( $ExchangeOffer );
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
				$this->arCategories->add( $term );

				// Set product relative
				$ExchangeProduct->add_category( $term );
			}

			// Delete replaced or empty
			$ExchangeProduct->del_meta( $term_name );
		}
	}

	function parse_requisites_as_developers( ExchangeProduct $ExchangeProduct ) {
		if ( empty( $this->requisites_as_developers ) ) {
			return;
		}

		foreach ( $this->requisites_as_developers as $term_name ) {

			if ( $meta = $ExchangeProduct->get_meta( $term_name ) ) {
				/** @var Developer $term */
				$term = new Developer( array(
					'name' => $meta,
				) );

				// Add term. Sort (unique) by external code
				$this->arDevelopers[ $term->get_external() ] = $term;

				// Set product relative
				$ExchangeProduct->add_developer( $term );
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
				$this->arWarehouses->add( $warehouse );

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
				if ( empty( $this->arProperties[ $taxonomy_slug ] ) ) {
					$attribute = new Attribute( array(
						'attribute_label' => $taxonomy_name,
						'attribute_name'  => $taxonomy_slug,
					), $taxonomy_slug );

					// Need create for collect terms
					$this->arProperties->add( $attribute );
				} else {
					/**
					 * Next work with created/exists taxonomy
					 * @var Attribute
					 */
					$attribute = $this->arProperties->offsetGet( $taxonomy_slug );
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

				/**
				 * Collect in taxonomy
				 * @note correct taxonomy in term by attribute
				 */
				$attribute->add_value( $attribute_value );

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

	/**
	 * @todo add documentation
	 */
	function fill_exists() {
		if ( ! empty( $this->arCategories ) ) {
			Category::fill_exists( $this->arCategories );
		}

//        fill_exists

//        if ( ! empty( $this->arDevelopers ) ) {
//            Category::fill_exists( $this->arDevelopers );
//        }
//
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
//                /** @var Developer $developer */
//                foreach ( $product->developer as &$developer ) {
//                    if ( $developer->get_id() ) {
//                        continue;
//                    }
//
//                    if ( isset( $this->arDevelopers[ $developer->get_external() ] ) ) {
//                        $developer->set_id( $this->arDevelopers[ $developer->get_external() ]->get_id() );
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
