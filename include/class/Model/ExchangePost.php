<?php

namespace NikolayS93\Exchange\Model;


use CommerceMLParser\Model\Property;
use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Register;
use function NikolayS93\Exchange\esc_cyr;

/**
 * Works with posts, term_relationships, post meta
 * Content: {
 *     Variables
 *     Utils
 *     Construct
 *     Relatives
 *     CRUD
 * }
 */
class ExchangePost implements Identifiable, ExternalCode {

	use ItemMeta;

	public $warehouses = array();

	/**
	 * @var \WP_Post
	 * @sql FROM $wpdb->posts
	 *      WHERE ID = %d
	 */
	private $post;

	function prepare( $mode = '' ) {
		return true;
	}

	function is_new() {
		$start_date = get_option( 'exchange_start-date', '' );

		if ( $start_date && strtotime( $start_date ) <= strtotime( $this->post->post_date ) ) {
			return true;
		}

		/**
		 * 2d secure ;D
		 */
		if ( empty( $this->post->post_modified ) || $this->post->post_date == $this->post->post_modified ) {
			return true;
		}

		return false;
	}

	function get_product_meta() {
		$meta = $this->get_meta();

		unset( $meta['_price'], $meta['_regular_price'], $meta['_manage_stock'], $meta['_stock_status'], $meta['_stock'] );

		return $meta;
	}

	/**
	 * ExchangePost constructor.
	 *
	 * @param array $post
	 * @param string $ext
	 * @param array $meta
	 */
	function __construct( Array $post, $ext = '', $meta = array() ) {
		$args = wp_parse_args( $post, array(
			'post_author'    => get_current_user_id(),
			'post_status'    => apply_filters( 'ExchangePost__post_status', 'publish' ),
			'comment_status' => apply_filters( 'ExchangePost__comment_status', 'closed' ),
			'post_type'      => 'product',
			'post_mime_type' => '',
		) );

		$this->post = new \WP_Post( (object) $args );
		$this->set_external( $ext ? $ext : $args['post_mime_type'] );

		if ( empty( $this->post->post_name ) ) {
			$this->set_slug( $this->post->post_title );
		}

		/**
		 * For no offer defaults
		 */
		$meta = wp_parse_args( $meta, array(
			'total_sales'        => 0,
			'_price'             => 0,
			'_regular_price'     => 0,
			'_manage_stock'      => 'yes',
			'_stock'             => 0,
			'_stock_status'      => 'outofstock',
			'_tax_status'        => 'taxable',
			'_tax_class'         => '',
			'_backorders'        => 'no',
			'_sold_individually' => 'no',
			'_virtual'           => 'no',
			'_downloadable'      => 'no',
			'_download_limit'    => - 1,
			'_download_expiry'   => '-1',
			'_wc_average_rating' => 0,
			'_product_version'   => '3.8.1',
		) );

		$meta['_regular_price'] = $meta['_price'];
		if ( $meta['_stock'] > 0 ) {
			$meta['_stock_status'] = 'instock';
		}

		/**
		 * @todo generate guid
		 */
		$this->set_meta( $meta );

		$this->warehouses = new Collection();
	}

	public static function get_external_key() {
		// product no has external meta, he save it in posts on mime_type as XML/external
		return false;
	}

	public function get_external() {
		return $this->post->post_mime_type;
	}

	public function get_raw_external() {
		$ext = $this->get_external();

		if ( 0 === stripos( $ext, 'XML/' ) ) {
			$ext = substr( $ext, 4 );
		}

		return $ext;
	}

	public function set_external( $ext ) {
		if ( 0 !== stripos( $ext, 'XML' ) ) {
			$ext = 'XML/' . $ext;
		}

		$this->post->post_mime_type = (String) $ext;

		return $this;
	}

	public function get_id() {
		return $this->post->ID;
	}

	public function set_id( $value ) {
		$this->post->ID = intval( $value );

		return $this;
	}

	public function get_slug() {
		return $this->post->post_name;
	}

	public function set_slug( $slug ) {
		$this->post->post_name = sanitize_title( esc_cyr( $slug, false ) );

		return $this;
	}

	public function get_author() {
		return $this->post->post_author;
	}

	public function set_author( $author ) {
		$this->post->post_author = $author;

		return $this;
	}

	public function get_title() {
		return $this->post->post_title;
	}

	public function set_title( $title ) {
		$this->post->post_title = $title;

		return $this;
	}

	public function get_content() {
		return $this->post->post_content;
	}

	public function set_content( $content ) {
		$this->post->post_content = $content;

		return $this;
	}

	public function get_excerpt() {
		return $this->post->post_excerpt;
	}

	public function set_excerpt( $excerpt ) {
		$this->post->post_excerpt = $excerpt;

		return $this;
	}

	/**************************************************** Relatives ***************************************************/

	public function get_warehouse( $CollectionItemKey = '' ) {
		$warehouse = $CollectionItemKey ?
			$this->warehouses->offsetGet( $CollectionItemKey ) :
			$this->warehouses->first();

		return $warehouse;
	}

	public function add_warehouse( Warehouse $ExchangeTerm ) {
		return $this->warehouses->add( $ExchangeTerm );
	}

	/****************************************************** CRUD ******************************************************/
	function fetch( $full = false ) {
		$postdata                      = $this->post->to_array();
		$postdata['post_modified']     = current_time( 'mysql' );
		$postdata['post_modified_gmt'] = current_time( 'mysql', 1 );

		if ( $full ) {
			$postdata['meta_input'] = $this->get_meta();

			if ( isset( $this->categories ) && ! $this->categories->isEmpty() ) {
				$postdata['tax_input']['product_cat'] = array();

				foreach ( $this->categories as $category ) {
					array_push( $postdata['tax_input']['product_cat'], intval( $category->get_id() ) );
				}
			}

			if ( isset( $this->warehouses ) && ! $this->warehouses->isEmpty() ) {
				$tax                           = Register::get_warehouse_taxonomy_slug();
				$postdata['tax_input'][ $tax ] = array();

				foreach ( $this->warehouses as $warehouse ) {
					array_push( $postdata['tax_input'][ $tax ], intval( $warehouse->get_id() ) );
				}
			}

			// @todo add atrributes
		} else {
			$price                                    = $this->get_price();
			$stock                                    = $this->get_quantity();
			$postdata['meta_input']['_price']         = $price;
			$postdata['meta_input']['_regular_price'] = $price;
			$postdata['meta_input']['_stock']         = $stock;
			$postdata['meta_input']['_stock_status']  = $stock ? 'instock' : 'outofstock';
		}

		return $postdata;
	}

	public function insert() {
		if ( $this->get_id() ) {
			$postdata = $this->fetch( true );
			unset( $postdata['post_date'], $postdata['post_date_gmt'] );
		} else {
			$postdata                  = $this->fetch( true );
			$postdata['post_date']     = $postdata['post_modified'];
			$postdata['post_date_gmt'] = $postdata['post_modified_gmt'];
		}

		return wp_insert_post( $postdata );
	}

	public function delete() {
		// global $wpdb;

		// $method = 'delete';

		// if( 'delete' == $method ) {
		// }
		// else {
		// 	wp_update_post(array(
		// 		'ID'    =>  $postid,
		// 		'post_status'   =>  'draft'
		// 	));
		// }

		// $wpdb->update(
		// 	$wpdb->posts,
		// 	// set
		// 	array( 'post_status' => 'draft' ),
		// 	// where
		// 	array(
		// 		'post_mime_type' => $this->get_external(),
		// 		'post_status'    => 'publish',
		// 	)
		// );
	}

	/**
	 * @return Int insert post ID or 0
	 */
	public function create() {
		return ( $_post = $this->fetch( 'post' ) ) ? wp_insert_post( $_post ) : 0;
	}

	function get_quantity() {
		return $this->get_meta( 'stock', 0 );
	}

	function get_price() {
		return $this->get_meta( 'price', 0 );
	}

	function get_tax() {
		return $this->get_meta( 'tax', 0 );
	}
}
