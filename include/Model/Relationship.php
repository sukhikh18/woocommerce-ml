<?php

namespace NikolayS93\Exchange\Model;

class Relationship
{
    public $taxonomy;
    public $external;
    public $id = 0;

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

    function get_id()
    {
        return $this->id;
    }

    function set_id($id)
    {
        $this->id = (int) $id;
    }

    function getTaxonomy()
    {
        return $this->taxonomy;
    }
}