<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Register;

/**
 * Works with posts, postmeta
 */
class Offer extends Post {
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
	function fill_relative_post() {
		global $wpdb;

		$table = Register::get_exchange_table_name();
		$ext   = $this->get_external();

		$res = $wpdb->get_row( "
			SELECT * FROM $table
			WHERE `xml` = '$ext'
			LIMIT 1
		" );

		if( !empty($res) ) {
			if ( ! empty( $res->name ) ) {
				$this->set_title( $res->name );
			}

//			if ( ! empty( $res->desc ) ) {
//				$this->set_title( $res->name );
//			}

			$meta = unserialize( $res->meta_list );
			$this->set_meta( $meta );
			// reset status
			$this->set_quantity( $this->get_quantity() );
		}
	}

	/**
	 * Only one price coast for simple
	 *
	 * @param \CommerceMLParser\ORM\Collection $prices
	 *
	 * @return int
	 */
	public static function get_current_price( $prices ) {
		$price = 0;
		if ( ! $prices->isEmpty() ) {
			$price = $prices->current()->getPrice();
		}

		return $price;
	}

	function get_price() {
		return floatval( $this->get_meta( 'price', 0 ) );
	}

	function get_regular_price() {
		return floatval( $this->get_meta( 'regular_price', 0 ) );
	}

	function set_price( $new_price ) {
		$new_price = floatval( $new_price );

		$this->set_meta( '_price', max( $new_price, $this->get_price() ) );
		$this->set_meta( '_regular_price', max( $new_price, $this->get_regular_price() ) );

		return $this;
	}

	function get_quantity() {
		$stock = floatval( $this->get_meta( 'stock', 0 ) );

		return $stock;
	}

	function set_quantity( $qty ) {
		$status = $this->get_meta( 'stock_status', 'outofstock' );
		$stock = $this->get_quantity();

		$stock+= floatval( $qty );

		if( $stock ) {
			$status = 'instock';
		}

		$this->set_meta( '_manage_stock', 'yes' );
		$this->set_meta( '_stock_status', $status );
		$this->set_meta( '_stock', $stock );

		return $this;
	}
}