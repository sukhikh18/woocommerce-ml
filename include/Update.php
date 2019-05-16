<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Model\TermModel;
use NikolayS93\Exchange\Model\ProductModel;

use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeAttribute;
use NikolayS93\Exchange\Model\ExchangePost;

class Update
{

    static function get_sql_placeholder( $structure )
    {
        $values = array();
        foreach ($structure as $column => $format) {
            $values[] = "'$format'";
        }

        $query = '(' . implode(",", $values) . ')';

        return $query;
    }

    static function get_sql_structure( $structure )
    {
        return '('. implode(', ', array_keys( $structure )) .')';
    }

    static function get_sql_duplicate( $structure )
    {
        $values = array();
        foreach ($structure as $column => $format) {
            $values[] = "$column = VALUES($column)";
        }

        $query = implode(",\n", $values) . ';';

        return $query;
    }

    static function get_sql_update($bd_name, $structure, $insert, $placeholders, $duplicate)
    {
        global $wpdb;

        $query = "INSERT INTO $bd_name $structure VALUES " . implode(', ', $placeholders);
        $query.= " ON DUPLICATE KEY UPDATE " . $duplicate;

        return $wpdb->prepare($query, $insert);
    }

    public static function posts( &$products )
    {
        global $wpdb, $user_id;

        if( empty($products) || !is_array($products) ) return;

        $insert = array();
        $phs = array();

        $posts_structure = ExchangePost::get_structure('posts');

        $structure = static::get_sql_structure( $posts_structure );
        $duplicate = static::get_sql_duplicate( $posts_structure );
        $sql_placeholder = static::get_sql_placeholder( $posts_structure );

        $date_now = date('Y-m-d H:i:s');
        $gmdate_now = gmdate('Y-m-d H:i:s');

        foreach ($products as &$product)
        {
            $product->prepare();
            $p = $product->getObject();

            if( '0000-00-00 00:00:00' === $p->post_date )     $p->post_date = $date_now;
            if( '0000-00-00 00:00:00' === $p->post_date_gmt ) $p->post_date_gmt = $gmdate_now;

            array_push($insert,
                $p->ID,
                $p->post_author,
                $p->post_date,
                $p->post_date_gmt,
                $p->post_content,
                $p->post_title,
                $p->post_excerpt,
                $p->post_status,
                $p->comment_status,
                $p->ping_status,
                $p->post_password,
                $p->post_name,
                $p->to_ping,
                $p->pinged,
                $p->post_modified = $date_now,
                $p->post_modified_gmt = $gmdate_now,
                $p->post_content_filtered,
                $p->post_parent,
                $p->guid,
                $p->menu_order,
                $p->post_type,
                $p->post_mime_type,
                $p->comment_count);

            array_push($phs, $sql_placeholder);
        }

        /**
         * Update posts
         */
        if( sizeof($insert) && sizeof($phs) ) {
            $query = static::get_sql_update($wpdb->posts, $structure, $insert, $phs, $duplicate);
            $wpdb->query( $query );
        }
    }

    public static function postmeta( &$products )
    {
        foreach ($products as &$product)
        {
            /**
             * @todo think how to get inserted meta
             */
            if( !$post_id = $product->get_id() ) {
                Utils::addLog( 'Нет ID товара для его наполнения пользовательскими поялми' .
                    "\r\n" . print_r($product, 1) );
            }

            /**
             * Get list of all meta by product
             */
            $listOfMeta = $product->getMeta();

            foreach ($listOfMeta as $mkey => $mvalue)
            {
                update_post_meta( $post_id, $mkey, trim($mvalue) );
            }
        }
    }

    public static function terms( &$terms )
    {
        global $wpdb, $user_id;

        $updated = array();

        foreach ($terms as &$term)
        {
            $term->prepare();

            /**
             * @var Int WP_Term->term_id
             */
        	$term_id = $term->get_id();

            /**
             * @var WP_Term
             */
            $obTerm = $term->getTerm();

            /**
             * @var array
             */
            $arTerm = (array) $obTerm;

            /**
             * @todo need double iteration for parents
             */
            if( $term_id ) {
                /**
                 * Do not update parent term
                 */
                unset( $arTerm['parent'] );

                $result = wp_update_term( $term_id, $arTerm['taxonomy'], array_filter(apply_filters('1c4wp_update_term', $arTerm )) );
            }
            else {
                $result = wp_insert_term( $arTerm['name'], $arTerm['taxonomy'], array_filter(apply_filters('1c4wp_insert_term', $arTerm )) );
            }

            if( !is_wp_error($result) ) {
                $term_id = $result['term_id'];
                $term->set_id( $term_id );
                $updated[ $result['term_id'] ] = $term;
            }
            else {
                /**
                 * if is term exists
                 * Некоторые аттрибуты могут иметь одинаковый слаг, попробуем залить аттрибуты разных категорий в один слаг
                 * @todo check this
                 * @warning Crunch!
                 */
                if( 'term_exists' == $result->get_error_code() ) {
                    $term_id = $result->get_error_data();
                    $term->set_id( $term_id );
                }
                else {
                    Utils::addLog( $result );
                }
            }
        }

        return $updated;
    }

    public static function termmeta($terms)
    {
        global $wpdb;

        $insert = array();
        $phs = array();

        $meta_structure = ExchangeTerm::get_structure('termmeta');

        $structure = static::get_sql_structure( $meta_structure );
        $duplicate = static::get_sql_duplicate( $meta_structure );
        $sql_placeholder = static::get_sql_placeholder( $meta_structure );

        foreach ($terms as $term)
        {
            if( !$term->get_id() ) { // Utils::addLog( new WP_Error() );
                continue;
            }

            array_push($insert, $term->meta_id, $term->get_id(), $term->getExtID(), $term->getExternal());
            array_push($phs, $sql_placeholder);
        }

        $updated_rows = 0;
        if( !empty($insert) && !empty($phs) ) {
            $query = static::get_sql_update($wpdb->termmeta, $structure, $insert, $phs, $duplicate);
            $updated_rows = $wpdb->query( $query );
        }

        return $updated_rows;
    }

    public static function properties( &$properties = array() )
    {
        global $wpdb;

        $retry = false;

        foreach ($properties as $propSlug => $property)
        {
            $slug = $property->getSlug();

            /**
             * Register Property's Taxonomies;
             */
            if( !taxonomy_exists($slug) ) {
                /**
                 * @var Array
                 */
                $external = $property->getExternal();
                $attribute = $property->fetch();

                $result = wc_create_attribute( $attribute );

                if( is_wp_error( $result ) ) Utils::addLog( $result, $attribute );

                $attribute_id = intval( $result );
                if( 0 < $attribute_id ) {
                    if( $external ) {
                        $property->set_id( $attribute_id );
                        $insert = $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomymeta', array(
                            'tax_id' => $attribute_id,
                            'meta_key' => ExchangeTerm::getExtID(),
                            'meta_value' => $external,
                        ) );
                    }
                    else {
                        Utils::addLog(new \WP_Error( 'error', __('Empty attr insert or attr external by ' . $attribute['attribute_label'])));
                    }

                    /**
                     * For exists imitation
                     */
                    register_taxonomy( $slug, 'product' );

                    $retry = true;
                }

                /**
                 * @var bool
                 * Почему то термины не вставляются сразу же после вставки таксономии (proccess_add_attribute)
                 * Нужно будет пройтись еще раз и вставить термины.
                 */
                $retry = true;
            }
        }

        return $retry;
    }

    /**
     * @todo write it for mltile offers
     */
    public static function offers( Array &$offers )
    {
    }

    public static function offerPostMetas( Array &$offers ) // $columns = array('sku', 'unit', 'price', 'quantity', 'stock_wh')
    {
        global $wpdb, $user_id;

        foreach ($offers as $obExchangeOffer)
        {
            if( !$post_id = $obExchangeOffer->get_id() ) continue;

            /**
             * @todo How to check new product?
             */
            $update = false;

            // if( !$update ) {
            //     $properties = array(
            //         '_unit' => $exOffer->unit,
            //         // '_wc_review_count' => '0',
            //         // '_wc_rating_count' => 'a:0:{}',
            //         // '_wc_average_rating' => '0',
            //         // '_edit_lock' => '',
            //         '_product_attributes' => 'a:0:{}',
            //         '_regular_price' => $obExchangeOffer->getMeta('_regular_price'),
            //         '_price' => $obExchangeOffer->getMeta('_price'),
            //         '_manage_stock' => 'yes',
            //         '_stock_status' => 1 <= $exOffer->quantity ? 'instock' : 'outofstock',
            //         '_stock' => $exOffer->quantity,
            //         '_edit_last' => get_current_user_id(),
            //         '_sale_price' => '',
            //         '_sale_price_dates_from' => '',
            //         '_sale_price_dates_to' => '',
            //         '_tax_status' => 'taxable',
            //         '_tax_class' => '',
            //         // '_backorders' => 'no',
            //         // '_low_stock_amount' => '',
            //         '_sold_individually' => 'no',
            //         '_weight' => $exOffer->weight,
            //         '_length' => '',
            //         '_width' => '',
            //         '_height' => '',
            //         // '_upsell_ids' => 'a:0:{}',
            //         // '_crosssell_ids' => 'a:0:{}',
            //         // '_purchase_note' => '',
            //         '_default_attributes' => 'a:0:{}',
            //         '_virtual' => 'no',
            //         '_downloadable' => 'no',
            //         '_product_image_gallery' => '',
            //         // '_download_limit' => '-1',
            //         // '_download_expiry' => '-1',
            //         '_product_version' => '3.5.6',
            //         'total_sales' => 0,
            //     );

            //     if( $exOffer->prices ) {
            //         $properties['_prices'] = $exOffer->prices;
            //     }

            //     if( $exOffer->stock_wh ) {
            //         $properties['_stock_wh'] = $exOffer->stock_wh;
            //     }
            // }

            $properties = array();

            if( $unit = $obExchangeOffer->getMeta('unit') ) {
                $properties['_unit'] = $unit;
            }

            if( $price = $obExchangeOffer->getMeta('price') ) {
                $properties['_regular_price'] = $price;
                $properties['_price'] = $price;
            }


            /**
             * @todo fixit (think about)
             * We want manage all stock :)
             */
            $qty = $obExchangeOffer->get_quantity();

            // if( null !== $qty ) {
                $properties['_manage_stock'] = 'yes';
                $properties['_stock_status'] = 0 < $qty ? 'instock' : 'outofstock';
                $properties['_stock']        = $qty;
            // }

            if( $weight = $obExchangeOffer->getMeta('weight') ) {
                $properties['_weight'] = $weight;
            }

            // if( $exOffer->prices ) {
            //     $properties['_prices'] = $exOffer->prices;
            // }

            // if( $exOffer->stock_wh ) {
            //     $properties['_stock_wh'] = $exOffer->stock_wh;
            // }

            foreach ($properties as $property_key => $property)
            {
                update_post_meta( $post_id, $property_key, $property );
                // wp_cache_delete( $post_id, "{$property_key}_meta" );
            }
        }
    }

    /***************************************************************************
     * Update relationships
     */
    public static function relationships( Array $posts, $args = array() )
    {
        /** @global wpdb $wpdb built in wordpress db object */
        global $wpdb;

        $updated = 0;

        foreach ($posts as $post)
        {
            if( !$post_id = $post->get_id() ) continue;

            $wp_post = $post->getObject();

            /**
             * for new products only
             */
            if( $wp_post->post_date != $wp_post->post_modified ) continue;

            if( method_exists($post, 'updateAttributes') ) {
                $post->updateAttributes();
            }

            if( method_exists($post, 'updateObjectTerms') ) {
                $updated += $post->updateObjectTerms();
            }
        }

        return $updated;
    }

    // public static function warehouses_ext()
    // {
    //     $xmls = array(
    //         'primary'    => get_theme_mod( 'primary_XML_ID', '0' ),
    //         'secondary'  => get_theme_mod( 'secondary_XML_ID', '0' ),
    //         'company_3' => get_theme_mod( 'company_3_XML_ID', '0' ),
    //         'company_4' => get_theme_mod( 'company_4_XML_ID', '0' ),
    //         'company_5' => get_theme_mod( 'company_5_XML_ID', '0' ),
    //     );

    //     // $xmls = array_flip($xmls);
    //     // unset( $xmls[0] );

    //     // $wh_count = 0;
    //     // foreach ($warehouses as $wh_key => &$warehouse) {
    //     //     if( isset( $xmls[ $wh_key ] ) ) {
    //     //         $warehouse[ 'contact' ] = $xmls[ $wh_key ];
    //     //         $wh_count++;
    //     //     }
    //     // }

    //     // return $wh_count;
    // }

    public static function update_term_counts()
    {
        global $wpdb;

        /**
         * update taxonomy term counts
         */
        $wpdb->query("
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
        )");

        delete_transient( 'wc_term_counts' );
    }
}
