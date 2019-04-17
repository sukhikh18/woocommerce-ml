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

    function get_quantity() {
        $stock    = (int) $this->getMeta('stock');
        $quantity = (int) $this->getMeta('quantity');

        $qty = max($stock, $quantity);

        return $qty;
    }

    function get_stock() {
        return $this->get_quantity();
    }
}