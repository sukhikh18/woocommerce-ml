<?php


namespace NikolayS93\Exchanger\Model\Interfaces;


interface Identifiable {
	function get_id();

	function set_id( $id );

	function get_slug();

	function set_slug( $slug );
}