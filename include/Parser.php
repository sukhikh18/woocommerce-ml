<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeAttribute;
use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\ExchangeOffer;
use CommerceMLParser\ORM\Collection;
use CommerceMLParser\Event;

use CommerceMLParser\Creational\Singleton;

if ( !defined( 'ABSPATH' ) ) exit('You shall not pass');

class Parser
{
    use Singleton;

    /**
     * Resourses
     */
    private $arCategories = array();
    private $arDevelopers = array();
    private $arWarehouses = array();
    private $arProperties = array();
    private $arProducts   = array();
    private $arOffers     = array();

    /**
     * Временная переменная, нужна только для связи товаров с атрибутами
     * @var array
     */
    private $arTaxonomies = array();

    function __init()
    {
    }

    function __parse($files = array())
    {
        if( empty($files) ) return;

        $Parser = \CommerceMLParser\Parser::getInstance();
        $Parser->addListener("CategoryEvent",  array($this, 'parseCategoriesEvent'));
        $Parser->addListener("WarehouseEvent", array($this, 'parseWarehousesEvent'));
        $Parser->addListener("PropertyEvent",  array($this, 'parsePropertiesEvent'));
        $Parser->addListener("ProductEvent",   array($this, 'parseProductsEvent'));
        $Parser->addListener("OfferEvent",     array($this, 'parseOffersEvent'));
        /** 1c no has develop section (values only)
        $Parser->addListener("DeveloperEvent", array($this, 'parseDevelopersEvent')); */

        if( is_array($files) ) {
            foreach ($files as $file)
            {
                /**
                 * @todo set handler
                 */
                if(!is_readable($file)) die();

                $Parser->parse( $file );
            }
        }

        $this->prepare();
    }

    function __fillExists()
    {
        if( !empty($this->arCategories) ) {
            ExchangeTerm::fillExistsFromDB( $this->arCategories );
        }

        if( !empty($this->arDevelopers) ) {
            ExchangeTerm::fillExistsFromDB( $this->arDevelopers );
        }

        if( !empty($this->arWarehouses) ) {
            ExchangeTerm::fillExistsFromDB( $this->arWarehouses );
        }

        if( !empty($this->arProperties) ) {
            ExchangeAttribute::fillExistsFromDB( $this->arProperties );
        }

        if( !empty($this->arProducts) ) {
            ExchangeProduct::fillExistsFromDB( $this->arProducts );
            foreach ($this->arProducts as &$product)
            {
                $product->fillRelatives();
            }
        }

        if( !empty($this->arOffers) ) {
            ExchangeProduct::fillExistsFromDB( $this->arOffers );
            foreach ($this->arOffers as &$offer)
            {
                $offer->fillRelatives();
            }
        }
    }

    public static function getDir( $namespace = '' )
    {
        $dir = trailingslashit( Plugin::get_exchange_data_dir() . $namespace );

        if( !is_dir($dir) ) {
            /** Try create */
            @mkdir($dir, 0777, true) or Utils::error( printf(
                __("<strong>%s</strong>: Sorry but <strong>%s</strong> not has write permissions", DOMAIN),
                __("Fatal error", DOMAIN),
                $dir
            ) );
        }

        /** Check again */
        if( !is_dir($dir) ) {
            Utils::error( printf(
                __("<strong>%s</strong>: Sorry but <strong>%s</strong> not readble", DOMAIN),
                __("Fatal error", DOMAIN),
                $dir
            ) );
        }

        return realpath($dir);
    }

    public static function getFiles( $filename = null, $namespace = 'catalog' )
    {
        $arResult = array();

        /**
         * Get all folder objects
         */
        $dir = static::getDir( $namespace );
        $objects = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /**
         * Check objects name
         */
        foreach($objects as $path => $object)
        {
            if( !$object->isFile() || !$object->isReadable() ) continue;
            if( 'xml' != strtolower($object->getExtension()) ) continue;

            if( !empty($filename) ) {
                /**
                 * Filename start with search string
                 */
                if( 0 === strpos( $object->getBasename(), $filename ) ) {
                    $arResult[] = $path;
                }
            }
            else {
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

    //     static::parsePropertiesEvent();
    // }

    public function getCategories()
    {
        return $this->arCategories;
    }

    public function getDevelopers()
    {
        return $this->arDevelopers;
    }

    public function getWarehouses()
    {
        return $this->arWarehouses;
    }

    public function getProperties()
    {
        return $this->arProperties;
    }

    public function getProducts( $_args = array() )
    {
        return $this->arProducts;
    }

    public function getOffers()
    {
        /**
         * If u need one offer only for simple product (for ex.)
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

    /** @var CommerceMLParser\Model\Category $category */
    private function addCategoryRecursive( $category, $parent = null )
    {
        $id = $category->getId();

        $term = array(
            'name'        => $category->getName(),
            'taxonomy'    => 'product_cat',
            'description' => '', // 1c 8.2 not has a cat description?
        );

        if( $parent ) {
            /**
             * External string ID
             */
            $term['parent_ext'] = $parent->getId();
        }

        $meta = $category->getProperties()->fetch();

        $this->arCategories[ $id ] = new ExchangeTerm( $term, $id, $meta );

        /** @var CategoryCollection [description] */
        $childs = $category->getChilds();
        if( !$childs->isEmpty() ) {
            foreach ($childs->fetch() as $child)
            {
                $this->addCategoryRecursive($child, $category);
            }
        }
    }

    function parseCategoriesEvent(Event\CategoryEvent $categoryEvent)
    {
        /** @todo check this*/
        // $flatcategory = $categoryEvent->getFlatCategories()->fetch();

        /** @var CommerceMLParser\ORM\Collection */
        $categories = $categoryEvent->getCategories();

        if( !$categories->isEmpty() ) {
            foreach ($categories->fetch() as $category)
            {
                $this->addCategoryRecursive( $category );
            }
        }
    }

    function parseWarehousesEvent(Event\WarehouseEvent $warehouseEvent)
    {
        /** @var CommerceMLParser\Model\Warehouse */
        $warehouse = $warehouseEvent->getWarehouse();

        $id = $warehouse->getId();
        $term = array(
            'name'        => $warehouse->getName(),
            'taxonomy'    => 'warehouse',
        );

        $this->arWarehouses[ $id ] = new ExchangeTerm( $term, $id );
    }

    function parsePropertiesEvent(Event\PropertyEvent $propertyEvent)
    {
        /** @var CommerceMLParser\Model\Property */
        $property = $propertyEvent->getProperty();

        $property_id = $property->getId();
        $tax = array(
            'attribute_label' => $property->getName(),
        );

        $taxonomy = new ExchangeAttribute( $tax, $property_id );
        $taxonomy_slug = $taxonomy->getSlug();

        $values = $property->getValues();

        if( !empty($values) ) {
            foreach ($values as $term_id => $name)
            {
                $taxonomy->addTerm( new ExchangeTerm( array(
                    'name' => $name,
                    'taxonomy' => $taxonomy_slug,
                ), $term_id ) );
            }
        }

        $this->arProperties[ $property_id ] = $taxonomy;
    }

    function parseProductsEvent(Event\ProductEvent $productEvent)
    {
        /** @var CommerceMLParser\Model\Product */
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

        if( !$categoriesCollection->isEmpty() ) {
            /**
             * @var String $category External code
             */
            foreach ($categoriesCollection as $category)
            {
                $obCategory = new ExchangeTerm( array('taxonomy' => 'product_cat'), $category );
                $this->arProducts[ $id ]->setRelationship( 'product_cat', $obCategory );
            }
        }

        /**
         * Set proerties
         */
        $propertiesCollection = $product->getProperties();

        if( !$propertiesCollection->isEmpty() ) {
            /**
             * @var Types\PropertyValue $property
             */

            foreach ($propertiesCollection as $property)
            {
                $property_id = $property->getId();

                if( empty( $this->arProperties ) && empty( $this->arTaxonomies ) ) {
                    $this->arTaxonomies = getTaxonomies();
                }

                $obProperty = null;
                if( isset($this->arTaxonomies[ $property_id ]) ) {
                    $obProperty = $this->arTaxonomies[ $property_id ];
                }
                elseif( isset($this->arProperties[ $property_id ]) ) {
                    $obProperty = $this->arProperties[ $property_id ];
                }
                // else {
                //     $obProperty = \NikolayS93\Exchange\Model\getTaxonomyByExternal( $property_id );
                // }

                if( $obProperty ) {
                    $property_term = new ExchangeTerm( array('taxonomy' => $obProperty->getSlug()), $property_id );
                    $this->arProducts[ $id ]->setRelationship( 'property', $property_term );
                }
            }
        }

        /**
         * Set developer
         * @var Collection $developersCollection List of..
         */
        $developersCollection = $product->getManufacturer();

        if( !$developersCollection->isEmpty() ) {
            /**
             * @var Partner $developer Изготовитель
             */
            foreach ($developersCollection as $developer)
            {
                $developer_id = $developer->getId();
                $developer_args = array(
                    'name'        => $developer->getName(),
                    'description' => $developer->getComment(),
                    'taxonomy'    => 'manufacturer',
                );

                $developer_term = new ExchangeTerm( $developer_args, $developer_id );
                $this->arDevelopers[ $developer_id ] = $developer_term;
                $this->arProducts[ $id ]->setRelationship( 'developer', $developer_term );
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

        if( !$baseunit_name = $baseunit->getNameInterShort() ) {
            $baseunit_name = $baseunit->getNameFull();
        }

        /** @var Collection $taxRatesCollection СтавкиНалогов */
        $taxRatesCollection = $product->getTaxRate();
        $taxRate = $taxRatesCollection->current();

        $meta = array(
            '_sku'  => $product->getSku(),
            '_unit' => $baseunit_name,
            '_tax'  => $taxRate->getRate(),
        );

        if( $barcode = $product->getBarcode() ) {
            $meta['_barcode'] = $barcode;
        }

        $excludeRequisites = apply_filters('parseProductExcludeRequisites', array('ВидНоменклатуры', 'ТипНоменклатуры', 'Код'));

        foreach ($product->getRequisites()->fetch() as $requisite)
        {
            if( in_array($requisite->getName(), $excludeRequisites) ) continue;

            $meta[ $requisite->getName() ] = $requisite->getValue();
        }

        $characteristics = $product->getCharacteristics();
        if( !$characteristics->isEmpty() ) {
            $meta['_characteristics'] = array();

            foreach ($characteristics->fetch() as $characteristic)
            {
                $meta['_characteristics'][] = $characteristic->getId();
            }
        }

        $this->arProducts[ $id ]->setMeta( $meta );
    }

    function parseOffersEvent(Event\OfferEvent $offerEvent)
    {
        /** @var CommerceMLParser\Model\Offer */
        $offer = $offerEvent->getOffer();

        $id = $offer->getId();

        /** @var String */
        // @list($product_id, $offer_id) = explode('#', $id);

        $quantity = $offer->getQuantity();

        /**
         * Only one price coast for simple
         */
        $price = 0;
        $prices = $offer->getPrices();

        if( !$prices->isEmpty() ) {
            $price = $offer->getPrices()->current()->getPrice();
        }

        /**
         * @todo
         * Change product id to offer id for multiple offres
         */
        $offerArgs = array(
            'post_title'   => $offer->getName(),
            // 'post_excerpt' => $offer->getDescription(),
            'post_type'    => 'offer',
        );

        if( isset($this->arOffers[ $id ]) ) {
            // $this->arOffers[ $id ]->merge( $offerArgs, $id );
        }
        else {
            $this->arOffers[ $id ] = new ExchangeOffer($offerArgs, $id);
        }

        $meta = array();

        if( $price ) {
            $meta['_price'] = $price;
            $meta['_regular_price'] = $price;
        }

        if( null !== $quantity ) {
            $this->arOffers[ $id ]->set_quantity($quantity);
        }

        // '_weight'        => $offer->getWeight(),

        /** @var collection [description] */
        $warehousesCollection = $offer->getWarehouses();

        if( !$warehousesCollection->isEmpty() ) {

            $meta['_stock_wh'] = array();
            foreach ($warehousesCollection as $warehouse)
            {
                $warehouse_id = $warehouse->getId();
                $qty = $warehouse->getQuantity();

                $warehouse = new ExchangeTerm( array('taxonomy' => 'warehouse'), $warehouse_id );

                if( 0 < $qty ) {
                    $this->arOffers[ $id ]->setRelationship( 'warehouse', $warehouse );
                }
                else {
                    /**
                     * @todo remove relationship
                     */
                }

                $meta['_stock_wh'][ $warehouse->getExternal() ] = $qty;
            }
        }

        $this->arOffers[ $id ]->setMeta( $meta );
    }

    // ====================================================================== //
    private function prepare()
    {
        $ParseRequisitesAsCategories = (array) apply_filters('ParseRequisitesAsCategories', array('new' => 'Новинка'));
        $ParseRequisitesAsProperties = (array) apply_filters('ParseRequisitesAsProperties', array('size' => 'Размер'));
        $ParseRequisitesAsDevelopers = (array) apply_filters('ParseRequisitesAsDevelopers', array('developer' => 'мшПроизводитель'));
        $ParseRequisitesAsWarehouses = (array) apply_filters('ParseRequisitesAsWarehouses', array('warehouse' => 'Склад'));

        $RequisitesAsProperties = array(
            'arCategories' => $ParseRequisitesAsCategories,
            'arProperties' => $ParseRequisitesAsProperties,
            'arDevelopers' => $ParseRequisitesAsDevelopers,
            'arWarehouses' => $ParseRequisitesAsWarehouses,
        );

        /**
         * Collect and correct the requisites to the properties data
         */
        if( !empty($ParseRequisitesAsCategories) || !empty($ParseRequisitesAsProperties) || !empty($ParseRequisitesAsManufacturer) || !empty($ParseRequisitesAsWarehouses) ) {
            foreach ($this->arProducts as $i => $product) :

                foreach ($RequisitesAsProperties as $_tax => $map)
                {
                    switch ($_tax) {
                        case 'arProperties':
                            $tax = 'properties';
                            break;
                        case 'arCategories':
                            $tax = 'product_cat';
                            break;

                        case 'arDevelopers':
                            $tax = 'developer';
                            break;

                        case 'arWarehouses':
                            $tax = 'warehouse';
                            break;
                    }

                    /**
                     * Write to taxonomy: Cats, Props, Devs, Warh-es..
                     */
                    foreach ($map as $externalCode => $propertyName)
                    {
                        if( $meta = $product->getMeta($propertyName) ) {
                            // $taxonomy = $tax;

                            if( 'arProperties' == $_tax ) {
                                if( empty($this->arProperties[ $externalCode ]) ) {
                                    $this->arProperties[ $externalCode ] = new ExchangeAttribute( (object) array(
                                        'attribute_label' => $propertyName,
                                        'attribute_name' => $externalCode,
                                    ), $externalCode );
                                }

                                $term = new ExchangeTerm( array('name' => $meta) );

                                $taxonomy = $this->arProperties[ $externalCode ];
                                $taxonomy->addTerm( $term );
                            }
                            else {
                                $term = new ExchangeTerm( array('name' => $meta, 'taxonomy' => $externalCode) );
                                /**
                                 * error on php < 5.5
                                 * $this->$_tax[] = $term;
                                 */
                                array_push($this->$_tax, $term);
                            }

                            $product->setRelationship($tax, $term); // , $taxonomy
                        }

                        /**
                         * Delete empty
                         */
                        $product->delMeta($propertyName);
                    }
                }

                /**
                 * Set manufacturer terms
                 */
                // if( is_array($product->developer) ) {
                //     foreach ($product->developer as $ext => $manufacturer_name)
                //     {
                //         $this->arManufacturers[ $ext ] = new TermModel( $ext, (object) array(
                //             'name' => $manufacturer_name,
                //             'slug' => strtolower(Utils::esc_cyr($manufacturer_name)),
                //         ) );
                //     }
                // }

                // if( is_array($product->properties) ) {
                //     foreach ($product->properties as $ext => $property_name)
                //     {
                //         if( !isset($this->arProperties[ $ext ]['values'][ $property_name ]) ) {

                //             $external = strtolower(Utils::esc_cyr($property_name));
                //             $TermModel = new TermModel( $ext, (object) array(
                //                 'name' => $property_name,
                //                 'slug' => strtolower(Utils::esc_cyr($property_name)),
                //             ), 'xml/' . $this->arProperties[ $ext ]['slug'] . '/' . $external );

                //             $this->arProperties[ $ext ]['values'][ $external ] = $TermModel;
                //             $product->properties[ $ext ] = $TermModel->get_external();
                //         }
                //     }
                // }

                /**
                 * Do fetch normalize product catalog
                 */
                // $product->fetchOffers();

            endforeach;
        }

        foreach ($this->arOffers as $i => $ExchangeOffer)
        {
            /**
             * @var String for ex. b9006805-7dde-11e8-80cb-70106fc831cf
             */
            $ext = $ExchangeOffer->getRawExternal();

            $offer_ext = '';

            /**
             * @var String for ex. b9006805-7dde-11e8-80cb-70106fc831cf#d3e195ce-746f-11e8-80cb-70106fc831cf
             */
            $product_ext = '';

            if( false !== strpos($ext, '#') ) {
                list($product_ext, $offer_ext) = explode('#', $ext);
            }


            /**
             * if is have several offers, merge them to single
             */
            if( $offer_ext ) {
                /**
                 * If is no has a base (without #) offer
                 */
                if( !isset( $this->arOffers[ $product_ext ] ) ) {
                    $this->arOffers[ $product_ext ] = $ExchangeOffer;
                    $this->arOffers[ $product_ext ]->setExternal( $product_ext );
                }

                /**
                 * Set simple price
                 */
                $currentPrice = $ExchangeOffer->get_price();
                $basePrice = $this->arOffers[ $product_ext ]->get_price();

                if( $basePrice < $currentPrice ) {
                    $this->arOffers[ $product_ext ]->set_price( $currentPrice );
                }

                /**
                 * Set simple qty
                 */
                $currentQty = $ExchangeOffer->get_quantity();
                $baseQty = $this->arOffers[ $product_ext ]->get_quantity();

                $this->arOffers[ $product_ext ]->set_quantity( $baseQty + $currentQty );

                unset( $this->arOffers[$i] );
            }
        }
    }
}
