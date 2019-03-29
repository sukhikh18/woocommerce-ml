<?php

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

    static function get_structure()
    {
        $structure = array('posts' => array(
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
        ));

        return $structure;
    }

    static function get_metastructure()
    {
        $structure = array('postmeta' => array(
            'meta_id'    => '%d',
            'post_id'    => '%d',
            'meta_key'   => '%s',
            'meta_value' => '%s',
        ));

        return $structure;
    }

    function __construct( Array $post, $ext = '', $meta = array() )
    {
        $args = wp_parse_args( $post, array(
            'post_author'    => get_current_user_id(),
            'post_status'    => apply_filters('ExchangePost__post_status', 'publish'),
            'comment_status' => apply_filters('ExchangePost__comment_status', 'open'),
            'post_type'      => 'product',
        ) );

        if( $ext ) $args['post_mime_type'] = $ext;
        if( 0 !== strpos($args['post_mime_type'], 'XML') ) $args['post_mime_type'] = 'XML/' . $args['post_mime_type'];

        if( !$args['post_name'] ) {
            $args['post_name'] = strtolower($args['post_name']);
        }

        /**
         * @todo generate guid
         */

        $this->post = new WP_Post( (object) $args );
        $this->setMeta($meta);
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
        return substr($this->get_external(), 4);
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
}

class ExchangeProduct extends ExchangePost
{
}

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
}