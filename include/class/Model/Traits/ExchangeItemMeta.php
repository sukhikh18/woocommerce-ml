<?php

namespace NikolayS93\Exchange;

trait ExchangeItemMeta {
	private $meta = array();

	function getMeta( $key = '' ) {
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

	function setMeta( $key, $value = '' ) {
		if ( ! $key ) {
			return;
		}

		if ( is_array( $key ) ) {
			foreach ( $key as $metakey => $metavalue ) {
				$this->meta[ $metakey ] = $metavalue;
			}
		} else {
			$this->meta[ $key ] = $value;
		}
	}

	function delMeta( $key ) {
		unset( $this->meta[ $key ] );
	}
}