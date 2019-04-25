<?php

namespace NikolayS93\Exchange\Model;

/**
 * Works with posts, postmeta
 */
class ExchangeOffer extends ExchangePost
{
    // use ExchangeItemMeta;

    // private $post;

    // function __construct( Array $post, $ext = '', $meta = array() )
    // {
    //     $args = wp_parse_args( $post, array(
    //         'post_status'       => apply_filters('ExchangePost__post_status', 'publish'),
    //         'comment_status'    => apply_filters('ExchangePost__comment_status', 'open'),
    //         /**
    //          * @todo watch this!
    //          */
    //         'post_type'         => 'offer',
    //     ) );

    //     if( $ext ) $args['post_mime_type'] = $ext;
    //     if( 0 !== strpos($args['post_mime_type'], 'XML') ) $args['post_mime_type'] = 'XML/' . $args['post_mime_type'];

    //     if( !$args['post_name'] ) {
    //         $args['post_name'] = strtolower($args['post_name']);
    //     }

    //     /**
    //      * @todo generate guid
    //      */

    //     $this->post = new WP_Post( (object) $args );
    //     $this->setMeta($meta);
    // }

    function get_quantity()
    {
        $stock    = $this->getMeta('stock');
        // $quantity = $this->getMeta('quantity');

        // if( is_numeric($stock) && is_numeric($quantity) ) {
        //     $qty = max($stock, $quantity);
        // }
        // elseif( is_numeric($stock) ) {
        //     $qty = $stock;
        // }
        // elseif( is_numeric($quantity) ) {
        //     $qty = $quantity;
        // }

        return $stock;
    }

    function get_stock()
    {
        return $this->get_quantity();
    }

    function set_quantity( $qty )
    {
        $this->setMeta('_manage_stock', 'yes');
        $this->setMeta('_stock_status', $qty ? 'instock' : 'outofstock');
        $this->setMeta('_stock', $qty);
    }

    function set_stock( $qty ) {
        $this->set_quantity();
    }

    function get_price()
    {
        return $this->getMeta('price');
    }

    function set_price( $price )
    {
        $this->setMeta('_price', $price);
        $this->setMeta('_regular_price', $price);
    }

    function merge( $args, $product )
    {
    }
}