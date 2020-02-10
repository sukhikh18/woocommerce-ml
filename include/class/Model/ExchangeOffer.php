<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Register;

/**
 * Works with posts, postmeta
 */
class ExchangeOffer extends ExchangePost {
	// use ExchangeItemMeta;

	// private $post;

	// function __construct( Array $post, $ext = '', $meta = array() )
	// {
	//     $args = wp_parse_args( $post, array(
	//         'post_status'       => apply_filters('ExchangePost__post_status', 'publish'),
	//         'comment_status'    => apply_filters('ExchangePost__comment_status', 'open'),
	//         /**
	//          * @todo watch this!
	//          */
	//         'post_type'         => 'offer',
	//     ) );

	//     if( $ext ) $args['post_mime_type'] = $ext;
	//     if( 0 !== strpos($args['post_mime_type'], 'XML') ) $args['post_mime_type'] = 'XML/' . $args['post_mime_type'];

	//     if( !$args['post_name'] ) {
	//         $args['post_name'] = strtolower($args['post_name']);
	//     }

	//     /**
	//      * @todo generate guid
	//      */

	//     $this->post = new WP_Post( (object) $args );
	//     $this->setMeta($meta);
	// }

	function set_quantity( $qty ) {
		if ( null !== $qty ) {
			$qty = floatval( $qty );

			$this->set_meta( '_manage_stock', 'yes' );
			$this->set_meta( '_stock_status', $qty ? 'instock' : 'outofstock' );
			$this->set_meta( '_stock', $qty );
		}

		return $this;
	}

	/**
	 * Only one price coast for simple
	 *
	 * @param \CommerceMLParser\ORM\Collection $prices
	 *
	 * @return int
	 */
	public function get_current_price( $prices ) {
		$price = 0;
		if ( ! $prices->isEmpty() ) {
			$price = $prices->current()->getPrice();
		}

		return $price;
	}

	function set_price( $price ) {
		$this->set_meta( '_price', $price );
		$this->set_meta( '_regular_price', $price );

		return $this;
	}

	function merge( $offer ) {
		// Merge this.
		$this->set_quantity( $this->get_quantity() + $offer->get_quantity() );
		$this->set_price( max( $this->get_price(), $offer->get_price() ) );
		// @todo merge warehouses quantity.
	}

	public function write_temporary_data() {
		global $wpdb;

		$table  = $wpdb->get_blog_prefix() . EXCHANGE_TMP_TABLENAME;
		$update = array();

		if ( $price = $this->get_price() ) {
			$update['price'] = $price;
		}

		if ( $qty = $this->get_quantity() ) {
			$update['qty'] = $qty;
		}

		$this->warehouses->walk( function ( $term ) use ( &$update ) {
			$update['warehouses'][ $term->get_raw_external() ] = $term->get_id();
		} );

		if( isset($update['warehouses']) ) {
			$update['warehouses'] = serialize( $update['warehouses'] );
		}

		return $wpdb->update( $table, $update, array(
			'code' => $this->get_raw_external(),
		) );
	}
}