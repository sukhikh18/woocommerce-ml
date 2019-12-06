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
 */
class Post implements Identifiable, ExternalCode {

	use ItemMeta;

	/**
	 * @var \WP_Post
	 * @sql FROM $wpdb->posts
	 *      WHERE ID = %d
	 */
	private $post;

	/**
	 * @var Collection;
	 */
	public $relationships;

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
//		$meta = wp_parse_args( $meta, array(
//			'_price'         => 0,
//			'_regular_price' => 0,
//			'_manage_stock'  => 'no',
//			'_stock_status'  => 'outofstock',
//			'_stock'         => 0,
//		) );

		/**
		 * @todo generate guid
		 */
		$this->set_meta( $meta );

		$this->relationships = new Collection();
	}

	public function get_relationship( $CollectionItemKey = '' ) {
		$relationship = $CollectionItemKey ?
			$this->relationships->offsetGet( $CollectionItemKey ) :
			$this->relationships->first();

		return $relationship;
	}

	public function add_relationship( $new_relationship ) {
		foreach ( $this->relationships as $i => $relationship ) {
			if ( $relationship->term_source === $new_relationship->term_source ) {
				$this->relationships[ $i ] = $new_relationship;

				return $this;
			}
		}

		$this->relationships->add( $new_relationship );

		return $this;
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

	/****************************************************** CRUD ******************************************************/
	function fetch( $key = null ) {
		$data = array(
			'post'     => array(
				'post_author'    => $this->post->post_author,
				'post_content'   => $this->post->post_content,
				'post_title'     => $this->post->post_title,
				'post_excerpt'   => $this->post->post_excerpt,
				'post_status'    => $this->post->post_status,
				'post_name'      => $this->post->post_name,
				'post_type'      => $this->post->post_type,
				'post_mime_type' => $this->post->post_mime_type,
			),
			'postmeta' => $this->get_meta(),
		);

		if ( null === $key || ( $key && ! isset( $data[ $key ] ) ) ) {
			return $data;
		}

		return $data[ $key ];
	}

	public function update() {
		// global $wpdb;

		// @todo add title/content..
		// $wpdb->update(
		// 	$wpdb->posts,
		// 	// set
		// 	array(
		// 		'post_status'       => 'publish',
		// 		'post_modified'     => current_time( 'mysql' ),
		// 		'post_modified_gmt' => current_time( 'mysql', 1 )
		// 	),
		// 	// where
		// 	array(
		// 		'post_mime_type' => $this->get_external(),
		// 	)
		// );

		if ( $_post = $this->fetch( 'post' ) ) {
			return wp_update_post( $_post );
		}

		return 0;
	}

	public function deactivate() {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			// set
			array( 'post_status' => 'draft' ),
			// where
			array(
				'post_mime_type' => $this->get_external(),
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * @return Int insert post ID or 0
	 */
	public function create() {
		if ( $_post = $this->fetch( 'post' ) ) {
			return wp_insert_post( $_post );
		}

		return 0;
	}

	public function write_temporary_data() {
		global $wpdb;

		$table = Register::get_exchange_table_name();

		$product_id         = $this->get_id() ? intval( $this->get_id() ) : 0;
		$xml                = $this->get_external();
		$name               = $this->get_title();
		$desc               = $this->get_content();
		$meta_list          = serialize( $this->get_meta() );
		$relationships_list = array();

		/**
		 * @param Term $term
		 */
		$term_list_pluck = function ( $term ) use ( &$relationships_list ) {
			$relationships_list[ $term->get_id() ] = $term->get_external();
		};

		// @todo set tax relative
		$this->categories->walk( $term_list_pluck );
		// $this->attributes->walk( $term_list_pluck );
		$this->warehouses->walk( $term_list_pluck );

		$relationships_list = serialize( $relationships_list );

		$columns = array( 'product_id', 'xml', 'name', 'desc', 'meta_list', 'relationships_list' );
		// $values_args  = implode( ', ', array_map( function ( $value ) {
		// 	return "'$value'";
		// }, $values ) );
		// $columns_args = implode( ", \r\n", array_map( function ( $column ) {
		// 	return "`$column` = VALUES(`$column`)";
		// }, $columns ) );

		// $wpdb->query( "INSERT INTO $table VALUES ($values_args) ON DUPLICATE KEY UPDATE $columns_args;" );
		$q = $wpdb->insert( $table, compact( $columns ) );
	}
}
