<?php

namespace NikolayS93\Exchanger\Model\Traits;

use NikolayS93\Exchanger\Error;

trait ItemMeta {
	private $meta = array();

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	function get_meta( $key = '' ) {
		if ( $key ) {

			if ( isset( $this->meta[ '_' . $key ] ) ) {
				return $this->meta[ '_' . $key ];
			}

			if ( isset( $this->meta[ $key ] ) ) {
				return $this->meta[ $key ];
			}

			return null;
		}

		return (array) $this->meta;
	}

	function set_meta( $key, $value = '' ) {
		if ( is_array( $key ) ) {
			foreach ( $key as $meta_key => $meta_value ) {
				$this->set_meta( $meta_key, $meta_value );
			}
		} else {
			if ( $key && $value ) {
				$this->meta[ trim( $key ) ] = is_array( $value ) ? array_filter( $value, 'trim' ) : trim( $value );
			}
		}
	}

	function del_meta( $key ) {
		unset( $this->meta[ $key ] );
	}
}