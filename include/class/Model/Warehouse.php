<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Register;
use function NikolayS93\Exchange\check_mode;
use function NikolayS93\Exchange\Error;

class Warehouse extends Term {
	function get_taxonomy_name() {
		return Register::get_warehouse_taxonomy_slug();
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( check_mode( $term_id, $Plugin->get( 'warehouse_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get( 'wh_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->unset_name();
					}
					break;
			}

			if ( ! check_mode( $term_id, $Plugin->get( 'wh_desc' ) ) ) {
				$this->unset_description();
			}

			return true;
		}

		return false;
	}
}
