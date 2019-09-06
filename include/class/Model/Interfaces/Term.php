<?php


namespace NikolayS93\Exchange\Model\Interfaces;


interface Term {
	function get_taxonomy();
	function set_taxonomy( $taxonomy );
	function get_term();
}