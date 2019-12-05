<?php
/**
 * D. Пошаговая загрузка данных
 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
 *
 * @param Parser $Parser
 * @param Update $update
 *
 * @print 'progress|success|failure'
 */

namespace NikolayS93\Exchange;

if( ! isset( $Parser ) ) {
    $Parser = null;
}

if( ! isset( $update ) ) {
    $update = new Update();
}

$file = get_exchange_file( Request::get_file() );
file_is_readable( $file, true );

$Parser = new Parser();
/** @var \CommerceMLParser\Parser $Dispatcher */
$Dispatcher = Dispatcher();
$Dispatcher->addListener( 'ProductEvent', array( $Parser, 'product_event' ) );
$Dispatcher->addListener( 'OfferEvent', array( $Parser, 'offer_event' ) );
$Dispatcher->addListener( 'CategoryEvent', array( $Parser, 'category_event' ) );
$Dispatcher->addListener( 'WarehouseEvent', array( $Parser, 'warehouse_event' ) );
$Dispatcher->addListener( 'PropertyEvent', array( $Parser, 'property_event' ) );
$Dispatcher->parse( $file );

/** @var CollectionPosts $products */
$products = $Parser->get_products();
/** @var  $offers */
$offers = $Parser->get_offers();
/** @var CollectionTerms $categories */
$categories = $Parser->get_categories();
/** @var CollectionTerms $warehouses */
$warehouses = $Parser->get_warehouses();
/** @var CollectionTerms $attributes */
$attributes = $Parser->get_properties();

Transaction()->set_transaction_mode();

$mode = Request::get_mode();

$categories->fill_exists();
$warehouses->fill_exists();
$attributes->fill_exists();

$update->terms( $categories )->term_meta( $categories );
$update->terms( $warehouses )->term_meta( $warehouses );

echo "<pre>";
print_r( $products );
die();

Request::set_mode( 'import_posts', $update->set_status( 'progress' ) );

$update->stop(
	array(
		"Обновлено {{UPDATE}} категорий/терминов.",
		"Обновлено {{META}} мета записей.",
	)
);



// Unreachable statement in theory.
Request::reset_mode();
$update->stop();

if ( 'import_posts' === $mode || ( ! $categories->count() && ! $warehouses->count() && ! $attributes->count() ) ) {
    if ( $products->count() ) { // update temporary products table
        Transaction()->set_transaction_mode();

        $products
            ->fill_exists()
            ->fill_exists_terms( $Parser );

        $update
            ->update_products( $products )
            ->update_products_meta( $products );

        plugin()->reset_mode();

        $update->stop( array( 'Записаны временные данные товаров' ) );
    }

    $offers_count = $offers->count();
    $offers       = $offers->slice( $update->progress, $update->offset['offer'] );

    if ( $offers_count ) {
        Transaction()->set_transaction_mode();

//              $offers->fill_exists();
        foreach ( $offers as $offer ) {
            $offer->fill_relative_post();
        }

        print_r($offers);
        die();

        // second step: import posts with post meta.
//              if ( 'import_relationships' !== $mode ) {
//                  $update
//                      ->update_offers( $offers )
//                      ->relationships( $offers )
//                      ->update_offers_meta( $offers );
//
//                  if ( $update->progress < $offers_count ) {
//                      // Set mode for retry.
//                      $update->set_status( 'progress' );
//                  } elseif ( 'success' === $update->status ) {
//                      if ( floatval( $this->version ) < 3 ) {
//                          plugin()->set_mode( 'deactivate', $update->set_status( 'progress' ) );
//                      }
//                  }
//
//                  $update->stop(
//                      array(
//                          sprintf(
//                              $this->get_message_by_filename( Request::get_file()['name'] ),
//                              $update->progress,
//                              $offers_count
//                          ),
//                          $update->results['meta'] . ' произвольных записей товаров обновлено.',
//                      )
//                  );
//
//                  $update
//                      ->stop(
//                          printf(
//                              '%d зависимостей %d предложений обновлено (всего %d из %d обработано).',
//                              $update->results['relationships'],
//                              $offers->count(),
//                              $update->progress,
//                              $offers_count
//                          )
//                      );

        } // third step: import posts relationships.

} else { // Update terms




    // $attribute_values = $attributes->get_all_values();
    // $Update
    // ->properties( $attributes )
    // ->terms( $attributeValues )
    // ->term_meta( $attributeValues );
//          if ( $products->count() && floatval( $this->version ) < 3 ) {

//          }


}

//      if ( 'import_posts' === $mode || 'import_relationships' === $mode ) {
//          if ( $offers->count() ) {
//              Transaction()->set_transaction_mode();
//
//              $offers->fill_exists();
//          }
//      }

