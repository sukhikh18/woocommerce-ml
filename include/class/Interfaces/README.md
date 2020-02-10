```php

$Register
    ->register_plugin_page()
    ->register_exchange_url();

$WP_REST_Controller
    ->exchange()
        ->checkauth()

        ->init()

        ->file( $requested = 'php://input' )

        ->query()

        ->import( $Parser = null, $Update = null, function() {
            $Parser
                ->get_categories()
                ->get_warehouses()
                ->get_properties()
                ->get_products()
                ->get_offers();

            // Step 1.
            $Update->terms( $categories )->term_meta( $categories );
            $Update->terms( $warehouses )->term_meta( $warehouses );
            // Step 2.
            $product
                ->fill_exists()
                ->fill_exists_terms()
                ->write_temporary_data();
            // Step in another query.
            $offer
                ->fill_exists_terms()
                ->merge()
                ->write_temporary_data();
        } )

        ->deactivate()

        ->complete( function() {
            $Update
                ->update_meta( $post_id, $property_key, $property )
                ->update_products( $products )
                ->update_products_step( $product )
                ->update_products_meta( $products )
                ->update_offers( $offers )
                ->update_offers_meta( $offers )
                ->terms( $termsCollection )
                ->term_meta( $terms )
                ->relationships( $posts, $args = array() );
        } );
```
