<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Decorator\ProductDecorator;
use NikolayS93\Exchange\Decorator\OfferDecorator;
use NikolayS93\Exchange\Decorator\TermDecorator;
use NikolayS93\Exchange\Model\TermModel;
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

    function __init()
    {
        $Parser = \CommerceMLParser\Parser::getInstance();
        $Parser->addListener("CategoryEvent",  array($this, 'parseCategoriesEvent'));
        $Parser->addListener("WarehouseEvent", array($this, 'parseWarehousesEvent'));
        $Parser->addListener("PropertyEvent",  array($this, 'parsePropertiesEvent'));
        $Parser->addListener("ProductEvent",   array($this, 'parseProductsEvent'));
        $Parser->addListener("OfferEvent",     array($this, 'parseOffersEvent'));

        /** 1c no has develop section (values only)
        $Parser->addListener("DeveloperEvent", array($this, 'parseDevelopersEvent')); */

        $Parser->parse( static::get_file( FILENAME ) );

        $this->prepare();
    }

    public static function get_dir( $namespace = '' )
    {
        $dir = trailingslashit( EX_DATA_DIR . $namespace );
        if( !is_dir($dir) ) {
            /** Try create */
            @mkdir($dir, 0777, true) or ex_error( printf(
                __("<strong>%s</strong>: Sorry but <strong>%s</strong> not has write permissions", DOMAIN),
                __("Fatal error", DOMAIN),
                $dir
            ) );
        }

        /** Check again */
        if( !is_dir($dir) ) {
            ex_error( printf(
                __("<strong>%s</strong>: Sorry but <strong>%s</strong> not readble", DOMAIN),
                __("Fatal error", DOMAIN),
                $dir
            ) );
        }

        return realpath($dir);
    }

    public static function get_file( $filename = null, $namespace = 'catalog' )
    {
        $dir = static::get_dir( $namespace );
        $objects = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($objects as $path => $object) {
            if( !$object->isFile() || !$object->isReadable() ) continue;
            if( 'xml' != strtolower($object->getExtension()) ) continue;
            if( false === strpos( $object->getBasename(), $filename ) ) continue;

            return $path;
        }

        return '';
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
        foreach ($this->arOffers as $key => $offer)
        {
            list($realkey) = explode('#', $key);

            if( isset($this->arOffers[$realkey]) && $this->arOffers[$realkey] !== $offer ) {
                $mainOffer = &$this->arOffers[$realkey];
                $mainOffer->price = max(array($mainOffer->price, $offer->price));
                $mainOffer->quantity += $offer->quantity;

                unset($mainOffer, $this->arOffers[$key]);
            }
        }

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

        if( $parent ) $term['parent_ext'] = $parent->getId();

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

        $id = $property->getId();
        $tax = array(
            'name' => $property->getName(),
        );

        $taxonomy = new ExchangeTaxonomy( $tax, $id );
        $taxonomy_slug = $taxonomy->getSlug();

        foreach ($property->getValues()->fetch() as $id => $name) {
            $taxonomy->__add( new ExchangeTerm( array(
                'name' => $name,
                'taxonomy' => $taxonomy_slug,
            ), $id ) );
        }

        $this->arProperties[ $id ] = $taxonomy;
    }

    function parseProductsEvent(Event\ProductEvent $productEvent)
    {
        /** @var CommerceMLParser\Model\Product */
        $product = $productEvent->getProduct();

        $id = $product->getId();

        /**
         * Only one base unit for simple
         */
        $baseunit = $product->getBaseUnit()->current();

        if( !$baseunit_name = $baseunit->getNameInterShort() ) {
            $baseunit_name = $baseunit->getNameFull();
        }

        $meta = array(
            '_sku'           => $product->getSku(),
            '_barcode'       => $product->getBarcode(),
            '_unit'          => $baseunit_name,
        );


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

        $this->arProducts[ $id ] = new ExchangeProduct( array(
            'post_title'   => $product->getName(),
            'post_excerpt' => $product->getDescription(),
        ), $id, $meta );
    }

    function parseOffersEvent(Event\OfferEvent $offerEvent)
    {
        /** @var CommerceMLParser\Model\Offer */
        $offer = $offerEvent->getOffer();

        $id = $offer->getId();

        /** @var String */
        @list($product_id, $offer_id) = explode('#', $id);

        $quantity = $offer->getQuantity();

        /**
         * Only one price coast for simple
         */
        $price = $offer->getPrices()->current()->getPrice();

        $meta = array(
            '_price'         => $price,
            '_regular_price' => $price,
            '_manage_stock'  => 'yes',
            '_stock_status'  => $quantity ? 'instock' : 'outofstock',
            '_stock'         => $quantity,
            '_weight'        => $product->getWeight(),
            // '_stock_wh'      => array(),
        );

        $this->arOffers[ $id ] = new ExchangeOffer(array(
            'post_title'   => $product->getName(),
            'post_excerpt' => $product->getDescription(),
            'post_type'    => 'offer',
        ), $offer_id, $meta);
    }

    // ====================================================================== //
    private function prepare()
    {
        $ParseRequisitesAsCategories    = (array) apply_filters('ParseRequisitesAsCategories', array('new' => 'Новинка'));
        $ParseRequisitesAsProperties    = (array) apply_filters('ParseRequisitesAsProperties', array('size' => 'Размер'));
        $ParseRequisitesAsManufacturers = (array) apply_filters('ParseRequisitesAsManufacturers', array('manufacturer' => 'Производитель'));
        $ParseRequisitesAsWarehouses    = (array) apply_filters('ParseRequisitesAsWarehouses', array('warehouse' => 'Склад'));

        $RequisitesAsProperties = array(
            'arCategories'    => $ParseRequisitesAsCategories,
            'arProperties'    => $ParseRequisitesAsProperties,
            'arManufacturers' => $ParseRequisitesAsManufacturers,
            'arWarehouses'    => $ParseRequisitesAsWarehouses,
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

                        case 'arManufacturers':
                            $tax = 'manufacturer';
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
                        /**
                         * @var string $_tax may be use as NikolayS93\Exchange\Model\ProductModel->$_tax
                         *          arCategories, arProperties, arManufacturers, arWarehouses
                         * @var string $externalCode have the form as new, size, manufacturer, warehouse
                         *          or 700898df-83f3-11e7-afd4-1c872c77ed7c
                         * @var string $propertyName Term name
                         *          Новинка, Размер, Производитель, Склад
                         * @var $this object with all collections
                         */
                        if( isset($product->requisites[ $propertyName ]) ) {
                            /**
                             * Collect tax data from products
                             * @note Has a Warning
                             */
                            // @$this->$_tax[ $externalCode ] = $propertyName;
                            // and to the same as
                            /**
                             * @var array $thisTax list of term in tax
                             */
                            $thisTax = &$this->$_tax;
                            $externalCode = strtolower($externalCode);

                            /** @var string $propertyExternalCode external code for propery values */
                            $propertyExternalCode = strtolower(Utils::esc_cyr($product->requisites[ $propertyName ]));

                            if( 'arProperties' == $_tax ) {
                                if( !isset($thisTax[ $externalCode ]) || !is_array($thisTax[ $externalCode ]) ) {
                                    $thisTax[ $externalCode ] = array();
                                }

                                /** Do not rerite name if is exists */
                                if( empty($thisTax[ $externalCode ]['name']) ) {
                                    $thisTax[ $externalCode ]['name'] = $propertyName;
                                }

                                if( empty($thisTax[ $externalCode ]['slug']) ) {
                                    $thisTax[ $externalCode ]['slug'] = strtolower(Utils::esc_cyr($propertyName));
                                }

                                if( !isset($thisTax[ $externalCode ]['values']) || !is_array($thisTax[ $externalCode ]['values']) ) {
                                    $thisTax[ $externalCode ]['values'] = array();
                                }

                                $thisTax[ $externalCode ]['values'][ $propertyExternalCode ]
                                    = $term
                                    = new TermModel( $propertyExternalCode, (object) array(
                                        'name' => $product->requisites[ $propertyName ],
                                        'slug' => $propertyExternalCode,
                                    ), 'XML/' . $externalCode );

                                /**
                                 * Change data in a product
                                 * @var $tax $_tax in product model
                                 */
                                $productTax = &$this->arProducts[$i]->$tax;

                                if( !isset($productTax[ $externalCode ]) || !is_array($productTax[ $externalCode ]) ) {
                                    $productTax[ $externalCode ] = array();
                                }

                                $productTax[ $externalCode ][] = $term->get_external(); // $propertyExternalCode;
                            }
                            else {
                                $thisTax[]
                                    = $term
                                    = new TermModel( $propertyExternalCode, (object) array(
                                        'name' => $product->requisites[ $propertyName ],
                                        'slug' => $propertyExternalCode,
                                    ) );

                                /**
                                 * Change data in a product
                                 * @var $tax $_tax in product model
                                 */
                                $productTax = &$this->arProducts[$i]->$tax;
                                $productTax[] = $term->get_external();
                            }

                            unset($this->arProducts[$i]->requisites[ $propertyName ]);
                        }
                    }
                }

                /**
                 * Set manufacturer terms
                 */
                if( is_array($product->manufacturer) ) {
                    foreach ($product->manufacturer as $ext => $manufacturer_name)
                    {
                        $this->arManufacturers[ $ext ] = new TermModel( $ext, (object) array(
                            'name' => $manufacturer_name,
                            'slug' => strtolower(Utils::esc_cyr($manufacturer_name)),
                        ) );
                    }
                }

                if( is_array($product->properties) ) {
                    foreach ($product->properties as $ext => $property_name)
                    {
                        if( !isset($this->arProperties[ $ext ]['values'][ $property_name ]) ) {

                            $external = strtolower(Utils::esc_cyr($property_name));
                            $TermModel = new TermModel( $ext, (object) array(
                                'name' => $property_name,
                                'slug' => strtolower(Utils::esc_cyr($property_name)),
                            ), 'xml/' . $this->arProperties[ $ext ]['slug'] . '/' . $external );

                            $this->arProperties[ $ext ]['values'][ $external ] = $TermModel;
                            $product->properties[ $ext ] = $TermModel->get_external();
                        }
                    }
                }

                /**
                 * Do fetch normalize product catalog
                 */
                // $product->fetchOffers();

            endforeach;
        }
    }

    /**
     * [fillExistsProductData description]
     * @param  array  &$products      products or offers collections
     * @param  boolean $orphaned_only [description]
     * @return [type]                 [description]
     */
    static public function fillExistsProductData( &$products, $orphaned_only = false )
    {
        /** @global wpdb wordpress database object */
        global $wpdb;

        /** @var List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /** @var array list of objects exists from posts db */
        $exists = array();

        /** @var $product NikolayS93\Exchange\Model\ProductModel or */
        /** @var $product NikolayS93\Exchange\Model\OfferModel */
        foreach ($products as $rawExternalCode => $product)
        {
            if( !$orphaned_only || ($orphaned_only && !$product->get_id()) ) {
                $externals[] = "`post_mime_type` = 'XML/$rawExternalCode'";
            }
        }

        if( !empty($externals) ) {
            //ID, post_date, post_date_gmt, post_name, post_mime_type
            $exists_query = "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = 'product'
                AND (\n". implode(" \t\n OR ", $externals) . "\n)";

            $exists = $wpdb->get_results( $exists_query );

            unset($externals);
        }

        foreach ($exists as $exist)
        {
            /** @var post_mime_type without XML\ */
            $mime = substr($exist->post_mime_type, 4);

            if( $mime && isset($products[ $mime ]->post) ) {
                /** @var stdObject (similar WP_Post) */
                $post = &$products[ $mime ]->post;
                $_post = $products[ $mime ]->post;

                $_post->ID = (int) $exist->ID;

                /**
                 * If is already exists
                 */
                if( !empty($exist->post_name) )    $_post->post_name = (string) $exist->post_name;
                if( !empty($exist->post_title) )   $_post->post_title = (string) $exist->post_title;
                if( !empty($exist->post_content) ) $_post->post_content = (string) $exist->post_content;
                if( !empty($exist->post_excerpt) ) $_post->post_excerpt = (string) $exist->post_excerpt;

                /**
                 * Pass post modified (Update product only)
                 */
                $_post->post_date = (string) $exist->post_date;
                $_post->post_date_gmt = (string) $exist->post_date_gmt;

                /**
                 * What do you want to keep the same?
                 */
                $post = apply_filters( 'exchange-keep-product', $_post, $post, $exist );
            }
        }
    }

    static public function fillExistsTermData( &$terms ) // , $taxonomy = ''
    {
        /** @global wpdb wordpress database object */
        global $wpdb;

        /** @var boolean get data for items who not has term_id */
        $orphaned_only = true;

        /** @var List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /** @var array list of objects exists from posts db */
        $_exists = array();
        $exists = array();

        foreach ($terms as $rawExternalCode => $term) {
            $_external = $term->get_external();
            $_p_external = $term->get_parent_external();

            if( !$term->get_id() ) {
                $externals[] = "`meta_value` = '". $_external ."'";
            }

            if( $_p_external && $_external != $_p_external && !$term->get_parent_id() ) {
                $externals[] = "`meta_value` = '". $_p_external ."'";
            }
        }

        /**
         * Get from database
         */
        if( !empty($externals) ) {
            $externals = array_unique($externals);

            $exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '". EX_EXT_METAFIELD ."'
                    AND (". implode(" \t\n OR ", $externals) . ")";

            $_exists = $wpdb->get_results( $exists_query );
        }

        /**
         * Resort for convenience
         */
        foreach($_exists as $exist)
        {
            $exists[ $exist->meta_value ] = $exist;
        }
        unset($_exists);

        foreach ($terms as &$term)
        {
            if(!empty( $exists[ $term->get_external() ] )) {
                $term->set_id( $exists[ $term->get_external() ]->term_id );
                $term->set_property('meta_id', $exists[ $term->get_external() ]->meta_id);
            }

            if(!empty( $exists[ $term->get_parent_external() ] )) {
                $term->set_parent_id( $exists[ $term->get_parent_external() ]->term_id );
            }
        }
    }

    /**
     * for compatibility only
     */
    public function parse_exists_products( $orphaned_only = false )
    {
        /**
         * @todo multiple offers
         */
        static::fillExistsProductData( $this->arProducts, $orphaned_only );
    }

    public function parse_exists_terms()
    {
        foreach ($this->terms as $taxonomy => &$arTerms) {
            static::fillExistsTermData( $arTerms, $taxonomy );
        }
    }
}
