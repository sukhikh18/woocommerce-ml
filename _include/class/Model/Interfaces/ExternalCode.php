<?php

namespace NikolayS93\Exchanger\Model\Interfaces;

interface ExternalCode {
	static function get_external_key();

	function get_external();

	function get_raw_external();

	function set_external( $ext );
//	static function fillExistsFromDB( &$objects );
}