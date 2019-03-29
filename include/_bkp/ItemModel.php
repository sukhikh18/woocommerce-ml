<?php

namespace NikolayS93\Exchange\Model;

class ItemModel
{
    /**
     * @var WP_Post instance
     */
    public $post;

    /**
     * @var float
     */
    public $price;

    /**
     * Product properties
     */
    public $sku;

    /**
     * [$unit description]
     * @var string
     */
    public $unit;

    /**
     * [$weight description]
     * @var int
     */
    public $weight;

    /**
     * @var Int product's qty summary
     */
    public $quantity;

    /**
     * Taxonomy values
     * @add relationships
     */
    // public $prices;

    public function __construct( $external_id, $post = null, $type = 'XML' )
    {
        $this->post = new \stdClass();

        if( is_object($post) ) {
            foreach (get_object_vars($post) as $key => $value) {
                $this->post->$key = $value;
            }
        }

        $this->post->post_mime_type = strtoupper($type) . '/' . esc_attr($external_id);
    }

    public function get_id( $force = false )
    {
        if( $force && empty( $this->post->ID ) && !empty($this->ext) ) {
            $this->post->ID = $this::get_id_by_ext($this->ext);
        }

        return isset($this->post->ID) ? $this->post->ID : false;
    }

    public function get_mime_type()
    {
        return (string) $this->post->post_mime_type;
    }

    public function get_raw_mime_type()
    {
        return substr((string) $this->post->post_mime_type, 4);
    }

    /**
     * Get post from cache or
     * @param  Integer $post_id
     * @return WP_Post $WP_Post object of the post
     */
    public static function get_post( $post_id = 0 )
    {
        $WP_Post = WP_Post::get_instance( $post_id );

        return $WP_Post;
    }

    // public function get_product_properties()
    // {
    //     $product_properties = array(
    //         'sku'      => $this->sku,
    //         'unit'     => $this->unit,
    //         'price'    => $this->price,
    //         'quantity' => $this->quantity,
    //         'weight'   => $this->weight,
    //     );

    //     return apply_filters('ItemModel::get_product_properties', $product_properties);
    // }

    // public function get_product_tax_values()
    // {
    //     $product_tax_values = array(
    //         'prices' => $this->prices,
    //         'stock_wh' => $this->stock_wh,
    //     );

    //     return apply_filters('ItemModel::get_product_tax_values', $product_tax_values);
    // }

    /**
     * [set_property description]
     * @param string $property_key   [description]
     * @param [type] $property_value [description]
     */
    public function set_property( $property_key, $property_value )
    {
        if( !property_exists($this, $property_key) ) return;

        $this->$property_key = apply_filters( 'EX_Item::set_property',
            sanitize_text_field($property_value), $property_key );
    }

    /**
     * [set_taxonomy description]
     * @param string $taxonomy_name [description]
     * @param array  $terms         [description]
     */
    public function set_taxonomy( $taxonomy_name, $terms )
    {
        if( !property_exists($this, $taxonomy_name) ) return;

        $this->$taxonomy_name = apply_filters( 'EX_Item::set_taxonomy',
            array_filter($terms, 'sanitize_text_field'), $taxonomy_name );
    }

    public function get_taxonomy( $taxonomy_name )
    {
    	if( !property_exists($this, $taxonomy_name) ) return false;

    	return $this->$taxonomy_name;
    }
}
