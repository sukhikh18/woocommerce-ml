<?php

namespace NikolayS93\Exchange\ORM;

use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\ExchangePost;
use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Warehouse;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\Error;

/**
 * Class CollectionPosts
 */
class CollectionPosts extends Collection {
	/**
	 * @param bool $orphaned_only
	 *
	 * @return $this
	 */
	public function fill_exists( $orphaned_only = true ) {
		/** @global \wpdb wordpress database object */
		global $wpdb;

		$Plugin   = Plugin::get_instance();
		$settings = array(
//            'post_name' =>
			'skip_post_author'  => $Plugin->get_setting( 'skip_post_author' ),
			'skip_post_title'   => $Plugin->get_setting( 'skip_post_title' ),
			'skip_post_content' => $Plugin->get_setting( 'skip_post_content' ),
			'skip_post_excerpt' => $Plugin->get_setting( 'skip_post_excerpt' ),
		);

		/** @var array List of external code items list in database attribute context (%s='%s') */
		$post_mime_types = array();

		/**
		 * EXPLODE FOR SIMPLE ONLY @param ExchangePost $post
		 * @todo repair this
		 */
		$build_query = function ( $post ) use ( &$post_mime_types, $orphaned_only ) {
			if ( ! $orphaned_only || ( $orphaned_only && ! $post->get_id() ) ) {
				list( $ext ) = explode( '#', $post->get_external() );

				if ( $ext ) {
					$post_mime_types[] = sprintf( "`post_mime_type` = '%s'", esc_sql( $ext ) );
				}
			}
		};

		$this->walk( $build_query );

		$post_mime_types = array_unique( $post_mime_types );
		if ( empty( $post_mime_types ) ) {
			return $this;
		}

		$post_mime_types_args = implode( " \t\n OR ", $post_mime_types );
		$exists_query         = "
            SELECT * FROM {$wpdb->prefix}posts p
            WHERE `post_type` = 'product' AND ($post_mime_types_args)";
		$exists               = $wpdb->get_results( $exists_query );
		/** @var array $existsList compact post_mime_type => ID list */
		$existsList = wp_list_pluck( $exists, 'ID', 'post_mime_type' );

		/**
		 * @param ExchangePost $post
		 */
		$insert_data = function ( $post, $collectionOffset ) use ( $existsList ) {
			if ( isset( $existsList[ $post->get_external() ] ) ) {
				$post->set_id( $existsList[ $post->get_external() ] );
			} else {
				// $this->offsetUnset( $collectionOffset );
			}
		};

		$this->walk( $insert_data );

		array_walk( $exists, function ( $result ) use ( $exists, $settings ) {
			$external_from_db = $result->post_mime_type;
			/** @var ExchangePost $post */
			if ( $post = $this->offsetGet( $external_from_db ) ) {
				$post->set_id( $result->ID );

//                if ( $settings['skip_post_author'] ) {
//                    $post->set_author( $result->post_author );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_title( $result->post_title );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_content( $result->post_content );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_excerpt( $result->post_excerpt );
//                }
			}
		} );

		return $this;
	}

	public function fill_exists_terms( $Parser = null ) {
		/** @global \wpdb $wpdb built in wordpress db object */
		global $wpdb;

		$externals = array();

		/**
		 * @param ExchangeProduct $product
		 */
		$this->walk( function ( $product ) use ( &$externals ) {
			/**
			 * @param Term $cat
			 */
			$extract_external = function ( $cat ) use ( &$externals ) {
				$externals[] = $cat->get_external();
			};

			if ( isset( $product->categories ) ) {
				$product->categories->walk( $extract_external );
			}

			if ( isset( $product->warehouses ) ) {
				$product->warehouses->walk( $extract_external );
			}

			if ( isset( $product->attributes ) ) {
				$product->attributes->walk( $extract_external );
			}
		} );

		$externals = array_unique( $externals );

		if ( ! empty( $externals ) ) {
			array_walk( $externals, function ( &$external ) {
				$external = "`meta_value` = '{$external}'";
			} );

			$external_key = Category::get_external_key();
			$exists_terms = wp_list_pluck( $wpdb->get_results( "
                SELECT term_id, meta_value FROM {$wpdb->prefix}termmeta
                WHERE meta_key = '$external_key'
                    AND (" . implode( " \t\n OR ", $externals ) . ")" ),
				'term_id',
				'meta_value'
			);

			/**
			 * @param ExchangeProduct $product
			 */
			$this->walk( function ( $product ) use ( $exists_terms ) {
				/**
				 * @param Term $term
				 */
				$put_terms = function ( $term ) use ( $exists_terms ) {
					if ( isset( $exists_terms[ $term->get_external() ] ) ) {
						$term->set_id( $exists_terms[ $term->get_external() ] );
					}
				};

				if ( isset( $product->categories ) ) {
					$product->categories->walk( $put_terms );
				}

				if ( isset( $product->warehouses ) ) {
					$product->warehouses->walk( $put_terms );
				}

				if ( isset( $product->attributes ) ) {
					$product->attributes->walk( $put_terms );
				}
			} );
		}

		return $this;
	}
}
