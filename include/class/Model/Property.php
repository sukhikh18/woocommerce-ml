<?php

namespace NikolayS93\Exchange\Model;

class Property {
	public $name;
	public $value = '';
	public $position = 10;
	public $is_visible = 0;
	public $is_variation = 0;
	public $is_taxonomy = 0;

    function __construct( $args ) {
        array_walk( $args, function ( $item, $key ) {
            if ( property_exists( $this, $key ) ) {
                $this->$key = $item;
            }
        } );
    }
}
