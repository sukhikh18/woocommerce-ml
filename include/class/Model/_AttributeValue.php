<?php


namespace NikolayS93\Exchange\Model;


use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\check_mode;
use function NikolayS93\Exchange\error;

class AttributeValue implements ExternalCode { // extends Term

	public $id;
	public $code;
	public $name;
	public $value;
	public $taxonomy;

	public function __construct( $args, $code ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->set_external( $code );
	}

	function get_external() {
		return $this->code;
	}

	function get_raw_external() {
		$code_parts = explode( '/', $this->code, 2 );

		return end( $code_parts );
	}

	function set_external( $ext ) {
		if ( ! empty( $this->taxonomy ) ) {
			$ext = $this->taxonomy . '/' . $ext;
		}

		$this->code = $ext;
	}

	function get_id() {
		return $this->id;
	}

	function set_id( $id ) {
		$this->id = intval( $id );
	}

	function set_taxonomy( $tax ) {
		$this->taxonomy = $tax;
	}

	function get_taxonomy() {
		return $this->taxonomy;
	}

	function get_slug() {
		return sanitize_title( apply_filters( 'Term::set_slug', $this->name, $this ) );
	}

	function update() {
		$term_id  = $this->get_id();
		$taxonomy = $this->get_taxonomy();
		$term     = array(
			'term_id'          => $term_id,
			'term_taxonomy_id' => $term_id,
			'slug'             => $this->get_slug(),
		);

		if ( $term_id = $this->get_id() ) {
			$term['name'] = $this->name;
			$result       = wp_update_term( $term_id, $taxonomy, $term );
			$msg = 'update';
		} else {
			$result = wp_insert_term( $this->name, $taxonomy, $term );
			$msg = 'create';
		}

		if ( ! is_wp_error( $result ) ) {
			$this->set_id( $result['term_id'] );

			return $msg;
		} else {
			Error()
				->add_message( print_r( $result, 1 ), 'Warning', true )
				->add_message( print_r( $this, 1 ), 'Target', true );
		}

		return false;
	}

	public function get_taxonomy_name() {
		// TODO: Implement get_taxonomy_name() method.
		return '';
	}

	static function get_external_key() {
		return apply_filters( 'AttributeValue::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
	}


	function prepare() {
		return true;
//		$Plugin = Plugin::get_instance();
//		/** @var Int $term_id WP_Term->term_id */
//		$term_id = $this->get_id();
//
//		if ( check_mode( $term_id, $Plugin->get( 'attribute_mode' ) ) ) {
//			// Do not update name?
//			switch ( $Plugin->get( 'pa_name' ) ) {
//				case false:
//					if ( $term_id ) {
//						$this->unset_name();
//					}
//					break;
//			}
//
////			if ( ! check_mode( $term_id, $Plugin->get( 'pa_desc' ) ) ) {
////				$this->unset_description();
////			}
//
//			return true;
//		}
//
//		return false;
	}
}
