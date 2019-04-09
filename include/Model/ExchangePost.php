<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\ORM\ExchangeItemMeta;

/**
 * Works with posts, term_relationships, postmeta
 */
class ExchangePost
{
    use ExchangeItemMeta;

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
                $target = 'properties';
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

    function setRelationship( $context = '', ExchangeTerm $term ) // , ExchangeAttribute $tax = null
    {
        $target = $this->getTarget( $context );
        $this->$target[] = new Relationship( array(
            'external' => $term->getExternal(),
            'id'       => $term->get_id(),
        ) );
    }

    function __construct( Array $post, $ext = '', $meta = array() )
    {
        $args = wp_parse_args( $post, array(
            'post_author'    => get_current_user_id(),
            'post_status'    => apply_filters('ExchangePost__post_status', 'publish'),
            'comment_status' => apply_filters('ExchangePost__comment_status', 'open'),
            'post_type'      => 'product',
            'post_mime_type' => '',
        ) );

        if( $ext ) $args['post_mime_type'] = $ext;
        if( 0 !== strpos($args['post_mime_type'], 'XML') ) $args['post_mime_type'] = 'XML/' . $args['post_mime_type'];

        if( empty($args['post_name']) ) {
            $args['post_name'] = function_exists('mb_strtolower') ? mb_strtolower($args['post_title']) : strtolower($args['post_title']);
        }

        /**
         * @todo generate guid
         */

        $this->post = new \WP_Post( (object) $args );
        $this->setMeta($meta);
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
        $ext = $this->getExternal();
        return substr($ext, strpos($ext, '/'));
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
                'post_mime_type' => $this->post->post_mime_type,
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
        /** @global wpdb wordpress database object */
        global $wpdb, $site_url;

        $site_url = get_site_url();
        $date_now = date('Y-m-d H:i:s');
        $gmdate_now = gmdate('Y-m-d H:i:s');

        /** @var List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /** @var array list of objects exists from posts db */
        $exists = array();

        /** @var $product NikolayS93\Exchange\Model\ProductModel or */
        /** @var $product NikolayS93\Exchange\Model\OfferModel */
        foreach ($products as $rawExternalCode => $product)
        {
            if( !$orphaned_only || ($orphaned_only && !$product->get_id()) ) {
                $externals[] = "`post_mime_type` = '". $product->getExternal() ."'";
            }
        }

        if( !empty($externals) ) {
            //ID, post_date, post_date_gmt, post_name, post_mime_type
            $exists_query = "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = 'product'
                AND (\n". implode(" \t\n OR ", $externals) . "\n)";

            $exists = $wpdb->get_results( $exists_query );

            unset($externals);
        }

        $startExchange = get_option( 'exchange_start-date', '' );
        $intStartExchange = strtotime($startExchange);

        foreach ($exists as $exist)
        {
            /** @var post_mime_type without XML/ */
            $mime = substr($exist->post_mime_type, 4);

            if( $mime && isset($products[ $mime ]->post) ) {
                /** @var stdObject (similar WP_Post) */
                $post = &$products[ $mime ]->post;
                $_post = $products[ $mime ]->post;

                $_post->ID = (int) $exist->ID;

                /**
                 * If is already exists
                 */
                if( !empty($exist->post_name) )    $_post->post_name = (string) $exist->post_name;
                if( !empty($exist->post_title) )   $_post->post_title = (string) $exist->post_title;
                if( !empty($exist->post_content) ) $_post->post_content = (string) $exist->post_content;
                if( !empty($exist->post_excerpt) ) $_post->post_excerpt = (string) $exist->post_excerpt;

                $intPostDate = strtotime($exist->post_date);

                /**
                 * Early created (will be modified)
                 */
                if( $intPostDate && $intPostDate < $intStartExchange ) {
                    $_post->post_date         = (string) $exist->post_date;
                    $_post->post_date_gmt     = (string) $exist->post_date_gmt;
                    $_post->post_modified     = $date_now;
                    $_post->post_modified_gmt = $gmdate_now;
                }

                /**
                 * New post
                 */
                else {
                    $_post->post_date         = $date_now;
                    $_post->post_date_gmt     = $gmdate_now;
                    $_post->post_modified     = $date_now;
                    $_post->post_modified_gmt = $gmdate_now;
                }

                /**
                 * What do you want to keep the same?
                 */
                $post = apply_filters( 'exchange-keep-product', $_post, $post, $exist );
            }
        }
    }
}
