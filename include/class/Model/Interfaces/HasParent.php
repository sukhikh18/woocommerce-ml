<?php


namespace NikolayS93\Exchange\Model\Interfaces;


interface HasParent {
	function get_parent_id();

	function get_parent_external();

	function set_parent_id( $term_id );

	function set_parent_external( $external );
}