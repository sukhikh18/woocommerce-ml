<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\check_mode;

class AttributeValue extends Term {

	public function get_taxonomy_name() {
		// TODO: Implement get_taxonomy_name() method.
		return '';
	}

	static function get_external_key() {
		return apply_filters( 'AttributeValue::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( check_mode( $term_id, $Plugin->get_setting( 'attribute_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get_setting( 'pa_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->unset_name();
					}
					break;
			}

			if ( ! check_mode( $term_id, $Plugin->get_setting( 'pa_desc' ) ) ) {
				$this->unset_description();
			}

			return true;
		}

		return false;
	}
}
