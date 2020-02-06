<?php

namespace NikolayS93\Exchanger;


use NikolayS93\Exchanger\ORM\CollectionPosts;
use NikolayS93\Exchanger\ORM\CollectionTerms;
use NikolayS93\Exchanger\ORM\Collection;
use NikolayS93\Exchanger\Model\Interfaces\Term;
use NikolayS93\Exchanger\Model\Category;
use NikolayS93\Exchanger\Model\Developer;
use NikolayS93\Exchanger\Model\Attribute;
use NikolayS93\Exchanger\Model\Warehouse;
use NikolayS93\Exchanger\Model\ExchangePost;
use NikolayS93\Exchanger\Model\ExchangeProduct;
use NikolayS93\Exchanger\Model\ExchangeOffer;


class Update {

	public $offset;

	/** @var $progress int Offset from */
	public $progress;

	public $results;

	public $status = 'success';

	function __construct() {

		$this->offset = apply_filters( PLUGIN::PREFIX . 'update_count_offset', array(
			'product' => 500,
			'offer'   => 500,
			'rest'    => 1001,
			'price'   => 1001,
		) );

		$this->progress = intval( Plugin()->get_setting( 'progress', 0, 'status' ) );

		$this->results = array(
			'create'        => 0,
			'update'        => 0,
			'meta'          => 0,
			'relationships' => 0,
		);
	}

	function get_status() {
		return $this->status;
	}

	function get_progress() {
		return $this->progress;
	}

	function set_status( $status ) {
		$this->status = $status;

		return $this;
	}

	function set_progress( $progress ) {
		$this->progress = $progress;

		return $this;
	}

	function reset_progress() {
		$this->set_progress( 0 );

		return $this;
	}

	public function update_meta( $post_id, $property_key, $property ) {
		if ( update_post_meta( $post_id, $property_key, $property ) ) {
			$this->results['meta'] ++;
		}
	}

	/**
	 * @param CollectionPosts $products
	 */
	public function update_products( $products ) {
		// Count products will be updated
		$this->progress += $products->count();

		if ( 'off' === $post_mode = Plugin()->get_setting( 'post_mode' ) ) {
			return $this;
		}

//		$products->walk( array($this, 'update_products_step') );
		$products->walk( function ( $product ) {
			$product->write_temporary_data();
		} );

		return $this;
	}

	/**
	 * @param ExchangePost $product
	 */
	public function update_products_step( $product ) {
		/** @var string $post_mode use get_option (has cache) */
		$post_mode = Plugin()->get_setting( 'post_mode' );
		if ( $product->prepare( $post_mode ) ) {
			if ( ! $product->get_id() ) {
				// if is create only
				if ( 'update' !== $post_mode && $post_id = $product->create() ) {
					// define ID for use on post meta update
					$product->set_id( $post_id );
					$this->results['create'] ++;
				}
			} else {
				// if is update only
				if ( 'create' !== $post_mode && $product->update() ) {
					$this->results['update'] ++;
				}
			}
		}
	}

	/**
	 * @param CollectionPosts $products
	 */
	public function update_products_meta( $products ) {
		return $this;

		/**
		 * Update post meta
		 */
		$skip_post_meta = array(
			'value' => Plugin()->get_setting( 'skip_post_meta_value', false ),
			'sku'   => Plugin()->get_setting( 'skip_post_meta_sku', false ),
			'unit'  => Plugin()->get_setting( 'skip_post_meta_unit', false ),
			'tax'   => Plugin()->get_setting( 'skip_post_meta_tax', false ),
		);

		/**
		 * @param ExchangeProduct $product
		 */
		$update_products_meta = function ( $product ) use ( $skip_post_meta ) {
			if ( $post_id = $product->get_id() ) {
				$is_new = $product->is_new();

				if ( ! $skip_post_meta['value'] || $is_new ) {
					// Get list of all meta by product ["_sku"], ["_unit"], ["_tax"], ["{$custom}"]
					$product_meta = $product->get_product_meta();

					array_map( function ( $k, $v ) use ( $post_id, $is_new, $skip_post_meta ) {
						$value = is_array( $v ) ? array_filter( $v, 'trim' ) : trim( $v );

						switch ( true ) {
							case '_sku' == $k && $skip_post_meta['sku']:
							case '_unit' == $k && $skip_post_meta['unit']:
							case '_tax' == $k && $skip_post_meta['tax']:
								return $value;
								break;

							default:
								$this->update_meta( $post_id, $k, $value );

								return $value;
								break;
						}

					}, array_keys( $product_meta ), $product_meta );
				}
			}
		};

		$products->walk( $update_products_meta );

		return $this;
	}

	/**
	 * @param CollectionPosts $offers
	 */
	public function update_offers( $offers ) {
		// Count offers will be updated ($newOffersCount != $offersCount)
		$this->progress += $offers->count();
		/**
		 * @param ExchangePost $product
		 */
		$update_offers = function ( $offer ) {
		};

		$offers->walk( $update_offers );

		return $this;
	}

	/**
	 * @param CollectionPosts $offers
	 */
	public function update_offers_meta( $offers ) {
		$skip_offer_meta = array(
			'offer_price'  => Plugin()->get_setting( 'offer_price', false ),
			'offer_qty'    => Plugin()->get_setting( 'offer_qty', false ),
			'offer_weight' => Plugin()->get_setting( 'offer_weight', false ),
		);

		/** @var ExchangeOffer $offer */
		$update_offers_meta = function ( $offer ) use ( $skip_offer_meta ) {
			if ( ! $post_id = $offer->get_id() ) {
				return;
			}

			if ( $unit = $offer->get_meta( 'unit' ) ) {
				$this->update_meta( $post_id, '_unit', $unit );
			}

			if ( 'off' !== $skip_offer_meta['offer_price'] ) {
				if ( $price = $offer->get_meta( 'price' ) ) {
					$this->update_meta( $post_id, '_regular_price', $price );
					$this->update_meta( $post_id, '_price', $price );
				}
			}

			if ( 'off' !== $skip_offer_meta['offer_qty'] ) {
				$qty = $offer->get_quantity();

				$this->update_meta( $post_id, '_manage_stock', 'yes' );
				$this->update_meta( $post_id, '_stock_status', 0 < $qty ? 'instock' : 'outofstock' );
				$this->update_meta( $post_id, '_stock', $qty );

				if ( $stock_wh = $offer->get_meta( 'stock_wh' ) ) {
					$this->update_meta( $post_id, '_stock_wh', $stock_wh );
				}
			}

			if ( 'off' !== Plugin()->get_setting( 'offer_weight',
					false ) && $weight = $offer->get_meta( 'weight' ) ) {
				$this->update_meta( $post_id, '_weight', $weight );
			}

			// Типы цен
			// if( $offer->prices ) {}
		};

		$offers->walk( $update_offers_meta );

		return $this;
	}

	/**
	 * @param Collection $termsCollection
	 */
	public function terms( $termsCollection ) {
		/** @var \NikolayS93\Exchanger\Model\Abstracts\Term $term */
		$closure = function ( $term, $offset ) {
			if ( $term->prepare() ) {
				$this->results['update'] += (int) $term->update();
			}
		};

		$termsCollection->walk( $closure );

		return $this;
	}

	public function term_meta( $terms ) {
		global $wpdb;


		/** @var Model\Abstracts\Term $term */
		foreach ( $terms as $term ) {
			$term_id = $term->get_id();
			if ( ! $term_id ) {
				continue;
			}

			update_term_meta( $term_id, $term->get_external_key(), $term->get_external() );
			$this->results['meta'] ++;
		}

		return $this;
	}

	// @todo delete taxonomy meta when delete taxonomy
	public function properties( $attributes ) {
		global $wpdb;

		foreach ( $attributes as $attribute ) {
			if ( 'select' !== $attribute->get_type() ) {
				continue;
			}

			$attribute_id = $attribute->get_id();
			$attribute_slug = $attribute->get_slug();

			if( ! $attribute_id && ! taxonomy_exists( $attribute_slug ) ) {
				$result = wc_create_attribute( $attribute->fetch() );

				if ( is_wp_error( $result ) ) {
					Error()
						->add_message( $result, "Warning", true )
						->add_message( $attribute, "Target", true );

					continue;
				}

				$external = $attribute->get_external();
				$attribute_id = intval( $result );

				if( $attribute_id > 0 ) {
					if ( $external ) {
						$attribute->set_id( $attribute_id );
						// Insert attribute ID map.
						$insert = $wpdb->insert(
							$wpdb->prefix . 'woocommerce_attribute_taxonomymeta',
							array(
								'meta_id'    => null,
								'tax_id'     => $attribute_id,
								'meta_key'   => EXCHANGE_EXTERNAL_CODE_KEY,
								'meta_value' => $external,
							),
							array( '%s', '%d', '%s', '%s' )
						);
					} else {
						Error()
							->add_message( "Empty attr insert or attr external", "Warning", true )
							->add_message( $attribute, "Target", true );
					}

					register_taxonomy( $attribute_slug, 'product' );
				}
			}
		}

		return $this;
	}

	/**
	 * @param CollectionPosts $posts
	 * @param array $args
	 */
	public function relationships( CollectionPosts $posts, $args = array() ) {
		/**
		 * @param ExchangeProduct $post
		 */
		$update_relationships = function ( $post ) {
			if ( $post_id = $post->get_id() ) {
				if ( method_exists( $post, 'update_object_terms' ) ) {
					$this->results['relationships'] += $post->update_object_terms();
				}

				if ( method_exists( $post, 'update_attributes' ) ) {
					$post->update_attributes();
				}
			}
		};

		$posts->walk( $update_relationships );

		$this->progress += $posts->count();

		return $this;
	}

	public static function update_term_counts() {
		global $wpdb;

		/**
		 * update taxonomy term counts
		 */
		$wpdb->query( "
            UPDATE {$wpdb->term_taxonomy} SET count = (
            SELECT COUNT(*) FROM {$wpdb->term_relationships} rel
            LEFT JOIN {$wpdb->posts} po
                ON (po.ID = rel.object_id)
            WHERE
                rel.term_taxonomy_id = {$wpdb->term_taxonomy}.term_taxonomy_id
            AND
                {$wpdb->term_taxonomy}.taxonomy NOT IN ('link_category')
            AND
                po.post_status IN ('publish', 'future')
        )" );

		delete_transient( 'wc_term_counts' );
	}
}
