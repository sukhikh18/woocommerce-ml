<?php

namespace NikolayS93\Exchange\Decorator;

use NikolayS93\Exchange\Model\ProductModel;

/**
 * Product ParserModel to Product Wordpress Model
 */
class ProductDecorator
{
    /** @var NikolayS93\Exchange\Model\ProductModel */
    private $WPModelProduct;

    function __construct( \CommerceMLParser\Model\Product $product )
    {
        /** @var String */
        $external_id = $product->getId();

        $this->WPModelProduct = new ProductModel( $external_id, (object) array(
            'post_title' => $product->getName(),
            'post_excerpt' => $product->getDescription(),
            'post_content' => '',
        ) );

        $sku = $product->getSku();
        $this->WPModelProduct->set_property( 'sku', $sku );

        /** @var CommerceMLParser\ORM\Collection */
        $categories = $product->getCategories();
        $this->setCategories($categories);

        /** @var CommerceMLParser\ORM\Collection */
        $manufacturers = $product->getManufacturer();
        $this->setManufacturers($manufacturers);

        /** @var CommerceMLParser\ORM\Collection */
        $requisites = $product->getRequisites();
        $this->setRequisites($requisites);

        /** @var CommerceMLParser\ORM\Collection */
        $properties = $product->getProperties();
        $this->setProperties($properties);
        // static::setCharacteristics($product);

        /**
         * @todo For next version iterate (need check)
         */
        // $barcode = $_product->getBarcode();
        // $this->WPModelProduct->set_property( 'barcode', $barcode );
        //
        // /** @var $unit Collection Profstal required */
        // $unit = $_product->getBaseunit();
        // $this->WPModelProduct->set_property( 'unit', $unit );
        //
        // /** @var Collection */
        // $taxrate = $_product->getTaxrate()
        // $this->WPModelProduct->set_property( 'tax', $unit );
        //
        // /** @var Collection */
        // $images = $_product->getImages()
    }

    public function getModel()
    {
        return $this->WPModelProduct;
    }

    /**
     * @param \CommerceMLParser\ORM\Collection $categories
     */
    protected function setCategories( $categories )
    {
        $categories = $categories->fetch();

        /**
         * @todo check this
         * @var $categories Array: $i => $external_id
         */
        $this->WPModelProduct->set_taxonomy( 'product_cat', $categories );
    }

    /**
     * @param \CommerceMLParser\ORM\Collection $manufacturers
     */
    protected function setManufacturers( $manufacturers )
    {
        $arManufactures = array();

        /** @var $manufacturer CommerceMLParser\Model\Types\Partner */
        foreach ($manufacturers as $manufacturer)
        {
            /**
             * @var $name, $external_id String
             */
            $name = $manufacturer->getName();
            $external_id = $manufacturer->getId();

            $arManufactures[ $external_id ] = $name;
        }

        $this->WPModelProduct->set_taxonomy( 'manufacturer', $arManufactures );
    }

    /**
     * @param \CommerceMLParser\ORM\Collection $requisites
     */
    protected function setRequisites( $requisites )
    {
        $excludeRequisites = array('ВидНоменклатуры', 'ТипНоменклатуры', 'Код', 'ЭтоДиск', 'ЭтоШины');

        $arRequisites = array();

        /** @var $requisite CommerceMLParser\Model\Types\RequisiteValue */
        foreach ($requisites as $requisite)
        {
            $name = $requisite->getName();
            if( in_array($name, $excludeRequisites) ) continue;

            $value = $requisite->getValue();

            if( 'Полное наименование' === $name ) {
                $this->WPModelProduct->post->post_title = $value;
            }
            else {
                $arRequisites[ $name ] = $value;
            }
        }

        $this->WPModelProduct->set_taxonomy( 'requisites', $arRequisites );
    }

    /**
     * @param \CommerceMLParser\ORM\Collection $properties
     */
    protected function setProperties( $properties )
    {
        // $check = true === \EX_Product::$check_properties;
        $arProperties = array();

        /** @var $property CommerceMLParser\Model\Types\PropertyValue */
        foreach ($properties as $property)
        {
            $external_id = $property->getId();
            // if( in_array($external_id, $excludeProperties) ) continue;

            $value = $property->getValue();
            $arProperties[ $external_id ] = $value;

            // if( !isset($this->arProperties[ $external_id ]) ) {
            //     echo "Ошибка: свойство {$external_id} для значения {$value} не найдено.";
            // }
        }

        $this->WPModelProduct->set_taxonomy( 'properties', $arProperties );
    }

    // protected function setCharacteristics( \EX_Product $EX_Product, \CommerceMLParser\Model\Product $product )
    // {
    //     /**
    //      * Set characteristics
    //      */
    //     $arCharacteristics = array();

    //     /** @var CommerceMLParser\ORM\Collection */
    //     $characteristics = $product->getCharacteristics();
    // }
}