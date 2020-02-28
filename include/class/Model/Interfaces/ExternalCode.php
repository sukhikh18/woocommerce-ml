<?php

namespace NikolayS93\Exchange\Model\Interfaces;

interface ExternalCode {
	function get_external();

	function set_external( $ext );

	static function fill_exists_from_DB( &$objects );
}