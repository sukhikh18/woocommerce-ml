<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Model\TermModel;
use NikolayS93\Exchange\Model\ProductModel;

use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeAttribute;

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
        global $wpdb, $site_url, $date_now, $gmdate_now, $user_id;

        if( empty($products) || !is_array($products) ) return;

        $insert = array();
        $phs = array();

        $site_url = get_site_url();
        $date_now = date('Y-m-d H:i:s');
        $gmdate_now = gmdate('Y-m-d H:i:s');

        $posts_structure = ExchangePost::get_structure('posts');

        $structure = static::get_sql_structure( $posts_structure );
        $duplicate = static::get_sql_duplicate( $posts_structure );
        $sql_placeholder = static::get_sql_placeholder( $posts_structure );

        foreach ($products as &$product)
        {
            $product->prepare();
            $product->fill( $insert, $phs );

            $p = $product->getObject();
            array_push($insert, $p->ID, $p->post_author, $p->post_date, $p->post_date_gmt, $p->post_content, $p->post_title, $p->post_excerpt, $p->post_status, $p->comment_status, $p->ping_status, $p->post_password, $p->post_name, $p->to_ping, $p->pinged, $p->post_modified, $p->post_modified_gmt, $p->post_content_filtered, $p->post_parent, $p->guid, $p->menu_order, $p->post_type, $p->post_mime_type, $p->comment_count);

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
            if( (!$post_id = $product->get_id()) && !is_debug() ) continue;

            /**
             * Get list of all meta by product
             */
            $listOfMeta = $product->getMeta();
            foreach ($listOfMeta as $mkey => $mvalue)
            {
                update_post_meta( $post_id, "_{$mkey}", $mvalue );
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
             * @todo add apply filter
             * @var array
             */
            $arTerm = array_filter( (array) $obTerm );

            /**
             * @todo need double iteration for parents
             */
            if( $term_id ) {
                $result = wp_update_term( $term_id, $arTerm['taxonomy'], $arTerm );
            }
            else {
                $result = wp_insert_term( $arTerm['name'], $arTerm['taxonomy'], $arTerm );
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

                if ( empty( $attribute['attribute_name'] ) || empty( $attribute['attribute_label'] ) ) {
                    Utils::addLog( new \WP_Error( 'error', __( 'Please, provide an attribute name and slug.', 'woocommerce' ) ) );
                    continue;
                }
                elseif ( ( $valid_attribute_name = \NikolayS93\Exchange\valid_attribute_name( $attribute['attribute_name'] ) ) && is_wp_error( $valid_attribute_name ) ) {
                    Utils::addLog( $valid_attribute_name );
                    continue;
                }
                elseif ( taxonomy_exists( wc_attribute_taxonomy_name( $attribute['attribute_name'] ) ) ) {
                    Utils::addLog(new \WP_Error( 'error', sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'woocommerce' ), sanitize_title( $attribute['attribute_name'] ) ) ));
                    continue;
                }

                $insert = $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );

                do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );

                // flush_rewrite_rules();
                delete_transient( 'wc_attribute_taxonomies' );

                if( is_wp_error($insert) ) {
                    Utils::addLog( $insert );
                    continue;
                }
                elseif( $wpdb->insert_id && $external ) {
                    $property->set_id( $wpdb->insert_id );
                    $insert = $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomymeta', array(
                        'tax_id' => $wpdb->insert_id,
                        'meta_key' => ExchangeTerm::getExtID(),
                        'meta_value' => $property->getExternal(),
                    ) );

                    $retry = true;
                    if( is_wp_error($insert) ) {
                        Utils::addLog( $insert );
                        continue;
                    }
                }
                else {
                    Utils::addLog(new \WP_Error( 'error', __('Empty attr insert or attr external by ' . $attribute['attribute_label'])));
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

        foreach ($offers as $exOffer)
        {
            if( (!$post_id = $exOffer->get_id()) && !is_debug() ) continue;

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
            //         '_regular_price' => $exOffer->price,
            //         '_price' => $exOffer->price,
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
            // else {
            // }
            $properties = array();

            if( $exOffer->unit ) {
                $properties['_unit'] = $exOffer->unit;
            }

            if( $exOffer->price ) {
                $properties['_regular_price'] = $exOffer->price;
                $properties['_price'] = $exOffer->price;
            }

            if( '' !== $exOffer->quantity ) {
                $properties['_manage_stock'] = 'yes';
                $properties['_stock_status'] = 1 <= $exOffer->quantity ? 'instock' : 'outofstock';
                $properties['_stock']        = $exOffer->quantity;
            }

            if( $exOffer->weight ) {
                $properties['_weight'] = $exOffer->weight;
            }

            if( $exOffer->prices ) {
                $properties['_prices'] = $exOffer->prices;
            }

            if( $exOffer->stock_wh ) {
                $properties['_stock_wh'] = $exOffer->stock_wh;
            }

            foreach ($properties as $property_key => $property)
            {
                update_post_meta( $post_id, $property_key, $property );
                // wp_cache_delete( $post_id, "_{$property_key}_meta" );
            }
        }
    }

    /***************************************************************************
     * Update relationships
     */
    public static function relationships( Array $products, $args = array() )
    {
        /** @global wpdb $wpdb built in wordpress db object */
        global $wpdb;

        $args = wp_parse_args( $args, array(
            'taxonomy' => 'product_cat', // $category, $warehouse, $brand, $attribute
        ) );

        $exsists_terms = array();

        $term_sql = array();

        foreach ($products as $product) {
            /**
             * Получаем все термины данного товара
             */
            if( ($terms = $product->get_taxonomy( $args['taxonomy'] )) && is_array($terms) ) {
                foreach ($terms as $term)
                {
                    if( is_array($term) ) {
                        foreach ($term as $attr) {
                            $extCode = 0 === strpos($attr, 'XML') ? $attr : 'XML/' . $attr;
                            $term_sql[] = "`meta_value` = '$extCode'";
                        }

                        continue;
                    }

                    $extCode = 0 === strpos($term, 'XML') ? $term : 'XML/' . $term;
                    $term_sql[] = "`meta_value` = '$extCode'";
                }
            }
        }

        if( !empty($term_sql) ) {
            $exsists_terms_query = "
                SELECT term_id, meta_key, meta_value
                FROM $wpdb->termmeta
                WHERE meta_key = '". EX_EXT_METAFIELD ."'
                    AND (". implode(" \t\n OR ", array_unique($term_sql)) . ")";

            /**
             * Получаем массив всех упомянутых EXT_ID(терминов) => term_id
             */
            $_exsists_terms = $wpdb->get_results( $exsists_terms_query );

            /**
             * resort array
             */
            foreach ($_exsists_terms as $k => $exsists_term) {
                $exsists_terms[ $exsists_term->meta_value ] = $exsists_term->term_id;
            }
        }

        unset($_exsists_terms);

        foreach ($products as $product) {
            /**
             * Если ID товара не найден пропкускаем товар
             * Теоретически такого совершаться не должно
             */
            if( !$product_id = $product->get_id() ) continue;

            if( ($terms = $product->get_taxonomy( $args['taxonomy'] )) && is_array($terms) ) {
                $relationships = array();
                $attributes = array();

                foreach ($terms as $tax => $term)
                {
                    if( is_array($term) ) {
                        $relationships = array();

                        foreach ($term as $attr)
                        {
                            $extCode = 0 === strpos($attr, 'XML') ? $attr : 'XML/' . $attr;

                            if( !isset($exsists_terms[ $extCode ]) ) continue;

                            if( $term_id = $exsists_terms[ $extCode ] ) {
                                $relationships[] = (int) $term_id;
                            }
                        }

                        if( empty($relationships) ) continue;
                        wp_set_object_terms( $product_id, $relationships, 'pa_' . $tax, false );

                        if( 'properties' == $args['taxonomy'] ) $attributes[] = 'pa_' . $tax;

                        continue;
                    }

                    $extCode = 0 === strpos($term, 'XML') ? $term : 'XML/' . $term;

                    /**
                     * Если такого термина не существует, пропускаем
                     */
                    if( !isset($exsists_terms[ $extCode ]) ) continue;

                    if( $term_id = $exsists_terms[ $extCode ] ) {
                        $relationships[] = (int) $term_id;
                    }
                }

                if( !empty($attributes) ) {
                    $product_attributes = array();

                    foreach ($attributes as $attribute) {
                        $product_attributes[ $attribute ] = array (
                            'name' => $attribute,
                            'value' => '',
                            'position' => 1,
                            'is_visible' => 1,
                            'is_variation' => 0,
                            'is_taxonomy' => 1
                        );
                    }

                    update_post_meta($product_id, '_product_attributes', $product_attributes);
                }

                /**
                 * Если категорий для товаров не найдено, пропускаем данный товар
                 */
                if( empty($relationships) ) continue;

                /**
                 * Добавляем терминов товару
                 * $append = true - иначе, удалим связи с акциями,
                 * новинками и т.д. как правило не созданные в 1с
                 */
                wp_set_object_terms( $product_id, $relationships, $args['taxonomy'], $append = true );
            }
        }
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
