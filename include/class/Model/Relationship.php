<?php

namespace NikolayS93\Exchange\Model;

class Relationship {

	public $term_id;
	public $term_source;

	public $taxonomy;

	function __construct( $args ) {
		array_walk( $args, function ( $item, $key ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $item;
			}
		} );
	}
}
