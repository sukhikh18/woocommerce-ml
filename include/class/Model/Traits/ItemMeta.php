<?php

namespace NikolayS93\Exchange\Model\Traits;

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
		if ( ! $key || !is_array($key) && !$value ) {
			return;
		}

		if ( !is_array( $key ) ) {
			$this->meta[ (string) $key ] = trim($value);
		} else {
			foreach ( $key as $meta_key => $meta_value ) {
				$this->set_meta($meta_key, $meta_value);
			}
		}
	}

	function del_meta( $key ) {
		unset( $this->meta[ $key ] );
	}
}