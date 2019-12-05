<?php

namespace NikolayS93\Exchange\Model\Traits;

use NikolayS93\Exchange\Error;

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
				if( is_string( $value ) ) {
					$value = trim( $value );
				}

				$this->meta[ trim( $key ) ] = $value;
				// $this->meta[ trim( $key ) ] = is_array( $value ) ? array_filter( $value, 'trim' ) : trim( $value );
			}
		}
	}

	function delete_meta( $key ) {
		unset( $this->meta[ $key ] );
	}
}
