<?php

namespace NikolayS93\Exchange\Model;


use CommerceMLParser\Model\Property;
use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\esc_cyr;

/**
 * Works with posts, term_relationships, postmeta
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
			'_price'         => 0,
			'_regular_price' => 0,
			'_manage_stock'  => 'no',
			'_stock_status'  => 'outofstock',
			'_stock'         => 0,
		) );

		/**
		 * @todo generate guid
		 */
		$this->set_meta( $meta );

		$this->warehouses = new Collection();
	}

	function prepare( $mode = '' ) {
	}

	function fetch() {
		return array(
			'posts'    => $this->post,
			'postmeta' => $this->get_meta(),
		);
	}

	static function get_structure( $key ) {
		$structure = array(
			'posts'    => array(
				'ID'                    => '%d',
				'post_author'           => '%d',
				'post_date'             => '%s',
				'post_date_gmt'         => '%s',
				'post_content'          => '%s',
				'post_title'            => '%s',
				'post_excerpt'          => '%s',
				'post_status'           => '%s',
				'comment_status'        => '%s',
				'ping_status'           => '%s',
				'post_password'         => '%s',
				'post_name'             => '%s',
				'to_ping'               => '%s',
				'pinged'                => '%s',
				'post_modified'         => '%s',
				'post_modified_gmt'     => '%s',
				'post_content_filtered' => '%s',
				'post_parent'           => '%d',
				'guid'                  => '%s',
				'menu_order'            => '%d',
				'post_type'             => '%s',
				'post_mime_type'        => '%s',
				'comment_count'         => '%d',
			),
			'postmeta' => array(
				'meta_id'    => '%d',
				'post_id'    => '%d',
				'meta_key'   => '%s',
				'meta_value' => '%s',
			)
		);

		if ( isset( $structure[ $key ] ) ) {
			return $structure[ $key ];
		}

		return false;
	}

	function add_warehouse( Warehouse $ExchangeTerm ) {
		return $this->warehouses->add( $ExchangeTerm );
	}

	function get_warehouse( $CollectionItemKey = '' ) {
		$warehouse = $CollectionItemKey ?
			$this->warehouses->offsetGet( $CollectionItemKey ) :
			$this->warehouses->first();

		return $warehouse;
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

	/**
	 * @deprecated
	 */
	public static function update_all( $posts_id ) {
		global $wpdb;

		$date_now   = current_time( 'mysql' );
		$gmdate_now = current_time( 'mysql', 1 );

		$wpdb->query( "UPDATE $wpdb->posts
            SET `post_status` = 'publish',
            	`post_modified` = '$date_now',
				`post_modified_gmt` = '$gmdate_now'
            WHERE ID in (" . implode( ",", $posts_id ) . ")" );
	}

	public function update() {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			// set
			array(
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 )
			),
			// where
			array(
				'post_mime_type' => $this->get_external(),
			)
		);
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

	public function create() {
		$res   = $this->fetch();
		$post  = $res['posts'];
		$_post = $post->to_array();

		// Is date null set now
		// if ( ! (int) preg_replace( '/[^0-9]/', '', $post->post_date ) ) {
		// 	unset( $_post['post_date'] );
		// }

		// if ( ! (int) preg_replace( '/[^0-9]/', '', $post->post_date_gmt ) ) {
		// 	unset( $_post['post_date_gmt'] );
		// }

		return wp_insert_post( $_post );
	}

	/**
	 * [fillExistsProductData description]
	 *
	 * @param array  &$products products or offers collections
	 * @param boolean $orphaned_only [description]
	 *
	 * @return [type]                 [description]
	 */
	static public function fillExistsFromDB( &$products, $orphaned_only = false ) {
		// $startExchange = get_option( 'exchange_start-date', '' );
		// $intStartExchange = strtotime($startExchange);

		/** @global \wpdb wordpress database object */
		global $wpdb;

		$Plugin = Plugin::get_instance();

		/** @var array List of external code items list in database attribute context (%s='%s') */
		$post_mime_types = array();

		/** @var array list of objects exists from posts db */
		$exists = array();

		/**
		 * EXPLODE FOR SIMPLE ONLY
		 * @todo
		 */
		foreach ( $products as $rawExternalCode => $product ) {
			if ( ! $orphaned_only || ( $orphaned_only && ! $product->get_id() ) ) {
				list( $product_ext ) = explode( '#', $product->get_external() );
				$post_mime_types[] = "`post_mime_type` = '" . esc_sql( $product_ext ) . "'";
			}
		}

		if ( $post_mime_type = implode( " \t\n OR ", $post_mime_types ) ) {
			// ID, post_author, post_date, post_title, post_content, post_excerpt, post_date_gmt, post_name, post_mime_type - required
			$exists = $wpdb->get_results( "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = 'product'
                AND (\n\t\n $post_mime_type \n)" );
		}

		foreach ( $exists as $exist ) {
			/** @var string $mime post_mime_type without XML/ */
			if ( ( $mime = substr( $exist->post_mime_type, 4 ) ) && isset( $products[ $mime ]->post ) ) {

				/** Skip if selected (unset new data field from array (@care)) */
				// if( $post_name = Plugin::get('post_name') )         unset( $exist->post_name );
				if ( ! $Plugin->get_setting( 'skip_post_author' ) ) {
					unset( $exist->post_author );
				}
				if ( ! $Plugin->get_setting( 'skip_post_title' ) ) {
					unset( $exist->post_title );
				}
				if ( ! $Plugin->get_setting( 'skip_post_content' ) ) {
					unset( $exist->post_content );
				}
				if ( ! $Plugin->get_setting( 'skip_post_excerpt' ) ) {
					unset( $exist->post_excerpt );
				}

				foreach ( get_object_vars( $exist ) as $key => $value ) {
					$products[ $mime ]->post->$key = $value;
				}
			}
		}
	}

	function getAllRelativeExternals( $orphaned_only = false ) {
		$arExternals = array();

		if ( ! empty( $this->product_cat ) ) {
			/** @var Category $product_cat */
			foreach ( $this->product_cat as $product_cat ) {
				if ( $orphaned_only && $product_cat->get_id() ) {
					continue;
				}
				$arExternals[] = $product_cat->get_external();
			}
		}

		if ( ! empty( $this->warehouses ) ) {
			/** @var Warehouse $warehouse */
			foreach ( $this->warehouses as $warehouse ) {
				if ( $orphaned_only && $warehouse->get_id() ) {
					continue;
				}
				$arExternals[] = $warehouse->get_external();
			}
		}

		if ( ! empty( $this->developer ) ) {
			/** @var Developer $developer */
			foreach ( $this->developer as $developer ) {
				if ( $orphaned_only && $developer->get_id() ) {
					continue;
				}
				$arExternals[] = $developer->get_external();
			}
		}

		if ( ! empty( $this->properties ) ) {
			/** @var Attribute $property */
			foreach ( $this->properties as $property ) {
				foreach ( $property->get_values() as $ex_term ) {
					if ( $orphaned_only && $ex_term->get_id() ) {
						continue;
					}

					$arExternals[] = $ex_term->get_external();
				}
			}
		}

		return $arExternals;
	}

	function fillExistsRelativesFromDB() {
		/** @global \wpdb $wpdb built in wordpress db object */
		global $wpdb;

		$arExternals = $this->getAllRelativeExternals( true );

		if ( ! empty( $arExternals ) ) {
			foreach ( $arExternals as $strExternal ) {
				$arSqlExternals[] = "`meta_value` = '{$strExternal}'";
			}

			$arTerms = array();

			$exsists_terms_query = "
                SELECT term_id, meta_key, meta_value
                FROM {$wpdb->prefix}term_meta
                WHERE meta_key = '" . Category::get_external_key() . "'
                    AND (" . implode( " \t\n OR ", array_unique( $arSqlExternals ) ) . ")";

			$ardbTerms = $wpdb->get_results( $exsists_terms_query );
			foreach ( $ardbTerms as $ardbTerm ) {
				$arTerms[ $ardbTerm->meta_value ] = $ardbTerm->term_id;
			}

			if ( ! empty( $this->product_cat ) ) {
				/** @var Category $product_cat */
				foreach ( $this->product_cat as &$product_cat ) {
					$ext = $product_cat->get_external();
					if ( ! empty( $arTerms[ $ext ] ) ) {
						$product_cat->set_id( $arTerms[ $ext ] );
					}
				}
			}

			if ( ! empty( $this->warehouses ) ) {
				/** @var Warehouse $warehouse */
				foreach ( $this->warehouses as &$warehouse ) {
					$ext = $warehouse->get_external();
					if ( ! empty( $arTerms[ $ext ] ) ) {
						$warehouse->set_id( $arTerms[ $ext ] );
					}
				}
			}

			if ( ! empty( $this->developer ) ) {
				/** @var Developer $developer */
				foreach ( $this->developer as &$developer ) {
					$ext = $developer->get_external();
					if ( ! empty( $arTerms[ $ext ] ) ) {
						$developer->set_id( $arTerms[ $ext ] );
					}
				}
			}

			if ( ! empty( $this->properties ) ) {
				/** @var Attribute $property */
				foreach ( $this->properties as &$property ) {
					if ( $property instanceof Attribute ) {
						foreach ( $property->get_values() as &$term ) {
							$ext = $term->get_external();
							if ( ! empty( $arTerms[ $ext ] ) ) {
								$term->set_id( $arTerms[ $ext ] );
							}
						}
					} else {
						Error::set_message( 'property: ' . print_r( $property, 1 ) . ' not has attribute instance.' );
					}
				}
			}
		}
	}

	function get_product_meta() {
		$meta = $this->get_meta();

		unset( $meta['_price'], $meta['_regular_price'], $meta['_manage_stock'], $meta['_stock_status'], $meta['_stock'] );

		return $meta;
	}

	function get_id() {
		return $this->post->ID;
	}

	function set_id( $value ) {
		$this->post->ID = intval( $value );
	}

	public function get_slug() {
		return $this->post->post_name;
	}

	public function set_slug( $slug ) {
		$this->post->post_name = sanitize_title( esc_cyr( $slug, false ) );
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
	}
}
