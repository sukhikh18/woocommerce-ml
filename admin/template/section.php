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
        <td>Найдено файлов</td>
        <td><?php foreach ($files as $file) {
            echo basename($file), '<br>';
        } ?></td>
    </tr>
    <tr>
        <td>Кол-во товаров</td>
        <td><?= sizeof($products) ;?> (<?= $newProductsCount ?>)</td>
    </tr>
    <tr>
        <td>Кол-во предложений</td>
        <td><?= sizeof($offers) ;?> (<?= $newOffersCount ?>)</td>
    </tr>
    <?php if( $orphanedProducts ): ?>
    <tr>
        <td style="color: #f00;">Товаров без предложений</td>
        <td style="color: #f00;"><?= $orphanedProducts ?></td>
    </tr>
    <?php endif; ?>
    <?php if( $negativeCount ): ?>
    <tr>
        <td style="color: #f00;">Отрицательные остатки</td>
        <td style="color: #f00;"><?= $negativeCount ?></td>
    </tr>
    <?php endif; ?>
    <?php if( $nullPrice ) : ?>
    <tr>
        <td style="color: #f00;">Предложения с нулевой стоимостью</td>
        <td style="color: #f00;"><?= $negativeCount ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td>Кол-во категорий</td>
        <td><?= sizeof($categories) ;?> (<?= $newCatsCount ?>)</td>
    </tr>
    <tr>
        <td>Кол-во свойств</td>
        <td><?= sizeof($properties) ;?></td>
    </tr>
    <tr>
        <td>Кол-во значений свойств</td>
        <td><?= sizeof($attributeValues); ?></td>
    </tr>
    <tr>
        <td>Кол-во производителей</td>
        <td><?= sizeof($developers) ;?> (<?= $newDevsCount ?>)</td>
    </tr>
    <tr>
        <td>Кол-во складов</td>
        <td><?= sizeof($warehouses) ?></td>
    </tr>
    <tr>
        <td colspan="2" align="center">В скобках указано количество не зарегистрированных объектов</td>
    </tr>
</table>

<!-- <textarea id="ex-report-textarea" style="width: 100%; height: 350px;">
<?php
/*
if( $last = Plugin::get('last_update') ) {
    echo "Последнее обновление: {$last}\n";
}
*/
?>
</textarea> -->
