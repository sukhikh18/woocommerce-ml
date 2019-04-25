<?php

namespace NikolayS93\Exchange;

?>
<div class='progress'><div class='progress-fill'></div></div>
<div id='ajax_action' style='text-align: center;'></div>
<?php

$time = Utils::getTime();

$filename = null;
$fillExists = false;

if( !empty($_GET['filename']) ) {
    $filename = sanitize_text_field( $_GET['filename'] );
    $fillExists = true;
}

$Parser = Parser::getInstance( $filename, $fillExists );

$categories = $Parser->getCategories();
$properties = $Parser->getProperties();
$developers = $Parser->getDevelopers();
$warehouses = $Parser->getWarehouses();

$products = $Parser->getProducts();
$offers = $Parser->getOffers();

$attributeValues = array();
foreach ($properties as $property)
{
    /** Collection to simple array */
    foreach ($property->getTerms() as $term)
    {
        $attributeValues[] = $term;
    }
}

$newProductsCount = 0;
$orphanedProducts = 0;
foreach ($products as $product)
{
    if( !$product->get_id() ) $newProductsCount++;
    if( !isset($offers[ $product->getRawExternal() ]) ) {print_r($product); $orphanedProducts++;}
}

$newOffersCount = 0;
$negativeCount = 0;
foreach ($offers as $offer)
{
    if( !$offer->get_id() ) $newOffersCount++;
    if( $offer->get_quantity() < 0 ) $negativeCount++;
}
// <!-- Без ИД, Без предложений, Всего -->
// <!-- Без ИД, С отриц. остатком, Всего -->
?>
<pre style='max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;'>
    <div style="flex: 2 1 100%;overflow: auto;">Кол-во товаров: <?= $newProductsCount ?>/<?= $orphanedProducts ?>/<?= sizeof($products) ;?>
    Кол-во предложений: <?= $newOffersCount ?>/<?= $negativeCount ?>/<?= sizeof($offers) ;?>
    Кол-во категорий: <?= sizeof($categories) ;?>
    Кол-во свойств: <?= sizeof($properties) ;?> (<?= sizeof($attributeValues); ?>)<?php ?>
    Кол-во производителей: <?= sizeof($developers) ;?>
    Кол-во складов: <?= sizeof($warehouses) ?>
    </div>

    <div style="flex: 1 1 50%;overflow: auto;">
        <h3>Товары</h3><?php print_r( array_slice($products, 0, 20) ); ?>
        <h3>Предложения</h3><?php print_r( array_slice($offers, 0, 20) ); ?>
    </div>

    <div style="flex: 1 1 50%;overflow: auto;">
        <h3>Склады</h3><?php print_r( array_slice($warehouses, 0, 20) ); ?>
        <h3>Категории</h3><?php print_r( array_slice($categories, 0, 20) ); ?>
        <h3>Производители</h3><?php print_r( array_slice($developers, 0, 20) ); ?>
        <h3>Свойства</h3><?php print_r( array_slice($properties, 0, 20) ); ?>
    </div>
</pre>
<div style="clear: both;"></div>
<?php

echo "Потрачено времени: " . Utils::getTime($time) . " sec.";
