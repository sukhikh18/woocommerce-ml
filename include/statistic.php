<?php

namespace NikolayS93\Exchange;

function statisticTable( $empty = false ) {
    $files = array();
    $products = array();
    $offers = array();
    $categories = array();
    $developers = array();
    $warehouses = array();
    $properties = array();
    $attributeValues = array();

    $newCatsCount = 0;
    $newDevsCount = 0;
    $newProductsCount = 0;
    $orphanedProducts = 0;
    $newOffersCount = 0;
    $negativeCount = 0;
    $nullPrice = 0;

    if( !$empty ) {
        $filename = !empty($_GET['filename']) ? sanitize_text_field( $_GET['filename'] ) : null;

        $files  = Parser::getFiles( $filename );
        $Parser = Parser::getInstance();
        $Parser->__parse($files);
        $Parser->__fillExists();

        $products = $Parser->getProducts();
        $offers = $Parser->getOffers();
        foreach ($products as $product)
        {
            if( !$product->get_id() ) $newProductsCount++;
            if( !isset($offers[ $product->getRawExternal() ]) ) $orphanedProducts++; // print_r($product);
        }

        foreach ($offers as $offer)
        {
            if( !$offer->get_id() ) $newOffersCount++;
            if( $offer->get_quantity() < 0 ) $negativeCount++;
            if( $offer->get_price() < 0 ) $nullPrice++;
        }

        $categories = $Parser->getCategories();
        foreach ($categories as $cat)
        {
            if( !$cat->get_id() ) $newCatsCount++;
        }

        $properties = $Parser->getProperties();
        foreach ($properties as $property)
        {
            /** Collection to simple array */
            foreach ($property->getTerms() as $term)
            {
                $attributeValues[] = $term;
            }
        }

        $developers = $Parser->getDevelopers();
        foreach ($developers as $dev)
        {
            if( !$dev->get_id() ) $newDevsCount++;
        }

        $warehouses = $Parser->getWarehouses();
    }
    ?>
    <table class="table widefat striped">
        <tr>
            <td><?= __('Finded files', DOMAIN); ?></td>
            <td><?php foreach ($files as $file) {
                echo basename($file), '<br>';
            } ?></td>
        </tr>
        <tr>
            <td><?= __('Products count', DOMAIN); ?></td>
            <td><?= sizeof($products) ;?> (<?= $newProductsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __('Offers count', DOMAIN); ?></td>
            <td><?= sizeof($offers) ;?> (<?= $newOffersCount ?> новых)</td>
        </tr>
        <?php if( $orphanedProducts ): ?>
            <tr>
                <td style="color: #f00;"><?= __('Orphaned products', DOMAIN); ?></td>
                <td style="color: #f00;"><?= $orphanedProducts ?></td>
            </tr>
        <?php endif; ?>
        <?php if( $negativeCount ): ?>
            <tr>
                <td style="color: #f00;"><?= __('Negative counts', DOMAIN); ?></td>
                <td style="color: #f00;"><?= $negativeCount ?></td>
            </tr>
        <?php endif; ?>
        <?php if( $nullPrice ) : ?>
            <tr>
                <td style="color: #f00;"><?= __('Null price offers', DOMAIN); ?></td>
                <td style="color: #f00;"><?= $negativeCount ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td><?= __('Category count', DOMAIN); ?></td>
            <td><?= sizeof($categories) ;?> (<?= $newCatsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __('Properties count', DOMAIN); ?></td>
            <td><?= sizeof($properties) ;?></td>
        </tr>
        <tr>
            <td><?= __('Property\'s value count', DOMAIN); ?></td>
            <td><?= sizeof($attributeValues); ?></td>
        </tr>
        <tr>
            <td><?= __('Manufacturers count', DOMAIN); ?></td>
            <td><?= sizeof($developers) ;?> (<?= $newDevsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __('Warehouses count', DOMAIN); ?></td>
            <td><?= sizeof($warehouses) ?></td>
        </tr>
        <tr>
            <td><?= __('Last update', DOMAIN); ?></td>
            <td><?= get_option('exchange_last-update') ?></td>
        </tr>
    </table>
    <?php
}

add_action('wp_ajax_statistic_table', __NAMESPACE__ . '\ajax_statistic_table');
function ajax_statistic_table() {
    if( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], DOMAIN ) ) {
        echo 'Ошибка! нарушены правила безопасности';
        wp_die();
    }

    statisticTable();
    wp_die();
}