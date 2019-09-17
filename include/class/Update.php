<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\ORM\CollectionTerms;
use NikolayS93\Exchange\Traits\Singleton;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Model\Interfaces\Term;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Developer;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\Warehouse;
use NikolayS93\Exchange\Model\ExchangeOffer;
use NikolayS93\Exchange\Model\ExchangePost;


class Update {

	use Singleton;

	public $offset;

	/** @var $progress int Offset from */
	public $progress;

	public $results;

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

	function __init() {
		$this->offset = apply_filters( PLUGIN::PREFIX . 'update_count_offset', array(
			'product' => 500,
			'offer'   => 500,
			'rest'    => 1001,
			'price'   => 1001,
		) );

		$this->progress = intval( Plugin()->get_setting( 'progress', 0, 'status' ) );

		$this->results = array(
			'create' => 0,
			'update' => 0,
			'meta'   => 0,
		);
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

	function update_meta( $post_id, $property_key, $property ) {
		if ( update_post_meta( $post_id, $property_key, $property ) ) {
			$this->results['meta'] ++;
		}
	}

	public function update_products( Parser $Parser ) {

		global $wpdb, $user_id;

		$Plugin = Plugin();

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
			$this->set_mode( $this->progress < $productsCount ? 'import_relationships' : '' );

			// If not disabled option
			if ( 'off' !== ( $post_mode = $Plugin->get_setting( 'post_mode' ) ) ) {
				Transaction::get_instance()->set_transaction_mode();

				/**
				 * @param ExchangePost $product
				 */
				$update_products = function ( $product ) use ( $post_mode ) {
					if ( $product->prepare( $post_mode ) ) {
						if ( ! $product->get_id() ) {
							// if is update only
							if ( 'update' !== $post_mode && $post_id = $product->create() ) {
								// define ID for use on post meta update
								$product->set_id( $post_id );
								$this->results['create'] ++;
							}
						} else {
							// if is create only
							if ( 'create' === $post_mode && $product->update() ) {
								$this->results['update'] ++;
							}
						}
					}
				};

				$products->walk( $update_products );
			}

			/**
			 * Update post meta
			 */
			$skip_post_meta = array(
				'value' => $Plugin->get_setting( 'skip_post_meta_value', false ),
				'sku'   => $Plugin->get_setting( 'skip_post_meta_sku', false ),
				'unit'  => $Plugin->get_setting( 'skip_post_meta_unit', false ),
				'tax'   => $Plugin->get_setting( 'skip_post_meta_tax', false ),
			);

			/**
			 * @param ExchangeProduct $product
			 */
			$update_product_meta = function ( $product ) use ( $skip_post_meta ) {
				if ( $post_id = $product->get_id() ) {
					if ( ! $skip_post_meta['value'] || $is_new = $product->is_new() ) {
						// Get list of all meta by product ["_sku"], ["_unit"], ["_tax"], ["{$custom}"]
						$product_meta = $product->get_product_meta();

						array_map( function ( $k, $v ) use ( $post_id, $is_new, $skip_post_meta ) {
							$value = trim( $v );

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

			$products->walk( $update_product_meta );

			$status   = array();
			$status[] = "$this->progress из $productsCount записей товаров обработано.";
			$status[] = $this->results['create'] . " товаров добавлено.";
			$status[] = $this->results['update'] . " товаров обновлено.";
			$status[] = $this->results['meta'] . " произвольных записей товаров обновлено.";

			$msg = implode( ' -- ', $status );

			exit( "progress\n$msg" );
		}
	}

	/**
	 * @param Collection $offers
	 *
	 * @todo write it for mltile offers
	 */
	public function _update_offers( $offers ) {
		global $wpdb, $user_id;

		$Plugin = Plugin();

		$skip_offer_meta = array(
			'offer_price'  => $Plugin->get_setting( 'offer_price', false ),
			'offer_qty'    => $Plugin->get_setting( 'offer_qty', false ),
			'offer_weight' => $Plugin->get_setting( 'offer_weight', false ),
		);

		/** @var ExchangeOffer $offer */
		$update_offer_meta = function ( $offer ) use ( $Plugin, $skip_offer_meta ) {
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

			if ( 'off' !== $Plugin->get_setting( 'offer_weight', false ) && $weight = $offer->get_meta( 'weight' ) ) {
				$this->update_meta( $post_id, '_weight', $weight );
			}

			// Типы цен
			// if( $offer->prices ) {}
		};

		$offers->walk( $update_offer_meta );
	}

	public function update_offers( Parser $Parser ) {
		$filename = Request::get_filename();

		$offers      = $Parser->get_offers();
		$offersCount = sizeof( $offers );
		/** @recursive update if is $offersCount > $offset */
		if ( $offersCount > $this->progress ) {
			Transaction()->set_transaction_mode();

			/**
			 * Slice offers who offset better
			 */
			$offers = array_slice( $offers->fetch(), $this->progress, $this->offset['offer'] );

			/** Count offers who will be updated */
			$this->progress += sizeof( $offers );

			$answer = 'progress';

			/** Require retry */
			if ( $this->progress < $offersCount ) {
				$this->set_mode( '' );
			} /** Go away */
			else {
				if ( 0 === strpos( $filename, 'offers' ) ) {
					$this->set_mode( 'import_relationships' );
				} else {
					$answer = 'success';
					$this->set_mode( '' );
				}
			}

			$resOffers = Update::offers( $offers );

			// has new products without id
			if ( 0 < $resOffers['create'] ) {
				ExchangeOffer::fillExistsFromDB( $offers, $orphaned_only = true );
			}

			Update::offerPostMetas( $offers );

			if ( 0 === strpos( $filename, 'price' ) ) {
				$msg = "$this->progress из $offersCount цен обработано.";
			} elseif ( 0 === strpos( $filename, 'rest' ) ) {
				$msg = "$this->progress из $offersCount запасов обработано.";
			} else {
				$msg = "$this->progress из $offersCount предложений обработано.";
			}

			exit( "$answer\n$msg" );
		}
	}

	public function update_products_relationships( Parser $Parser ) {
		$msg = 'Обновление зависимостей завершено.';

		/** @var $progress int Offset from */
		$products       = $Parser->get_products();
		$products_count = $products->count();

		if ( $products_count > $this->progress ) {
			// Plugin::set_transaction_mode();
			$offset         = apply_filters( 'exchange_products_relationships_offset', 500, $products_count,
				Request::get_filename() );
			$products       = array_slice( $products->fetch(), $this->progress, $offset );
			$sizeOfProducts = sizeof( $products );

			/**
			 * @todo write really update counter
			 */
			$relationships  = Update::relationships( $products );
			$this->progress += $sizeOfProducts;
			$msg            = "$relationships зависимостей $sizeOfProducts товаров обновлено (всего $progress из $products_count обработано).";

			/** Require retry */
			if ( $this->progress < $products_count ) {
				$this->set_mode( 'import_relationships' );
				exit( "progress\n$msg" );
			}
		}

		$this->set_mode( '' );
		exit( "success\n$msg" );
	}

	public function update_offers_relationships( Parser $Parser ) {
		$msg = 'Обновление зависимостей завершено.';

		$offers      = $Parser->get_offers();
		$offersCount = $offers->count();

		if ( $offersCount > $this->progress ) {
			// Plugin::set_transaction_mode();
			$offset       = apply_filters( 'exchange_offers_relationships_offset', 500, $offersCount, $filename );
			$offers       = array_slice( $offers->fetch(), $this->progress, $offset );
			$sizeOfOffers = sizeof( $offers );

			$relationships  = Update::relationships( $offers );
			$this->progress += $sizeOfOffers;
			$msg            = "$relationships зависимостей $sizeOfOffers предложений обновлено (всего $this->progress из $offersCount обработано).";

			/** Require retry */
			if ( $this->progress < $offersCount ) {
				$this->set_mode( 'import_relationships' );
				exit( "progress\n$msg" );
			}

			if ( floatval( $this->version ) < 3 ) {
				$this->set_mode( 'deactivate' );
				exit( "progress\n$msg" );
			}
		}

		$this->set_mode( '' );
		exit( "success\n$msg" );
	}

	public function update_terms( Parser $Parser ) {
		$Parser
			->watch_terms()
			->parse();

		$categories = $Parser->get_categories()->fill_exists();
		$developers = $Parser->get_developers()->fill_exists();
		$warehouses = $Parser->get_warehouses()->fill_exists();

		Update::terms( $categories );
		Update::term_meta( $categories );

		Update::terms( $developers );
		Update::term_meta( $developers );

		Update::terms( $warehouses );
		Update::term_meta( $warehouses );

//        $attributes = $Parser->get_properties()->fill_exists();
//	      $attributeValues = $attributes->get_all_values();

//        Update::properties( $attributes );
//        Update::terms( $attributeValues );
//        Update::term_meta( $attributeValues );
	}

	/**
	 * @param Collection $termsCollection
	 */
	private static function terms( &$termsCollection ) {
		$updated = 0;
		/** @var \NikolayS93\Exchange\Model\Abstracts\Term $term */
		$closure = function ( $term, $offset ) use ( &$updated ) {
			if ( $term->prepare() ) {
				$updated += (int) $term->update();
			}
		};

		$termsCollection->walk( $closure );

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

		/** @var Model\Abstracts\Term $term */
		foreach ( $terms as $term ) {
			if ( ! $term->get_id() ) {
				continue;
			}

			array_push( $insert, $term->meta_id, $term->get_id(), $term->get_external_key(), $term->get_external() );
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
			$slug = $property->get_slug();

			/**
			 * Register Property's Taxonomies;
			 */
			if ( 'select' == $property->get_type() && ! $property->get_id() && ! taxonomy_exists( $slug ) ) {
				$external  = $property->get_external();
				$attribute = $property->fetch();

				$result = wc_create_attribute( $attribute );

				if ( is_wp_error( $result ) ) {
					Error()
						->add_message( $result, "Warning", true )
						->add_message( $attribute, "Target", true );

					continue;
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

}
