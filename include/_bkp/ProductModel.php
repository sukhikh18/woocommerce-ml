<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Update;

/** @todo think about "set namespace" */
class ProductModel extends ItemModel
{
    /**
     * "Product_cat" type wordpress terms
     * @var Array
     */
    public $product_cat;

    /**
     * Raw product properties without link and not term related
     * @var Array
     */
    public $requisites;

    /**
     * Product properties with link by term (has taxonomy/term)
     * @var Array
     */
    public $properties;

    // public $warehouse;


    /**
     * Singleton term. Link to developer (prev. created)
     * @var String
     */
    public $manufacturer;

    /**
     * Product Offers
     */
    // public $offers = array();

    function __construct( $external_id, $post, $type = 'XML' )
    {
        parent::__construct($external_id, $post, $type = 'XML');
    }

    /**
     * [get_id_by_ext description]
     * @param  string $ext [description]
     * @return [type]      [description]
     */
    public static function get_id_by_ext( $ext )
    {
        return false;
    }

    public function set_offer( $offer )
    {
        $this->offers[] = apply_filters( 'EX_Product::set_offer', $offer );
    }

    // public function fetchOffers()
    // {
    //     $offers_count = (int) count($this->offers);

    //     if( 1 > $offers_count ) {
    //         $this->offers = 0;
    //         /**
    //          * @todo return new Exception;
    //          */
    //         return false;
    //     }

    //     $this->offers = (array) apply_filters( 'NikolayS93\Exchange\Model\ProductModel::fetchOffers', $this->offers );
    //     $offers_count = (int) count($this->offers);

    //     if(1 === $offers_count) {
    //         $offer = current($this->offers);

    //         unset( $offer->post );
    //         foreach ( get_object_vars( $offer ) as $key => $value ) {
    //             $this->$key = $value;
    //         }

    //         $this->offers = 1;
    //     }
    //     /**
    //      * @todo fetch variations
    //      */
    //     // is variations
    //     else {
    //     }
    // }

    /***************************************************************************
     * SQL functions
     */
    static function get_structure()
    {
        $structure = array(
            'ID'                    => '%d',
            'post_author'           => '%d',
            'post_date'             => '%s',
            'post_date_gmt'         => '%s',
            'post_content'          => '%s',
            'post_title'            => '%s',
            'post_excerpt'          => '%s',
            'post_status'           => '%s',
            'comment_status'        => '%s',
            'ping_status'           => '%s',
            'post_password'         => '%s',
            'post_name'             => '%s',
            'to_ping'               => '%s',
            'pinged'                => '%s',
            'post_modified'         => '%s',
            'post_modified_gmt'     => '%s',
            'post_content_filtered' => '%s',
            'post_parent'           => '%d',
            'guid'                  => '%s',
            'menu_order'            => '%d',
            'post_type'             => '%s',
            'post_mime_type'        => '%s',
            'comment_count'         => '%d',
        );

        return $structure;
    }

    public function prepare()
    {
        global $user_id, $site_url, $date_now, $gmdate_now;

        if(empty($site_url)) $site_url = get_site_url();
        if(empty($date_now)) $date_now = date('Y-m-d H:i:s');
        if(empty($gmdate_now)) $gmdate_now = gmdate('Y-m-d H:i:s');

        $post_name = Utils::esc_cyr( $this->post->post_title );
        $guid = $site_url . '/product/' . $post_name;

        $arPost = array(
            'ID'                => isset($this->post->ID) ? $this->post->ID : '',
            'post_mime_type'    => $this->get_mime_type(),
            'post_name'         => $post_name,
            'post_author'       => $user_id,
            'post_type'         => 'product',
            'post_date'         => $date_now,
            'post_date_gmt'     => $gmdate_now,
            // 'post_modified'     => $date_now,
            // 'post_modified_gmt' => $gmdate_now,

            'post_title'     => $this->post->post_title,
            'post_excerpt'   => $this->post->post_excerpt,
            'post_content'   => $this->post->post_content,
            'post_parent'    => 0,

            'post_status'           => 'publish',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_password'         => '',
            'to_ping'               => '',
            'pinged'                => '',
            'post_content_filtered' => '',

            'guid'          => $guid,
            'menu_order'    => 0,
            'comment_count' => 0,
        );

        foreach ($arPost as $key => $value) {
            if(empty($this->post->$key)) $this->post->$key = $value;
        }

        $this->post->post_modified     = $date_now;
        $this->post->post_modified_gmt = $gmdate_now;
    }

    function fill( &$insert, &$phs )
    {
        $p = $this->post;
        array_push( $insert, $p->ID, $p->post_author, $p->post_date, $p->post_date_gmt, $p->post_content, $p->post_title, $p->post_excerpt, $p->post_status, $p->comment_status, $p->ping_status, $p->post_password, $p->post_name, $p->to_ping, $p->pinged, $p->post_modified, $p->post_modified_gmt, $p->post_content_filtered, $p->post_parent, $p->guid, $p->menu_order, $p->post_type, $p->post_mime_type, $p->comment_count );

        array_push($phs, Update::get_sql_placeholder( static::get_structure() ) );
    }

    /**
     * @todo think about baybe this right?
     * Update
     */
    function update()
    {
    }

    function update_meta()
    {
    }
}
