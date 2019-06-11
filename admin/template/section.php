<?php
namespace NikolayS93\Exchange;

$filename = !empty($_GET['filename']) ? sanitize_text_field( $_GET['filename'] ) : null;

$files  = Parser::getFiles( $filename );
$Parser = Parser::getInstance();
$Parser->__parse($files);
$Parser->__fillExists();

$categories = $Parser->getCategories();

$newCatsCount = 0;
foreach ($categories as $cat)
{
    if( !$cat->get_id() ) $newCatsCount++;
}

$properties = $Parser->getProperties();
$attributeValues = array();
foreach ($properties as $property)
{
    /** Collection to simple array */
    foreach ($property->getTerms() as $term)
    {
        $attributeValues[] = $term;
    }
}

$developers = $Parser->getDevelopers();
$newDevsCount = 0;
foreach ($developers as $dev)
{
    if( !$dev->get_id() ) $newDevsCount++;
}


$warehouses = $Parser->getWarehouses();

$products = $Parser->getProducts();
$offers = $Parser->getOffers();



$newProductsCount = 0;
$orphanedProducts = 0;
foreach ($products as $product)
{
    if( !$product->get_id() ) $newProductsCount++;
    if( !isset($offers[ $product->getRawExternal() ]) ) $orphanedProducts++; // print_r($product);
}



$newOffersCount = 0;
$negativeCount = 0;
$nullPrice = 0;
foreach ($offers as $offer)
{
    if( !$offer->get_id() ) $newOffersCount++;
    if( $offer->get_quantity() < 0 ) $negativeCount++;
    if( $offer->get_price() < 0 ) $nullPrice++;
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
