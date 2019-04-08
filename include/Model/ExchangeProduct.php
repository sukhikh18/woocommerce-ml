<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeTaxonomy;

class Relationship
{
    public $external;
    public $id = 0;

    function __construct( $arArgs )
    {
        foreach ($arArgs as $k => $arg)
        {
            if( property_exists($this, $k) ) $this->$k = $arg;
        }
    }
}

class ExchangeProduct extends ExchangePost
{
    /**
     * "Product_cat" type wordpress terms
     * @var Array
     */
    public $product_cat = array();

    /**
     * Product properties with link by term (has taxonomy/term)
     * @var Array
     */
    public $properties = array();

    public $warehouse = array();

    /**
     * Single term. Link to developer (prev. created)
     * @var String
     */
    public $developer = array();
}