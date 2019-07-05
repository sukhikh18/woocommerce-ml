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

        $date_now = current_time('mysql');
        $gmdate_now = gmdate('Y-m-d H:i:s');

        $results = array(
            'create' => 0,
            'update' => array(),
        );

        foreach ($products as &$product)
        {
            $product->prepare();
            $p = $product->getObject();

            if( !$product->get_id() ) {
                $results['create']++;

                /**
                 * Collect insert data
                 */

                // Is date null
                if( '0000-00-00 00:00:00' === $p->post_date || '' === $p->post_date )         $p->post_date = $date_now;
                if( '0000-00-00 00:00:00' === $p->post_date_gmt || '' === $p->post_date_gmt ) $p->post_date_gmt = $gmdate_now;

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
            else {
                $results['update'][] = $product->get_id();
            }
        }

        /**
         * Check update exists
         */
        $update_count = sizeof( $results['update'] );

        /**
         * Update date_modify
         */
        if( 0 < $update_count ) {
            $q = $wpdb->query( "UPDATE $wpdb->posts
                SET `post_modified` = '$date_now', `post_modified_gmt` = '$gmdate_now'
                WHERE ID in (" . implode(",", $results['update']) . ")
            " );
        }

        /**
         * Create new posts
         */
        if( sizeof($insert) && sizeof($phs) ) {
            $query = static::get_sql_update($wpdb->posts, $structure, $insert, $phs, $duplicate);
            $q = $wpdb->query( $query );
        }

        $results['update'] = $update_count;

        return $results;
    }

    public static function postmeta( &$products )
    {
        $results = array(
            'update' => 0,
        );

        foreach ($products as &$product)
        {
            if( !$product->isNew() ) continue;

            /**
             * @todo think how to get inserted meta
             */
            if( !$post_id = $product->get_id() ) continue;

            /**
             * Get list of all meta by product
             */
            $listOfMeta = $product->getMeta();

            foreach ($listOfMeta as $mkey => $mvalue)
            {
                update_post_meta( $post_id, $mkey, trim($mvalue) );
                $results['update']++;
            }
        }

        return $results;
    }

    /**
     * @param  array  &$terms  as $rawExt => ExchangeTerm
     */
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
             * @todo Check why need double iteration for parents
             * @note do not update exists terms
             * @todo add filter for update oldest
             * @note So, i think, we can write author's user_id and do not touch if is edited by not automaticly
             */
            if( $term_id ) {
                $result = array('term_id' => $term_id);
                // unset( $arTerm['parent'] );
                // $result = wp_update_term( $term_id, $arTerm['taxonomy'], array_filter(apply_filters('1c4wp_update_term', $arTerm )) );
            }
            else {
                $result = wp_insert_term( $arTerm['name'], $arTerm['taxonomy'], array_filter(apply_filters('1c4wp_insert_term', $arTerm )) );
            }

            if( !is_wp_error($result) ) {
                $term->set_id( $result['term_id'] );
                $updated[ $result['term_id'] ] = $term;

                foreach ($terms as &$oTerm)
                {
                    if( $term->getExternal() === $oTerm->getParentExternal() ) {
                        $oTerm->set_parent_id( $term->get_id() );
                    }
                }
            }
            else {
                Utils::addLog( $result, $arTerm );
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
            if( 'select' == $property->getType() && !$property->get_id() && !taxonomy_exists($slug) ) {
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

                        $insert = $wpdb->insert(
                            $wpdb->prefix . 'woocommerce_attribute_taxonomymeta',
                            array(
                                'meta_id'    => null,
                                'tax_id'     => $attribute_id,
                                'meta_key'   => ExchangeTerm::getExtID(),
                                'meta_value' => $external,
                            ),
                            array( '%s', '%d', '%s', '%s' )
                        );
                    }
                    else {
                        Utils::addLog(new \WP_Error( 'error', __('Empty attr insert or attr external by ' . $attribute['attribute_label'])));
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

            if( !taxonomy_exists($slug) ) {
                /**
                 * For exists imitation
                 */
                register_taxonomy( $slug, 'product' );
            }
        }

        return $retry;
    }

    /**
     * @todo write it for mltile offers
     */
    public static function offers( Array &$offers )
    {
        return array(
            'create' => 0,
            'update' => 0,
        );
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
            /**
             * for new products only
             * @todo add filter
             */
            if( !$post->isNew() ) continue;

            if( !$post_id = $post->get_id() ) continue;

            $wp_post = $post->getObject();

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