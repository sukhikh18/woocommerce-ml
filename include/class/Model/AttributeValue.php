<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\check_mode;
use function NikolayS93\Exchange\error;

class AttributeValue { // extends Term

	public $name = '';
	public $value = '';
	public $position = 0;
	public $is_visible = 1;
	public $is_variation = 0;
	public $is_taxonomy = 0;

	public function __construct( $args ) {
		if( $args instanceof AttributeTerm ) {
			// @todo
			return true;
		}

		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}
}
