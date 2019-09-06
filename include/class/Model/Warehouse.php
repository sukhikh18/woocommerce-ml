<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Register;

class Warehouse extends Term {
	function get_taxonomy_name() {
		return Register::get_warehouse_taxonomy_slug();
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( $this->check_mode( $term_id, $Plugin->get_setting( 'warehouse_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get_setting( 'wh_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->set_name( '' );
					}
					break;
			}

			if( !$this->check_mode($term_id, $Plugin->get_setting( 'wh_desc' )) ) {
				$this->set_description( '' );
			}

			return true;
		}

		return false;
	}
}
