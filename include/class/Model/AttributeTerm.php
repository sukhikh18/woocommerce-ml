<?php


namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Plugin;

class AttributeTerm implements ExternalCode {

	public $id;
	public $ext;

	public $term;

	public function __construct( $args, $ext = '' ) {
		$this->term = array();
		foreach ( $args as $key => $value ) {
			$this->term[ $key ] = $value;
		}

		$this->set_external( $ext );
	}

	public function fetch() {
		return $this->term;
	}

	public function get_taxonomy() {
		return isset($this->term['taxonomy']) ? $this->term['taxonomy'] : '';
	}

	static function get_external_key() {
		return apply_filters( 'AttributeTerm::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
	}

	function get_external() {
		return $this->ext;
	}

	function get_raw_external() {
		$ext = $this->get_external();
		$taxonomy = $this->get_taxonomy();

		return preg_replace("#^({$taxonomy}/)#", '', $ext);
	}

	function set_external( $ext ) {
		if( $ext ) {
			$taxonomy = $this->get_taxonomy();

			if( $taxonomy && 0 !== strpos($ext, $taxonomy) ) {
				$ext = $taxonomy . '/' . $ext;
			}

			$this->ext = $ext;
		}
	}
}
