<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Model\TermModel;
use NikolayS93\Exchange\Model\ProductModel;

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

    /***************************************************************************
     * Update taxonomy's terms
     */
    public static function terms( Array &$terms, $args = array() )
    {
        global $wpdb;

        $args = wp_parse_args( $args, array(
            'taxonomy' => 'product_cat',
        ) );

        foreach ($terms as &$term)
        {
        	$term_id = $term->get_id();
            // if( (!$term_id = $term->get_id()) && !is_debug() ) continue;

            $term_args = array(
                'name' => $term->get_name(),
                'description' => $term->get_description(),
            );

            if( $parent_id = $term->get_parent_id( $force = true ) ) {
                $term_args['parent'] = $parent_id;
            }

            if( is_debug() ) {
                var_dump("TERM_ID: " . (int) $term_id, $term_args, $args);
                return;
            }

            if( $term_id ) {
                $result = wp_update_term( $term_id, $args['taxonomy'], $term_args );
            }
            else {
            	// Slice name
                $term_name = $term_args['name'];
                unset($term_args['name']);

                $result = wp_insert_term( $term_name, $args['taxonomy'], $term_args );

                if( !is_wp_error($result) ) $term->term->term_id = $result['term_id'];
            }

            /**
             * @todo check this
             */
            if( is_wp_error($result) ) {
                /**
                 * if is term exists
                 * Некоторые аттрибуты могут иметь одинаковый слаг, попробуем залить аттрибуты разных категорий в один слаг
                 */
                if( 'term_exists' == $result->get_error_code() ) {
                    $term->term->term_id = $result->get_error_data();
                }
            }
        }
    }

    public static function properties( $properties = array() )
    {
        $success = true;

        foreach ($properties as $propSlug => $property)
        {
            $insert = false;
            $slug = strlen($propSlug) >= 28 ? $property['slug'] : $propSlug;
            $taxonomy = wc_attribute_taxonomy_name( $slug );

            /**
             * Register Property's Taxonomies;
             */
            if ( !taxonomy_exists( $taxonomy ) ) {
                $insert = proccess_add_attribute( array(
                    'attribute_name' => $slug,
                    'attribute_label' => $property['name'],
                    'attribute_type' => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 1
                ) );

                if( is_wp_error($insert) ) {
                    /**
                     * @todo add error log
                     */
                    continue;
                }

                /**
                 * @var bool if is need retry
                 * Почему то термины не вставляются сразу же после вставки таксономии (proccess_add_attribute)
                 * Нужно будет пройтись еще раз и вставить термины.
                 */
                $success = 'Требуется дополнительная выгрузка аттрибутов';
            }
            else {
                Parser::fillExistsTermData( $property['values'] );
                // if ( !is_wp_error($insert) ) {
                    Update::terms( $property['values'], array('taxonomy' => $taxonomy) );
                    Update::update_termmetas( $property['values'], array('taxonomy' => $taxonomy) );
                // }
            }
        }

        return $success;
    }

    public static function posts( &$products )
    {
        global $wpdb, $site_url, $date_now, $gmdate_now;

        if( empty($products) || !is_array($products) ) return;

        $insert = array();
        $phs = array();

        $site_url = get_site_url();
        $date_now = date('Y-m-d H:i:s');
        $gmdate_now = gmdate('Y-m-d H:i:s');

        $structure = static::get_sql_structure( ProductModel::get_structure() );
        $duplicate = static::get_sql_duplicate( ProductModel::get_structure() );

        foreach ($products as &$product) {
            $product->prepare();
            $product->fill( $insert, $phs );
        }

        if( !count($insert) || !count($phs) ) return;

        $query = static::get_sql_update($wpdb->posts, $structure, $insert, $phs, $duplicate);

        if( is_debug() ) {
            return;
        }

        $wpdb->query( $query );
    }

    /**
     * @todo write it for mltile offers
     */
    public static function offers( Array &$offers )
    {
    }

    public static function update_termmetas( &$terms )
    {
        global $wpdb, $user_id;

        if( empty($terms) || !is_array($terms) ) return;

        $insert = array();
        $phs = array();

        $structure = static::get_sql_structure( TermModel::get_meta_structure() );
        $duplicate = static::get_sql_duplicate( TermModel::get_meta_structure() );

        foreach ($terms as $term) {
            if( !$term_id = $term->get_id() ) continue;

            $term->prepare();
            $term->fill_meta( $insert, $phs );
        }

        if( !count($insert) || !count($phs) ) return;

        $query = static::get_sql_update($wpdb->termmeta, $structure, $insert, $phs, $duplicate);

        if( is_debug() ) {
            var_dump( substr($query, 0, 1000) );
            return;
        }

        $wpdb->query( $query );
    }

    public static function postmetas( Array &$products ) // $columns = array('sku', 'unit', 'price', 'quantity', 'stock_wh')
    {
        global $wpdb, $user_id;

        foreach ($products as $exProduct)
        {
            if( (!$post_id = $exProduct->get_id()) && !is_debug() ) continue;

            $properties = array(
                'sku'  => $exProduct->sku,
                'unit' => $exProduct->unit,
            );

            foreach ($properties as $property_key => $property)
            {
                update_post_meta( $post_id, "_{$property_key}", $property );
            }
        }
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
