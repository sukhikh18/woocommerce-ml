<?php

namespace NikolayS93\Exchange\Model;


use CommerceMLParser\Model\Property;
use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\esc_cyr;

/**
 * Works with posts, term_relationships, post meta
 * Content: {
 *     Variables
 *     Utils
 *     Construct
 *     Relatives
 *     CRUD
 * }
 */
class ExchangePost implements Identifiable, ExternalCode {

    use ItemMeta;

    public $warehouses = array();

    /**
     * @var \WP_Post
     * @sql FROM $wpdb->posts
     *      WHERE ID = %d
     */
    private $post;

    static function get_structure( $key ) {
        $structure = array(
            'posts'    => array(
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

        if ( isset( $structure[ $key ] ) ) {
            return $structure[ $key ];
        }

        return false;
    }

    function prepare( $mode = '' ) {
        return true;
    }

    function is_new() {
        $start_date = get_option( 'exchange_start-date', '' );

        if ( $start_date && strtotime( $start_date ) <= strtotime( $this->post->post_date ) ) {
            return true;
        }

        /**
         * 2d secure ;D
         */
        if ( empty( $this->post->post_modified ) || $this->post->post_date == $this->post->post_modified ) {
            return true;
        }

        return false;
    }

    function get_product_meta() {
        $meta = $this->get_meta();

        unset( $meta['_price'], $meta['_regular_price'], $meta['_manage_stock'], $meta['_stock_status'], $meta['_stock'] );

        return $meta;
    }

    /**
     * ExchangePost constructor.
     *
     * @param array $post
     * @param string $ext
     * @param array $meta
     */
    function __construct( Array $post, $ext = '', $meta = array() ) {
        $args = wp_parse_args( $post, array(
            'post_author'    => get_current_user_id(),
            'post_status'    => apply_filters( 'ExchangePost__post_status', 'publish' ),
            'comment_status' => apply_filters( 'ExchangePost__comment_status', 'closed' ),
            'post_type'      => 'product',
            'post_mime_type' => '',
        ) );

        $this->post = new \WP_Post( (object) $args );
        $this->set_external( $ext ? $ext : $args['post_mime_type'] );

        if ( empty( $this->post->post_name ) ) {
            $this->set_slug( $this->post->post_title );
        }

        /**
         * For no offer defaults
         */
        $meta = wp_parse_args( $meta, array(
            '_price'         => 0,
            '_regular_price' => 0,
            '_manage_stock'  => 'no',
            '_stock_status'  => 'outofstock',
            '_stock'         => 0,
        ) );

        /**
         * @todo generate guid
         */
        $this->set_meta( $meta );

        $this->warehouses = new Collection();
    }

    public static function get_external_key() {
        // product no has external meta, he save it in posts on mime_type as XML/external
        return false;
    }

    public function get_external() {
        return $this->post->post_mime_type;
    }

    public function get_raw_external() {
        $ext = $this->get_external();

        if ( 0 === stripos( $ext, 'XML/' ) ) {
            $ext = substr( $ext, 4 );
        }

        return $ext;
    }

    public function set_external( $ext ) {
        if ( 0 !== stripos( $ext, 'XML' ) ) {
            $ext = 'XML/' . $ext;
        }

        $this->post->post_mime_type = (String) $ext;

        return $this;
    }

    public function get_id() {
        return $this->post->ID;
    }

    public function set_id( $value ) {
        $this->post->ID = intval( $value );

        return $this;
    }

    public function get_slug() {
        return $this->post->post_name;
    }

    public function set_slug( $slug ) {
        $this->post->post_name = sanitize_title( esc_cyr( $slug, false ) );

        return $this;
    }

    public function get_author() {
        return $this->post->post_author;
    }

    public function set_author( $author ) {
        $this->post->post_author = $author;
        return $this;
    }

    public function get_title() {
        return $this->post->post_title;
    }

    public function set_title( $title ) {
        $this->post->post_title = $title;

        return $this;
    }

    public function get_content() {
        return $this->post->post_content;
    }

    public function set_content( $content ) {
        $this->post->post_content = $content;

        return $this;
    }

    public function get_excerpt() {
        return $this->post->post_excerpt;
    }

    public function set_excerpt( $excerpt ) {
        $this->post->post_excerpt = $excerpt;

        return $this;
    }

    /**************************************************** Relatives ***************************************************/

    public function get_warehouse( $CollectionItemKey = '' ) {
        $warehouse = $CollectionItemKey ?
            $this->warehouses->offsetGet( $CollectionItemKey ) :
            $this->warehouses->first();

        return $warehouse;
    }

    public function add_warehouse( Warehouse $ExchangeTerm ) {
        return $this->warehouses->add( $ExchangeTerm );
    }

    /****************************************************** CRUD ******************************************************/
    function fetch() {
        return array(
            'posts'    => $this->post,
            'postmeta' => $this->get_meta(),
        );
    }

    public function update() {
        global $wpdb;

        $wpdb->update(
            $wpdb->posts,
            // set
            array(
                'post_status'       => 'publish',
                'post_modified'     => current_time( 'mysql' ),
                'post_modified_gmt' => current_time( 'mysql', 1 )
            ),
            // where
            array(
                'post_mime_type' => $this->get_external(),
            )
        );
    }

    public function deactivate() {
        global $wpdb;

        $wpdb->update(
            $wpdb->posts,
            // set
            array( 'post_status' => 'draft' ),
            // where
            array(
                'post_mime_type' => $this->get_external(),
                'post_status'    => 'publish',
            )
        );
    }

    public function create() {
        $res   = $this->fetch();
        $post  = $res['posts'];
        $_post = $post->to_array();

        // Is date null set now
        // if ( ! (int) preg_replace( '/[^0-9]/', '', $post->post_date ) ) {
        // 	unset( $_post['post_date'] );
        // }

        // if ( ! (int) preg_replace( '/[^0-9]/', '', $post->post_date_gmt ) ) {
        // 	unset( $_post['post_date_gmt'] );
        // }

        return wp_insert_post( $_post );
    }
}
