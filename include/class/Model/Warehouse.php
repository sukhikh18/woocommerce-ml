<?php


namespace NikolayS93\Exchanger\Model;


use NikolayS93\Exchanger\Model\Abstracts\Term;
use NikolayS93\Exchanger\Plugin;
use NikolayS93\Exchanger\Register;
use function NikolayS93\Exchanger\check_mode;
use function NikolayS93\Exchanger\Error;

class Warehouse extends Term {
	function get_taxonomy_name() {
		return Register::get_warehouse_taxonomy_slug();
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( check_mode( $term_id, $Plugin->get_setting( 'warehouse_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get_setting( 'wh_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->unset_name();
					}
					break;
			}

			if ( ! check_mode( $term_id, $Plugin->get_setting( 'wh_desc' ) ) ) {
				$this->unset_description();
			}

			return true;
		}

		return false;
	}
}
