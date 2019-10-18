<?php


namespace NikolayS93\Exchanger\Model\Interfaces;


interface Taxonomy {
	function add_value( $term );

	function get_values();

	function reset_values();
}