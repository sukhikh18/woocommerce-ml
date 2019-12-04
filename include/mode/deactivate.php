<?php
/**
 * E. Деактивация данных
 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=deactivate
 *
 * @print 'progress|success|failure'
 * @note We need always update post_modified for true deactivate
 * @since  3.0
 */

namespace NikolayS93\Exchange;

global $wpdb;

$start_date = get_option( 'exchange_start-date', false );

/**
 * Чистим и пересчитываем количество записей в терминах
 */
if ( ! $start_date ) {
    return;
}

$Plugin = Plugin();
/**
 * move .xml files from exchange folder
 */
$path_dir = $Plugin->get_exchange_dir();
$files    = $Plugin->get_exchange_files();

if ( ! empty( $files ) ) {
    reset( $files );

    /**
     * Meta data from any finded file
     *
     * @var array { version: float, is_full: bool }
     */
    $summary = Plugin::get_summary_meta( current( $files ) );

    /**
     * Need deactivate deposits products
     * $summary['version'] < 3 && $version < 3 &&
     */
    if ( true === $summary['is_full'] ) {
        $post_lost = Plugin::get( 'post_lost' );

        if ( ! $post_lost ) {
            // $postmeta['_stock'] = 0; // required?
            $wpdb->query(
                "
                UPDATE $wpdb->postmeta pm SET pm.meta_value = 'outofstock'
                WHERE pm.meta_key = '_stock_status' AND pm.post_id IN (
                          SELECT p.ID FROM $wpdb->posts p
                          WHERE p.post_type = 'product'
                                AND p.post_modified < '$start_date'
                ) "
            );
        } elseif ( 'delete' == $post_lost ) {
            // delete query
        }
    }
}

/**
 * Set pending status when post no has price meta
 * Most probably no has offer (or code error in last versions)
 *
 * @var array $notExistsPrice List of objects
 */
$notExistsPrice = $wpdb->get_results(
    "
        SELECT p.ID, p.post_type, p.post_status
        FROM $wpdb->posts p
        WHERE
            p.post_type = 'product'
            AND p.post_status = 'publish'
            AND p.post_modified > '$start_date'
            AND NOT EXISTS (
                SELECT pm.post_id, pm.meta_key FROM $wpdb->postmeta pm
                WHERE p.ID = pm.post_id AND pm.meta_key = '_price'
            )
    "
);

// Collect Ids
$notExistsPriceIDs = array_map( 'intval', wp_list_pluck( $notExistsPrice, 'ID' ) );

/**
 * Set pending status when post has a less price meta (null value)
 *
 * @var array $nullPrice List of objects
 */
$nullPrice = $wpdb->get_results(
    "
        SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
        FROM $wpdb->postmeta pm
        INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
        WHERE   p.post_type   = 'product'
            AND p.post_status = 'publish'
            AND p.post_modified > '$start_date'
            AND pm.meta_key = '_price'
            AND pm.meta_value = 0
    "
);

// Collect Ids
$nullPriceIDs = array_map( 'intval', wp_list_pluck( $nullPrice, 'post_id' ) );

// Merge Ids
$deactivateIDs = array_unique( array_merge( $notExistsPriceIDs, $nullPriceIDs ) );

$price_lost = Plugin::get( 'price_lost' );

/**
 * Deactivate
 */
if ( ! $price_lost && sizeof( $deactivateIDs ) ) {
    /**
     * Execute query (change post status to pending)
     */
    $wpdb->query(
        "UPDATE $wpdb->posts SET post_status = 'pending'
            WHERE ID IN (" . implode( ',', $deactivateIDs ) . ')'
    );
} elseif ( 'delete' == $price_lost ) {
    // delete query
}

/**
 * @todo how define time rengу one exhange (if exchange mode complete clean date before new part of offers)
 * Return post status if product has a better price (only new)
 */
// $betterPrice = $wpdb->get_results( "
// SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
// FROM $wpdb->postmeta pm
// INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
// WHERE   p.post_type   = 'product'
// AND p.post_status = 'pending'
// AND p.post_modified = p.post_date
// AND pm.meta_key = '_price'
// AND pm.meta_value > 0
// " );

// // Collect Ids
// $betterPriceIDs = array_map('intval', wp_list_pluck( $betterPrice, 'ID' ));

// if( sizeof($betterPriceIDs) ) {
// $wpdb->query(
// "UPDATE $wpdb->posts SET post_status = 'publish'
// WHERE ID IN (". implode(',', $betterPriceIDs) .")"
// );
// }

$msg = 'Деактивация товаров завершена';

if ( floatval( $this->version ) < 3 ) {
    plugin()->set_mode( 'complete', new Update() );
    exit( "progress\n$msg" );
}

exit( "success\n$msg" );