<?php


namespace NikolayS93\Exchange\Model\Interfaces;


interface Taxonomy {
	function add_term( $term );

	function get_terms();

	function reset_terms();
}