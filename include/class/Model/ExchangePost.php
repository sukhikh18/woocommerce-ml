<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\ORM\ExchangeItemMeta;
use NikolayS93\Exchange\Plugin;

/**
 * Works with posts, term_relationships, postmeta
 */
class ExchangePost
{
    use ExchangeItemMeta;

    public $warehouse = array();

    /**
     * @var WP_Post
     * @sql FROM $wpdb->posts
     *      WHERE ID = %d
     */
    private $post;

    static function get_structure( $key )
    {
        $structure = array(
            'posts' => array(
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
            ),
            'postmeta' => array(
                'meta_id'    => '%d',
                'post_id'    => '%d',
                'meta_key'   => '%s',
                'meta_value' => '%s',
            )
        );

        if( isset($structure[ $key ]) ) {
            return $structure[ $key ];
        }

        return false;
    }

    function getTarget( $context )
    {
        $target = null;

        switch ($context) {
            case 'properties':
            case 'arProperties':
            case 'property':
                $target = 'properties';
                break;

            case 'warehouse':
            case 'warehouses':
            case 'arWarehouses':
                $target = 'warehouse';
                break;

            case 'developer':
            case 'developers':
            case 'arDevelopers':
                $target = 'developer';
                break;

            case 'product_cat':
            case 'products_cat':
            case 'product_cats':
            default:
                $target = 'product_cat';
                break;
        }

        return $target;
    }

    function getAllRelativeExternals( $orphaned_only = false )
    {
        $arExternals = array();
        $arRelationships = array();

        if( !empty( $this->product_cat ) ) {
            $arRelationships = array_merge($arRelationships, $this->product_cat);
        }

        if( !empty( $this->warehouse ) ) {
            $arRelationships = array_merge($arRelationships, $this->warehouse);
        }

        if( !empty( $this->developer ) ) {
            $arRelationships = array_merge($arRelationships, $this->developer);
        }

        if( !empty( $this->properties ) ) {
            $arRelationships = array_merge($arRelationships, $this->properties);
        }

        foreach ($arRelationships as $arRelationship)
        {
            if( $orphaned_only && $arRelationship->get_id() ) {
                continue;
            }

            $arExternals[] = $arRelationship->getExternal();
        }

        return $arExternals;
    }

    function fillRelatives()
    {
        /** @global wpdb $wpdb built in wordpress db object */
        global $wpdb;

        $arExternals = $this->getAllRelativeExternals();
        foreach ($arExternals as $strExternal)
        {
            $arSqlExternals[] = "`meta_value` = '{$strExternal}'";
        }

        $ardbTerms = array();
        if( !empty($arSqlExternals) ) {
            $exsists_terms_query = "
                SELECT term_id, meta_key, meta_value
                FROM $wpdb->termmeta
                WHERE meta_key = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", array_unique($arSqlExternals)) . ")";

            $ardbTerms = $wpdb->get_results( $exsists_terms_query );

            $arTerms = array();
            foreach ($ardbTerms as $ardbTerm) {
                $arTerms[ $ardbTerm->meta_value ] = $ardbTerm->term_id;
            }
        }

        if( !empty($this->product_cat) ) {
            foreach ($this->product_cat as &$product_cat)
            {
                $ext = $product_cat->getExternal();
                if( !empty( $arTerms[ $ext ] ) ) $product_cat->setValue( $arTerms[ $ext ] );
            }
        }
        if( !empty($this->warehouse) ) {
            foreach ($this->warehouse as &$warehouse)
            {
                $ext = $warehouse->getExternal();
                if( !empty( $arTerms[ $ext ] ) ) $warehouse->setValue( $arTerms[ $ext ] );
            }
        }
        if( !empty($this->developer) ) {
            foreach ($this->developer as &$developer)
            {
                $ext = $developer->getExternal();
                if( !empty( $arTerms[ $ext ] ) ) $developer->setValue( $arTerms[ $ext ] );
            }
        }
        if( !empty($this->properties) ) {
            foreach ($this->properties as &$property)
            {
                $ext = $property->getExternal();
                if( !empty( $arTerms[ $ext ] ) ) $property->setValue( $arTerms[ $ext ] );
            }
        }
    }

    function setRelationship( $context = '', $term )
    {
        $target = $this->getTarget( $context );

        if( $term instanceOf ExchangeTerm ) {
            array_push($this->$target, new Relationship( array(
                'external' => $term->getExternal(),
                'value'    => $term->get_id(),
                'taxonomy' => $term->getTaxonomy(),
            ) ));
        }
        elseif( is_array($term) ) {
            array_push($this->$target, new Relationship( $term ));
        }
    }

    function __construct( Array $post, $ext = '', $meta = array() )
    {
        $args = wp_parse_args( $post, array(
            'post_author'       => get_current_user_id(),
            'post_status'       => apply_filters('ExchangePost__post_status', 'publish'),
            'comment_status'    => apply_filters('ExchangePost__comment_status', 'closed'),
            'post_type'         => 'product',
            'post_mime_type'    => '',
        ) );

        if( empty($args['post_name']) ) {
            $args['post_name'] = sanitize_title( \NikolayS93\Exchange\esc_cyr($args['post_title'], false) );
        }

        /**
         * For no offer defaults
         */
        $meta = wp_parse_args( $meta, array(
            '_price' => 0,
            '_regular_price' => 0,
            '_manage_stock' => 'no',
            '_stock_status' => 'outofstock',
            '_stock' => 0,
        ) );

        /**
         * @todo generate guid
         */

        $this->post = new \WP_Post( (object) $args );
        $this->setMeta($meta);
        $this->setExternal($ext ? $ext : $args['post_mime_type']);
    }

    function isNew()
    {
        $start_date = get_option( 'exchange_start-date', '' );

        if( $start_date && strtotime($start_date) <= strtotime($this->post->post_date) ) {
            return true;
        }

        /**
         * 2d secure ;D
         */
        if( empty($this->post->post_modified) || $this->post->post_date == $this->post->post_modified ) {
            return true;
        }

        return false;
    }

    function set_id( $value )
    {
        $this->post->ID = intval($value);
    }

    function get_id()
    {
        return $this->post->ID;
    }

    function prepare()
    {
    }

    function getObject()
    {
        return $this->post;
    }

    public function getExternal()
    {
        return $this->post->post_mime_type;
    }

    public function getRawExternal()
    {
        @list(, $ext) = explode('/', $this->getExternal());
        return $ext;
    }

    public function setExternal( $ext )
    {
        if( 0 !== strpos($ext, 'XML') ) {
            $ext = 'XML/' . $ext;
        }

        $this->post->post_mime_type = (String) $ext;
    }

    public function deactivate()
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->posts,
            // set
            array( 'post_status' => 'draft' ),
            // where
            array(
                'post_mime_type' => $this->getExternal(),
                'post_status'    => 'publish',
            )
        );
    }

    /**
     * [fillExistsProductData description]
     * @param  array  &$products      products or offers collections
     * @param  boolean $orphaned_only [description]
     * @return [type]                 [description]
     */
    static public function fillExistsFromDB( &$products, $orphaned_only = false )
    {
        // $startExchange = get_option( 'exchange_start-date', '' );
        // $intStartExchange = strtotime($startExchange);

        /** @global wpdb wordpress database object */
        global $wpdb;

        /** @var List of external code items list in database attribute context (%s='%s') */
        $post_mime_types = array();

        /** @var array list of objects exists from posts db */
        $exists = array();

        /** @var $product NikolayS93\Exchange\Model\ProductModel or */
        /** @var $product NikolayS93\Exchange\Model\OfferModel */
        /**
         * EXPLODE FOR SIMPLE ONLY
         * @todo
         */
        foreach ($products as $rawExternalCode => $product)
        {
            if( !$orphaned_only || ($orphaned_only && !$product->get_id()) ) {
                list($product_ext) = explode('#', $product->getExternal());
                $post_mime_types[] = "`post_mime_type` = '". esc_sql( $product_ext ) ."'";
            }
        }

        if( $post_mime_type = implode(" \t\n OR ", $post_mime_types) ) {
            // ID, post_author, post_date, post_title, post_content, post_excerpt, post_date_gmt, post_name, post_mime_type - required
            $exists = $wpdb->get_results( "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = 'product'
                AND (\n\t\n $post_mime_type \n)" );
        }

        foreach ($exists as $exist)
        {
            /** @var $mime post_mime_type without XML/ */
            if( ($mime = substr($exist->post_mime_type, 4)) && isset($products[ $mime ]->post) ) {

                /** Skip if selected (unset new data field from array (@care)) */
                // if( $post_name = Plugin::get('post_name') )         unset( $exist->post_name );
                if( !Plugin::get('skip_post_author') )  unset( $exist->post_author );
                if( !Plugin::get('skip_post_title') )   unset( $exist->post_title );
                if( !Plugin::get('skip_post_content') ) unset( $exist->post_content );
                if( !Plugin::get('skip_post_excerpt') ) unset( $exist->post_excerpt );

                foreach (get_object_vars( $exist ) as $key => $value)
                {
                    $products[ $mime ]->post->$key = $value;
                }
            }
        }
    }

    function getProductMeta()
    {
        $meta = $this->getMeta();

        unset( $meta['_price'], $meta['_regular_price'], $meta['_manage_stock'], $meta['_stock_status'], $meta['_stock'] );
        return $meta;
    }
}
