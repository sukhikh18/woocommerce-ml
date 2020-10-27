<?php

namespace NikolayS93\Exchange;

use CommerceMLParser\Model\Offer;
use CommerceMLParser\Model\Types\BaseUnit;
use CommerceMLParser\Model\Types\Partner;
use CommerceMLParser\Model\Types\Price;
use CommerceMLParser\Model\Types\TaxRate;
use CommerceMLParser\Model\Types\WarehouseStock;
use NikolayS93\Exchange\Model;
use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeAttribute;
use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\ExchangeOffer;
use NikolayS93\Exchange\ORM\Collection;
use CommerceMLParser\Event;
use CommerceMLParser\Model\Product;
use CommerceMLParser\Model\Types\ProductCharacteristic;
use CommerceMLParser\Model\Types\PropertyValue;
use CommerceMLParser\Model\Types\RequisiteValue;
use NikolayS93\Exchange\Creational\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You shall not pass' );
}

class Parser {
	use Singleton;

	/**
	 * Resourses
	 */
	private $arCategories = array();
	private $arDevelopers = array();
	private $arWarehouses = array();
	private $arProperties = array();
	private $arProducts = array();
	private $arOffers = array();

	/**
	 * Временная переменная, нужна только для связи товаров с атрибутами
	 * @var array
	 */
	private $arTaxonomies = array();

	function __init() {
	}

	public static function is_xml( $filename ) {
		return 1 === preg_match( '/\.xml$/', $filename );
	}

	function __parse( $files = array() ) {
		if ( empty( $files ) ) {
			return;
		}

		// is file basename.
		if ( ! is_array( $files ) ) {
			$files = array( Parser::get_dir( Plugin::get_type() ) . '/' . $files );
		}

		$files = array_filter( $files, array( __CLASS__, 'is_xml' ) );

		$Parser = \CommerceMLParser\Parser::getInstance();
		$Parser->addListener( "CategoryEvent", array( $this, 'parse_categories_event' ) );
		$Parser->addListener( "WarehouseEvent", array( $this, 'parse_warehouses_event' ) );
		$Parser->addListener( "PropertyEvent", array( $this, 'parse_properties_event' ) );
		$Parser->addListener( "ProductEvent", array( $this, 'parse_products_event' ) );
		$Parser->addListener( "OfferEvent", array( $this, 'parse_offers_event' ) );
		/** 1c no has develop section (values only)
		 * $Parser->addListener("DeveloperEvent", array($this, 'parseDevelopersEvent')); */

		foreach ( $files as $file ) {
			if ( ! is_readable( $file ) ) {
				Utils::error( 'File ' . $file . ' is not readble.' );
			}

			$Parser->parse( $file );
		}

		$this->parse_requisites();
		$this->prepare_offers();
	}

	function __fill_exists() {
		if ( ! empty( $this->arCategories ) ) {
			ExchangeTerm::fill_exists_from_DB( $this->arCategories );
		}

		if ( ! empty( $this->arDevelopers ) ) {
			ExchangeTerm::fill_exists_from_DB( $this->arDevelopers );
		}

		if ( ! empty( $this->arWarehouses ) ) {
			ExchangeTerm::fill_exists_from_DB( $this->arWarehouses );
		}

		if ( ! empty( $this->arProperties ) ) {
			ExchangeAttribute::fill_exists_from_DB( $this->arProperties );
		}

		// Я что то не понял как, но ID уже присвоены заранее
		// Кроме категорий (надо разобраться)
		if ( ! empty( $this->arProducts ) ) {
			/** Get exists product information by database */
			ExchangeProduct::fill_exists_from_DB( $this->arProducts );

			/** Fill id if is term exists in filedata */
			foreach ( $this->arProducts as &$product ) {
				foreach ( $product->product_cat as &$product_cat ) {
					if ( $product_cat->get_id() ) {
						continue;
					}
					if ( isset( $this->arCategories[ $product_cat->get_external() ] ) ) {
						$product_cat->set_id( $this->arCategories[ $product_cat->get_external() ]->get_id() );
					}
				}

				foreach ( $product->developer as &$developer ) {
					if ( $developer->get_id() ) {
						continue;
					}
					if ( isset( $this->arDevelopers[ $developer->get_external() ] ) ) {
						$developer->set_id( $this->arDevelopers[ $developer->get_external() ]->get_id() );
					}
				}

				foreach ( $product->warehouse as &$warehouse ) {
					if ( $warehouse->get_id() ) {
						continue;
					}
					if ( isset( $this->arWarehouses[ $warehouse->get_external() ] ) ) {
						$warehouse->set_id( $this->arWarehouses[ $warehouse->get_external() ]->get_id() );
					}
				}

				/** @var Model\ExchangeAttribute $property */
				foreach ( $product->properties as $property ) {
					$terms = array();
					$value = $property->get_value();

					if ( isset( $this->arProperties[ $property->get_external() ] ) ) {
						$attribute      = $this->arProperties[ $property->get_external() ];
						$attributeTerms = $attribute->get_terms();
						$terms          = ( $value instanceof ExchangeTerm ) ? array( $value ) : $property->get_terms();
					}

					foreach ( $terms as $term ) {
						if ( $term->get_id() ) {
							continue;
						}

						if ( $filledRelation = $attributeTerms->offsetGet( $term->get_external() ) ) {
							$property->set_value( $filledRelation );
						}
					}
				}

				/** Fill relative exists who not exists in this exchange (filedata) */
				$product->fill_exists_relatives_from_DB();
			}
		}

		if ( ! empty( $this->arOffers ) ) {
			ExchangeProduct::fill_exists_from_DB( $this->arOffers );
			foreach ( $this->arOffers as &$offer ) {
				$offer->fill_exists_relatives_from_DB();
			}
		}
	}

	public static function get_dir( $namespace = '' ) {
		$dir = trailingslashit( Plugin::get_exchange_data_dir() . $namespace );

		if ( ! is_dir( $dir ) ) {
			/** Try create */
			@mkdir( $dir, 0777, true ) or Utils::error( printf(
				__( "<strong>%s</strong>: Sorry but <strong>%s</strong> not has write permissions", DOMAIN ),
				__( "Fatal error", DOMAIN ),
				$dir
			) );
		}

		/** Check again */
		if ( ! is_dir( $dir ) ) {
			Utils::error( printf(
				__( "<strong>%s</strong>: Sorry but <strong>%s</strong> not readble", DOMAIN ),
				__( "Fatal error", DOMAIN ),
				$dir
			) );
		}

		return realpath( $dir );
	}

	public static function get_files( $filename = null, $namespace = 'catalog' ) {
		$arResult = array();

		/**
		 * Get all folder objects
		 */
		$dir     = static::get_dir( $namespace );
		$objects = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		/**
		 * Check objects name
		 */
		foreach ( $objects as $path => $object ) {
			if ( ! $object->isFile() || ! $object->isReadable() ) {
				continue;
			}

			// if ( 'xml' != strtolower( $object->getExtension() ) ) {
			// 	continue;
			// }

			if ( ! empty( $filename ) ) {
				/**
				 * Filename start with search string
				 */
				if ( 0 === strpos( $object->getBasename(), $filename ) ) {
					$arResult[] = $path;
				}
			} else {
				/**
				 * Get all xml files
				 */
				$arResult[] = $path;
			}
		}

		return $arResult;
	}

	// public function parseOwner()
	// {
	//     static::$parser->addListener("OwnerEvent", function (Event\OwnerEvent $ownerEvent) {
	//         // $owner = $ownerEvent->getPartner();
	//     });
	// }
	//
	// $args = wp_parse_args( $_args, array(
	//     'check_properties' => true,
	// ) );

	// if( true === $args['check_properties'] && empty($this->arProperties) ) {
	//     ProductModel::$check_properties = true;

	//     static::parse_properties_event();
	// }

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
		/**
		 * If u need one offer only for simple product (for ex.)
		 *
		 * @param array &$offers offers collection
		 */
		// foreach ($this->arOffers as $key => $offer)
		// {
		//     list($realkey) = explode('#', $key);

		//     if( isset($this->arOffers[$realkey]) && $this->arOffers[$realkey] !== $offer ) {
		//         $mainOffer = &$this->arOffers[$realkey];
		//         $mainOffer->price = max(array($mainOffer->price, $offer->price));
		//         $mainOffer->quantity += $offer->quantity;

		//         unset($mainOffer, $this->arOffers[$key]);
		//     }
		// }

		return $this->arOffers;
	}

	/********************************* Events *********************************/

	/**
	 * @param \CommerceMLParser\Model\Category $category
	 * @param null|\CommerceMLParser\Model\Category $parent
	 */
	private function add_category_recursive( $category, $parent = null ) {
		$id = $category->getId();

		$term = array(
			'name'        => $category->getName(),
			'taxonomy'    => 'product_cat',
			'description' => '', // 1c 8.2 not has a cat description?
		);

		if ( $parent ) {
			/**
			 * External string ID
			 */
			$term['parent_ext'] = $parent->getId();
		}

		$meta = $category->getProperties()->fetch();

		$this->arCategories[ $id ] = new ExchangeTerm( $term, $id, $meta );

		/** @var Collection $children of categories */
		$children = $category->getChilds();
		if ( ! $children->isEmpty() ) {
			foreach ( $children->fetch() as $child ) {
				$this->add_category_recursive( $child, $category );
			}
		}
	}

	function parse_categories_event( Event\CategoryEvent $categoryEvent ) {
		/** @todo check this */
		// $flatcategory = $categoryEvent->getFlatCategories()->fetch();

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

		$id   = $warehouse->getId();
		$term = array(
			'name'     => $warehouse->getName(),
			'taxonomy' => apply_filters( 'warehouseTaxonomySlug', DEFAULT_WAREHOUSE_TAX_SLUG ),
		);

		$this->arWarehouses[ $id ] = new ExchangeTerm( $term, $id );
	}

	function parse_properties_event( Event\PropertyEvent $propertyEvent ) {
		/** @var \CommerceMLParser\Model\Property */
		$property      = $propertyEvent->getProperty();
		$property_id   = $property->getId();
		$property_type = 'Строка' == $property->getType() ? 'text' : 'select';

		$attribute = new ExchangeAttribute( array(
			'attribute_label' => $property->getName(),
			'attribute_type'  => $property_type,
		), $property_id );

		$values = $property->getValues();
		foreach ( $values as $term_id => $name ) {
			$attribute->add_term( new ExchangeTerm( array(
				'name'     => $name,
				'taxonomy' => $attribute->get_slug(),
			), $term_id ) );
		}

		$this->arProperties[ $property_id ] = $attribute;
	}

	function parse_products_event( Event\ProductEvent $productEvent ) {
		/** @var \CommerceMLParser\Model\Product */
		$product = $productEvent->getProduct();

		$id = $product->getId();

		$this->arProducts[ $id ] = new ExchangeProduct( array(
			'post_title'   => $product->getName(),
			'post_excerpt' => $product->getDescription(),
		), $id );

		/**
		 * Set category
		 * @var Collection $categoriesCollection List of EXT
		 */
		$categoriesCollection = $product->getCategories();

		if ( ! $categoriesCollection->isEmpty() ) {
			/**
			 * @var String $category External code
			 */
			foreach ( $categoriesCollection as $category ) {
				$obCategory = new ExchangeTerm( array( 'taxonomy' => 'product_cat' ), $category );
				$this->arProducts[ $id ]->set_relationship( 'product_cat', $obCategory );
			}
		}

		/**
		 * Set proerties
		 */
		$propertiesCollection = $product->getProperties();

		if ( ! $propertiesCollection->isEmpty() ) {

			/**
			 * @var \CommerceMLParser\Model\Types\PropertyValue $property
			 */
			foreach ( $propertiesCollection as $PropertyValue ) {
				$propertyId    = $PropertyValue->getId();
				$propertyValue = $PropertyValue->getValue();

				$propertiesAsRequisites = (array) apply_filters( 'ParsePropertiesAsRequisites', array(
					'hotsale' => 'a35a3bd2-d12a-11e7-a4f2-0025904bff5d',
					'newer'   => 'b0eff642-d12a-11e7-a4f2-0025904bff5d'
				) );

				$disallow = false;

				foreach ( $propertiesAsRequisites as $metakey => $external ) {
					if ( $external == $propertyId ) {
						$this->arProducts[ $id ]->set_meta( $metakey, $propertyValue );
						$disallow = true;
						break;
					}
				}

				if ( ! $disallow && isset( $this->arProperties[ $propertyId ] ) ) {
					$this->arProducts[ $id ]->set_relationship( 'properties', $this->arProperties[ $propertyId ],
						$propertyValue );
				}
			}
		}

		/**
		 * Set developer
		 * @var Collection $developersCollection List of..
		 */
		$developersCollection = $product->getManufacturer();

		if ( ! $developersCollection->isEmpty() ) {
			/**
			 * @var \CommerceMLParser\Model\Types\Partner $developer Изготовитель
			 */
			foreach ( $developersCollection as $developer ) {
				$developer_id   = $developer->getId();
				$developer_args = array(
					'name'        => $developer->getName(),
					'description' => $developer->getComment(),
					// 'taxonomy'    => apply_filters( 'developerTaxonomySlug', DEFAULT_DEVELOPER_TAX_SLUG ),
				);

				$this->arProducts[ $id ]->set_meta( 'Производитель',
					array_merge( $developer_args, array( 'external' => $developer_id ) ) );
				// $developer_term = new ExchangeTerm( $developer_args, $developer_id );
				// $this->arDevelopers[ $developer_id ] = $developer_term;
				// $this->arProducts[ $id ]->set_relationship( 'developer', $developer_term );
			}
		}


		/**
		 * Set requisites
		 */
		/**
		 * Only one base unit for simple
		 * @var collection current
		 */
		$baseunit = $product->getBaseUnit()->current();

		if ( ! $baseunit_name = $baseunit->getNameInterShort() ) {
			$baseunit_name = $baseunit->getNameFull();
		}

		/** @var Collection $taxRatesCollection СтавкиНалогов */
		$taxRatesCollection = $product->getTaxRate();
		$taxRate            = $taxRatesCollection->current();

		$meta = array(
			'_sku'  => $product->getSku(),
			'_unit' => $baseunit_name,
			'_tax'  => $taxRate->getRate(),
		);

		if ( $barcode = $product->getBarcode() ) {
			$meta['_barcode'] = $barcode;
		}

		$excludeRequisites = apply_filters( 'parseProductExcludeRequisites', array(
			'ВидНоменклатуры',
			'ТипНоменклатуры',
			'Код'
		) );

		/** @var \CommerceMLParser\Model\Types\RequisiteValue $requisite */
		foreach ( $product->getRequisites()->fetch() as $requisite ) {
			if ( in_array( $requisite->getName(), $excludeRequisites ) ) {
				continue;
			}

			$meta[ $requisite->getName() ] = $requisite->getValue();
		}

		$characteristics = $product->getCharacteristics();
		if ( ! $characteristics->isEmpty() ) {
			$meta['_characteristics'] = array();

			/** @var \CommerceMLParser\Model\Types\ProductCharacteristic $characteristic */
			foreach ( $characteristics->fetch() as $characteristic ) {
				$meta['_characteristics'][] = $characteristic->getId();
			}
		}

		$this->arProducts[ $id ]->set_meta( $meta );
	}

	function parse_offers_event( Event\OfferEvent $offerEvent ) {
		/** @var \CommerceMLParser\Model\Offer */
		$offer = $offerEvent->getOffer();

		$id = $offer->getId();

		/** @var String */
		// @list($product_id, $offer_id) = explode('#', $id);

		$quantity = $offer->getQuantity();

		/**
		 * Only one price coast for simple
		 */
		$price  = 0;
		$prices = $offer->getPrices();

		if ( ! $prices->isEmpty() ) {
			$price = $offer->getPrices()->current()->getPrice();
		}

		/**
		 * @todo
		 * Change product id to offer id for multiple offres
		 */
		$offerArgs = array(
			'post_title' => $offer->getName(),
			// 'post_excerpt' => $offer->getDescription(),
			'post_type'  => 'offer',
		);

		if ( isset( $this->arOffers[ $id ] ) ) {
			// $this->arOffers[ $id ]->merge( $offerArgs, $id );
		} else {
			$this->arOffers[ $id ] = new ExchangeOffer( $offerArgs, $id );
		}

		$meta = array();

		if ( $price ) {
			$meta['_price']         = $price;
			$meta['_regular_price'] = $price;
		}

		if ( null !== $quantity ) {
			$this->arOffers[ $id ]->set_quantity( $quantity );
		}

		// Function not exists!
		// if( $weight = $offer->getWeight() ) {
		//     $meta['_weight'] = $weight;
		// }

		/** @var collection [description] */
		$warehousesCollection = $offer->getWarehouses();
		if ( ! $warehousesCollection->isEmpty() ) {

			$meta['_stock_wh'] = array();
			foreach ( $warehousesCollection as $warehouse ) {
				$warehouse_id = $warehouse->getId();
				$qty          = $warehouse->getQuantity();

				$warehouse = new ExchangeTerm( array(
					'taxonomy' => apply_filters( 'warehouseTaxonomySlug', DEFAULT_WAREHOUSE_TAX_SLUG )
				), $warehouse_id );

				if ( 0 < $qty ) {
					$this->arOffers[ $id ]->set_relationship( 'warehouse', $warehouse );
				} else {
					/**
					 * @todo remove relationship
					 */
				}

				$meta['_stock_wh'][ $warehouse->get_external() ] = $qty;
			}
		}

		$this->arOffers[ $id ]->set_meta( $meta );
	}

	// ====================================================================== //

	/**
	 * Collect and correct the requisites to the properties data
	 */
	private function parse_requisites() {
		/**
		 * @var array $ParseRequisitesAsCategories as $termSlug => $termLabel
		 * @todo think about: maybe need custom taxonomies instead cats
		 */
		$ParseRequisitesAsCategories = (array) apply_filters( 'ParseRequisitesAsCategories',
			array( 'new' => 'Новинка' ) );

		/**
		 * @var array $ParseRequisitesAsDevelopers ,
		 * @var array $ParseRequisitesAsWarehouses as $termLabel
		 */
		$ParseRequisitesAsDevelopers = (array) apply_filters( 'ParseRequisitesAsDevelopers',
			array() ); // 'Производитель', 'мшПроизводитель'
		$ParseRequisitesAsWarehouses = (array) apply_filters( 'ParseRequisitesAsWarehouses', array( 'Склад' ) );

		/**
		 * @var array $ParseRequisitesAsProperties as $taxonomySlug => $taxonomyLabel
		 */
		$ParseRequisitesAsProperties = (array) apply_filters( 'ParseRequisitesAsProperties', array(
			'size'  => 'Размер',
			'brand' => 'Производитель',
		) );

		/**
		 * @note Do not merge for KISS
		 */
		if ( empty( $ParseRequisitesAsCategories ) &&
		     empty( $ParseRequisitesAsProperties ) &&
		     empty( $ParseRequisitesAsManufacturer ) &&
		     empty( $ParseRequisitesAsWarehouses )
		) {
			return;
		}

		foreach ( $this->arProducts as $i => $product ) {
			/**
			 * Parse categories from products
			 */
			if ( ! empty( $ParseRequisitesAsCategories ) ) {
				/**
				 * @var string  Default taxonomy name by woocommerce
				 */
				$taxonomyName = 'product_cat';

				foreach ( $ParseRequisitesAsCategories as $termSlug => $termName ) {
					/**
					 * Get term from product by term name
					 */
					if ( $meta = $product->get_meta( $termName ) ) {
						/**
						 * @param array  ex.: [ name => Новинка, slug => new, taxonomy => product_cat ]
						 *
						 * @var ExchangeTerm
						 */
						$term = new ExchangeTerm( array(
							'name'     => $termName,
							'slug'     => $termSlug,
							'taxonomy' => $taxonomyName,
						) );

						/**
						 * Add term. Sort (unique) by external code
						 */
						$this->arCategories[ $term->get_external() ] = $term;

						/**
						 * Set product relative
						 *
						 * @param Object property name with list of terms
						 */
						$product->set_relationship( 'product_cat', $term );
					}

					/**
					 * Delete replaced or empty
					 */
					$product->del_meta( $termName );
				}
			}

			/**
			 * Parse developers from products
			 */
			if ( ! empty( $ParseRequisitesAsDevelopers ) ) {
				/**
				 * @var string  Default taxonomy name by woocommerce
				 */
				$taxonomyName = apply_filters( 'developerTaxonomySlug', DEFAULT_DEVELOPER_TAX_SLUG );

				foreach ( $ParseRequisitesAsDevelopers as $termName ) {
					/**
					 * Get term from product by term name
					 */
					if ( $meta = $product->get_meta( $termName ) ) {
						/**
						 * @param array  ex.: [ name => НазваниеПроизводителя, taxonomy => developer ]
						 *
						 * @var ExchangeTerm
						 */
						$term = new ExchangeTerm( array(
							'name'     => $meta,
							'taxonomy' => $taxonomyName,
						) );

						/**
						 * Add term. Sort (unique) by external code
						 */
						$this->arDevelopers[ $term->get_external() ] = $term;

						/**
						 * Set product relative
						 *
						 * @param Object property name with list of terms
						 */
						$product->set_relationship( 'developer', $term );
					}

					/**
					 * Delete replaced or empty
					 */
					$product->del_meta( $termName );
				}
			}

			/**
			 * Parse warehouses from products
			 */
			if ( ! empty( $ParseRequisitesAsWarehouses ) ) {
				/**
				 * @var string  Default taxonomy name by woocommerce
				 */
				$taxonomyName = apply_filters( 'warehouseTaxonomySlug', DEFAULT_WAREHOUSE_TAX_SLUG );

				foreach ( $ParseRequisitesAsWarehouses as $termName ) {
					/**
					 * Get term from product by term name
					 */
					if ( $meta = $product->get_meta( $termName ) ) {
						/**
						 * @param array  ex.: [ name => НазваниеСклада, taxonomy => warehouse ]
						 *
						 * @var ExchangeTerm
						 */
						$term = new ExchangeTerm( array(
							'name'     => $meta,
							'taxonomy' => $taxonomyName,
						) );

						/**
						 * Add term. Sort (unique) by external code
						 */
						$this->arDevelopers[ $term->get_external() ] = $term;

						/**
						 * Set product relative
						 *
						 * @param Object property name with list of terms
						 */
						$product->set_relationship( 'warehouse', $term );
					}

					/**
					 * Delete replaced or empty
					 */
					$product->del_meta( $termName );
				}
			}

			/**
			 * Parse properties from products
			 */
			if ( ! empty( $ParseRequisitesAsProperties ) ) {

				foreach ( $ParseRequisitesAsProperties as $taxonomySlug => $taxonomyName ) {
					if ( $meta = $product->get_meta( $taxonomyName ) ) {
						/**
						 * If taxonomy not exists
						 */
						if ( empty( $this->arProperties[ $taxonomySlug ] ) ) {
							/**
							 * Need create for collect terms
							 */
							$this->arProperties[ $taxonomySlug ] = new ExchangeAttribute( (object) array(
								'attribute_label' => $taxonomyName,
								'attribute_name'  => $taxonomySlug,
							), $taxonomySlug );
						}

						/**
						 * Next work with created/exists taxonomy
						 * @var ExchangeAttribute
						 */
						$taxonomy = $this->arProperties[ $taxonomySlug ];

						/**
						 * @param array  ex.: [ name => НазваниеСвойства, taxonomy => pa_size ]
						 *
						 * @var ExchangeTerm
						 */
						if ( ! is_array( $meta ) ) {
							$meta = array( 'name' => $meta );
						}
						$term = new ExchangeTerm( $meta );

						/**
						 * Unique external
						 */
						$extSlug = $taxonomy->get_slug();
						if ( 0 !== strpos( $extSlug, 'pa_' ) ) {
							$extSlug = 'pa_' . $extSlug;
						}
						$term->set_external( $extSlug . '/' . $term->get_slug() );

						$term_slug = $taxonomy->get_external() . '-' . $term->get_slug();

						/**
						 * Unique slug (may be equal slugs on other taxonomy)
						 */
						$term->set_slug( $term_slug );

						/**
						 * Collect in taxonomy
						 * @note correct taxonomy in term by attribute
						 */
						$taxonomy->add_term( $term );

						/**
						 * Set product relative
						 *
						 * @param Object property name with list of terms
						 */
						$product->set_relationship( 'properties', $taxonomy, $term->get_external() );
					}

					/**
					 * Delete replaced or empty
					 */
					$product->del_meta( $taxonomyName );
				}
			}
		}
	}

	/**
	 * @todo add documentation
	 */
	private function prepare_offers() {
		foreach ( $this->arOffers as $i => $ExchangeOffer ) {
			/**
			 * @var String for ex. b9006805-7dde-11e8-80cb-70106fc831cf
			 */
			$ext = $ExchangeOffer->get_raw_external();

			$offer_ext = '';

			/**
			 * @var String for ex. b9006805-7dde-11e8-80cb-70106fc831cf#d3e195ce-746f-11e8-80cb-70106fc831cf
			 */
			$product_ext = '';

			if ( false !== strpos( $ext, '#' ) ) {
				list( $product_ext, $offer_ext ) = explode( '#', $ext );
			}

			/**
			 * if is have several offers, merge them to single
			 */
			if ( $offer_ext ) {
				/**
				 * If is no has a base (without #) offer
				 */
				if ( ! isset( $this->arOffers[ $product_ext ] ) ) {
					$this->arOffers[ $product_ext ] = $ExchangeOffer;
					$this->arOffers[ $product_ext ]->set_external( $product_ext );
				}

				/**
				 * Set simple price
				 */
				$currentPrice = $ExchangeOffer->get_price();
				$basePrice    = $this->arOffers[ $product_ext ]->get_price();

				if ( $basePrice < $currentPrice ) {
					$this->arOffers[ $product_ext ]->set_price( $currentPrice );
				}

				/**
				 * Set simple qty
				 */
				$currentQty = $ExchangeOffer->get_quantity();
				$baseQty    = $this->arOffers[ $product_ext ]->get_quantity();

				$this->arOffers[ $product_ext ]->set_quantity( $baseQty + $currentQty );

				unset( $this->arOffers[ $i ] );
			}
		}
	}
}
