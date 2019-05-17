<?php

namespace NikolayS93\Exchange\Model;

class Relationship
{
    public $taxonomy;
    public $external;
    public $value = 0;

    function __construct( $arArgs )
    {
        foreach ( (array) $arArgs as $k => $arg)
        {
            if( property_exists($this, $k) ) $this->$k = $arg;
        }
    }

    function getExternal()
    {
        return $this->external;
    }

    function getValue()
    {
        return $this->value;
    }

    function setValue($value)
    {
        $this->value = (int) $value;
    }

    function getTaxonomy()
    {
        return $this->taxonomy;
    }
}