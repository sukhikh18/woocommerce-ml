<?php

interface Update {
    function get_status();
    function get_progress();
    function set_status( $status );
    function set_progress( $progress );
    function reset_progress();
    // @todo Update commands.
    public function update_meta( $post_id, $property_key, $property );
    public function update_products( $products );
    public function update_products_step( $product );
    public function update_products_meta( $products );
    public function update_offers( $offers );
    public function update_offers_meta( $offers );
    public function terms( $termsCollection );
    public function term_meta( $terms );
    public function relationships( CollectionPosts $posts, $args = array() );

    public static function update_term_counts();
}
