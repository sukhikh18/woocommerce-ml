<?php

class ProductsArchive
{
    static function posts( &$products )
    {
        global $wpdb;

        $all_products = $wpdb->get_results( "
            SELECT ID, post_mime_type
            FROM $wpdb->posts
            WHERE
                post_type = 'product'
            AND post_status = 'publish'" );

        /**
         * Some new products no have a ID
         */
        $exchanged_ext = array();
        foreach ($products as $exProduct) {
            $exchanged_ext[] = $exProduct->ext;
        }

        foreach ($all_products as $product) {
            if( !in_array($product->post_mime_type, $exchanged_ext) ) {
                $wpdb->update(
                    $wpdb->posts,
                    // set
                    array( 'post_status' => 'draft' ),
                    // where
                    array(
                        'post_mime_type' => $product->post_mime_type,
                        'post_status' => 'publish'
                        )
                    );
            }
        }
    }
}
