<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Interfaces\Term;
use NikolayS93\Exchange\Model\Abstracts\Term as ATerm;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use NikolayS93\Exchange\Plugin;

/**
 * Works with terms, term_taxonomy, term_relationships, term-meta
 */
class Category extends ATerm implements Term, ExternalCode, Identifiable, HasParent {
	/** @var int for easy external meta update */
	public $meta_id;
	/** @var string parent external code */
	private $parent_ext;

	function get_parent_external() {
		return $this->parent_ext;
	}

	function set_parent_external( $ext ) {
		return $this->parent_ext = (string) $ext;
	}

	public function get_parent_id() {
		return isset( $this->term_taxonomy['parent'] ) ? (int) $this->term_taxonomy['parent'] : 0;
	}

	public function set_parent_id( $term_id ) {
		return $this->term_taxonomy['parent'] = (int) $term_id;
	}

	function get_taxonomy_name() {
		return apply_filters(Plugin::PREFIX . 'Category::get_taxonomy_name', 'product_cat');
	}

	function prepare() {
		$Plugin = Plugin::get_instance();
		/** @var Int $term_id WP_Term->term_id */
		$term_id = $this->get_id();

		if ( $this->check_mode( $term_id, $Plugin->get_setting( 'category_mode' ) ) ) {
			// Do not update name?
			switch ( $Plugin->get_setting( 'cat_name' ) ) {
				case false:
					if ( $term_id ) {
						$this->set_name( '' );
					}
					break;
			}

			if( !$this->check_mode($term_id, $Plugin->get_setting( 'cat_desc' )) ) {
				$this->set_description( '' );
			}

			if( $this instanceof HasParent ) {
				if( !$this->check_mode($term_id, $Plugin->get_setting( 'skip_parent' )) ) {
					$this->set_parent_id( 0 );
				}
			}

			return true;
		}

		return false;
	}
}