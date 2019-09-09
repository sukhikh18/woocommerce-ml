<?php

namespace NikolayS93\Exchange;

use CommerceMLParser\Model\Property;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Developer;
use NikolayS93\Exchange\Model\ExchangeOffer;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\Interfaces\Term;
use NikolayS93\Exchange\Model\Warehouse;
use NikolayS93\Exchange\ORM\Collection;


class Update {

	public $offset;

	/** @var $progress int Offset from */
	public $progress;

	static function get_sql_placeholder( $structure ) {
		$values = array();
		foreach ( $structure as $column => $format ) {
			$values[] = "'$format'";
		}
		$query = '(' . implode( ",", $values ) . ')';
		return $query;
	}
	static function get_sql_structure( $structure ) {
		return '(' . implode( ', ', array_keys( $structure ) ) . ')';
	}
	static function get_sql_duplicate( $structure ) {
		$values = array();
		foreach ( $structure as $column => $format ) {
			$values[] = "$column = VALUES($column)";
		}
		$query = implode( ",\n", $values ) . ';';
		return $query;
	}
	static function get_sql_update( $bd_name, $structure, $insert, $placeholders, $duplicate ) {
		global $wpdb;
		$query = "INSERT INTO $bd_name $structure VALUES " . implode( ', ', $placeholders );
		$query .= " ON DUPLICATE KEY UPDATE " . $duplicate;
		return $wpdb->prepare( $query, $insert );
	}

	function __construct() {
		$this->offset = apply_filters( PLUGIN::PREFIX . 'update_count_offset', array(
			'product' => 500,
			'offer'   => 500,
			'rest'    => 1001,
			'price'   => 1001,
		) );

		$this->progress = intval( Plugin()->get_setting( 'progress', 0, 'status' ) );
	}

	/**
	 * Set inner plug-in mode
	 * @note Set empty mode for reset
	 *
	 * @param $mode
	 * @param array $args
	 *
	 * @return bool
	 */
	function set_mode( $mode, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'mode'     => $mode,
			'progress' => (int) $this->progress,
		) );

		return Plugin()->set_setting( $args, null, 'status' );
	}

	public function update_terms( Parser $Parser ) {

		$categories = $Parser->get_categories();
		$attributes = $Parser->get_properties();
		$developers = $Parser->get_developers();
		$warehouses = $Parser->get_warehouses();

		$attributeValues = array();
		/** @var Attribute $attribute */
		foreach ( $attributes as $attribute ) {
			/** Collection to simple array */
			foreach ( $attribute->get_terms() as $term ) {
				$attributeValues[] = $term;
			}
		}

		Update::terms( $categories );
		Update::term_meta( $categories );

		Update::terms( $developers );
		Update::term_meta( $developers );

		Update::terms( $warehouses );
		Update::term_meta( $warehouses );

		Update::properties( $attributes );
		Update::terms( new Collection($attributeValues) );
		Update::term_meta( $attributeValues );
	}

	public function update_products( Parser $Parser ) {

		global $wpdb, $user_id;

		$Plugin = Plugin();

		// Define empty result
		$results = array(
			'create'      => 0,
			'update'      => 0,
			'meta_update' => 0,
		);

		/** @var Collection $products */
		$products      = $Parser->get_products();
		$productsCount = $products->count();

		/** @recursive update if is $productsCount > $offset */
		if ( $productsCount <= $this->progress ) {

			// Slice products who offset out
			$newProductsCount = $products->slice( $this->progress, $this->offset['product'] );

			// Count products will be updated (new array count this != $productsCount)
			$this->progress += $newProductsCount;

			// Set mode for go away or retry
			$this->set_mode( $this->progress < $productsCount ? 'relationships' : '' );

			// If not disabled option
			if ( 'off' !== ( $post_mode = $Plugin->get_setting( 'post_mode' ) ) ) {
				Transaction()->set_transaction_mode();

				/** @var \NikolayS93\Exchange\Model\ExchangePost $product */
				foreach ( $products as $product ) {
					$product->prepare();

					if ( ! $product->get_id() ) {
						/** if is update only */
						if ( 'update' === $post_mode ) {
							continue;
						}

						if ( $post_id = $product->create() ) {
							$product->set_id( $post_id );
							$results['create'] ++;
						}
					} else {
						/** if is create only */
						if ( 'create' === $post_mode ) {
							continue;
						}

						if ( $product->update() ) {
							$results['update'] ++;
						}
					}
				}
			}

			/**
			 * Update product meta
			 */
			$skip_post_meta = array(
				'value' => $Plugin->get_setting( 'skip_post_meta_value', false ),
				'sku'   => $Plugin->get_setting( 'skip_post_meta_sku', false ),
				'unit'  => $Plugin->get_setting( 'skip_post_meta_unit', false ),
				'tax'   => $Plugin->get_setting( 'skip_post_meta_tax', false ),
			);

			foreach ( $products as $product ) {
				if ( $skip_post_meta['value'] && ! $is_new = $product->is_new() ) {
					continue;
				}

				if ( ! $post_id = $product->get_id() ) {
					continue;
				}

				/**
				 * Get list of all meta by product
				 * ["_sku"], ["_unit"], ["_tax"], ["{$custom}"],
				 */
				$productMeta = $product->get_product_meta();
				array_map( function ( $k, $v ) use ( &$results, $post_id, $is_new, $skip_post_meta ) {
					$value = trim( $v );

					if ( '_sku' == $k && $skip_post_meta['sku'] ) {
						return $value;
					}

					if ( '_unit' == $k && $skip_post_meta['unit'] ) {
						return $value;
					}

					if ( '_tax' == $k && $skip_post_meta['tax'] ) {
						return $value;
					}

					if ( update_post_meta( $post_id, $k, $value ) ) {
						$results['meta_update'] ++;
					}

					return $value;
				}, array_keys( $productMeta ), $productMeta );
			}

			$status   = array();
			$status[] = "$this->progress из $productsCount записей товаров обработано.";
			$status[] = $results['create'] . " товаров добавлено.";
			$status[] = $results['update'] . " товаров обновлено.";
			$status[] = $results['meta_update'] . " произвольных записей товаров обновлено.";

			$msg = implode( ' -- ', $status );

			exit( "progress\n$msg" );
		}
	}

	/**
	 * @todo write it for mltile offers
	 */
	public function update_offers( Array &$offers ) {
		return array(
			'create' => 0,
			'update' => 0,
		);
	}

	/***************************************************************************
	 * Update relationships
	 */
	public static function relationships( Array $posts, $args = array() ) {
		/** @global \wpdb $wpdb built in wordpress db object */
		global $wpdb;

		$updated = 0;

		foreach ( $posts as $post ) {
			if ( ! $post_id = $post->get_id() ) {
				continue;
			}

			if ( method_exists( $post, 'updateObjectTerms' ) ) {
				$updated += $post->updateObjectTerms();
			}

			if ( method_exists( $post, 'updateAttributes' ) ) {
				$post->updateAttributes();
			}
		}

		return $updated;
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

	/**
	 * @param Collection $termsCollection
	 */
	private static function terms( &$termsCollection ) {
		$updated = 0;
		/** @var \NikolayS93\Exchange\Model\Abstracts\Term $term */
		$closure = function($term, $offset) use (&$updated) {
			if( $term->prepare() ) {
				$updated += (int) $term->update();
			}
		};

		$termsCollection->walk($closure);

		return $updated;
	}

	private static function term_meta( $terms ) {
		global $wpdb;

		$insert = array();
		$phs    = array();

		$meta_structure = Category::get_structure( 'term_meta' );

		$structure       = static::get_sql_structure( $meta_structure );
		$duplicate       = static::get_sql_duplicate( $meta_structure );
		$sql_placeholder = static::get_sql_placeholder( $meta_structure );

		foreach ( $terms as $term ) {
			if ( ! $term->get_id() ) {
				continue;
			}

			array_push( $insert, $term->meta_id, $term->get_id(), $term->getExtID(), $term->getExternal() );
			array_push( $phs, $sql_placeholder );
		}

		$updated_rows = 0;
		if ( ! empty( $insert ) && ! empty( $phs ) ) {
			$query        = static::get_sql_update( $wpdb->termmeta, $structure, $insert, $phs, $duplicate );
			$updated_rows = $wpdb->query( $query );
		}

		return $updated_rows;
	}

	private static function properties( &$properties = array() ) {
		global $wpdb;

		$Plugin = Plugin();

		$retry = false;

		if ( 'off' === ( $attribute_mode = $Plugin->get_setting( 'attribute_mode' ) ) ) {
			return $retry;
		}

		foreach ( $properties as $propSlug => $property ) {
			$slug = $property->getSlug();

			/**
			 * Register Property's Taxonomies;
			 */
			if ( 'select' == $property->getType() && ! $property->get_id() && ! taxonomy_exists( $slug ) ) {
				$external  = $property->getExternal();
				$attribute = $property->fetch();

				$result = wc_create_attribute( $attribute );

				if ( is_wp_error( $result ) ) {
					Error::set_message("Тэг xml во временном потоке не обнаружен.", "Notice");
				}

				$attribute_id = intval( $result );
				if ( 0 < $attribute_id ) {
					if ( $external ) {
						$property->set_id( $attribute_id );

						$insert = $wpdb->insert(
							$wpdb->prefix . 'woocommerce_attribute_taxonomymeta',
							array(
								'meta_id'    => null,
								'tax_id'     => $attribute_id,
								'meta_key'   => EXTERNAL_CODE_KEY,
								'meta_value' => $external,
							),
							array( '%s', '%d', '%s', '%s' )
						);
					} else {
						Error::set_message( __( 'Empty attr insert or attr external by ' . $attribute['attribute_label'] ) );
					}

					$retry = true;
				}

				/**
				 * @var bool
				 * Почему то термины не вставляются сразу же после вставки таксономии (proccess_add_attribute)
				 * Нужно будет пройтись еще раз и вставить термины.
				 */
				$retry = true;
			}

			if ( ! taxonomy_exists( $slug ) ) {
				/**
				 * For exists imitation
				 */
				register_taxonomy( $slug, 'product' );
			}
		}

		return $retry;
	}

	// $columns = array('sku', 'unit', 'price', 'quantity', 'stock_wh')
	private static function offer_post_meta( Array &$offers )
	{
		global $wpdb, $user_id;

		$Plugin = Plugin();

		$result = array(
			'update' => 0,
		);

		/** @var ExchangeOffer $offer */
		foreach ( $offers as $offer ) {
			// if ($offer->is_new())
			if ( ! $post_id = $offer->get_id() ) {
				continue;
			}

			$properties = array();

			// its on post only?
			if ( $unit = $offer->getMeta( 'unit' ) ) {
				$properties['_unit'] = $unit;
			}

			if ( 'off' !== $Plugin->get_setting( 'offer_price', false ) ) {
				if ( $price = $offer->getMeta( 'price' ) ) {
					$properties['_regular_price'] = $price;
					$properties['_price']         = $price;
				}
			}

			if ( 'off' !== $Plugin->get_setting( 'offer_qty', false ) ) {
				$qty = $offer->get_quantity();

				$properties['_manage_stock'] = 'yes';
				$properties['_stock_status'] = 0 < $qty ? 'instock' : 'outofstock';
				$properties['_stock']        = $qty;

				if ( $stock_wh = $offer->getMeta( 'stock_wh' ) ) {
					$properties['_stock_wh'] = $stock_wh;
				}
			}

			if ( 'off' !== $Plugin->get_setting( 'offer_weight', false ) && $weight = $offer->getMeta( 'weight' ) ) {
				$properties['_weight'] = $weight;
			}

			// Типы цен
			// if( $exOffer->prices ) {
			//     $properties['_prices'] = $exOffer->prices;
			// }

			foreach ( $properties as $property_key => $property ) {
				$result['update'] ++;
				update_post_meta( $post_id, $property_key, $property );
				// wp_cache_delete( $post_id, "{$property_key}_meta" );
			}
		}

		return $result['update'];
	}
}
