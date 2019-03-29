<?php

trait ExchangeItemMeta
{
    private $meta = array();

    function getMeta( $key = '' )
    {
        if( $key ) {
            return isset($this->meta[$key]) ? $this->meta[$key] : null;
        }

        return (array) $this->meta;
    }

    function setMeta($key, $value = '')
    {
        if(!$key) return;

        if( is_array($key) ) {
            foreach ($key as $metakey => $metavalue)
            {
                $this->meta[ $metakey ] = $metavalue;
            }
        }
        else {
            $this->meta[ $key ] = $value;
        }
    }
}