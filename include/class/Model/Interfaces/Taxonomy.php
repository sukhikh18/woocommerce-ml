<?php


namespace NikolayS93\Exchange\Model\Interfaces;


interface Taxonomy {
	function add_value( $term );

	function get_values();

	function reset_values();
}