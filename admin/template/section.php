<?php
namespace NikolayS93\Exchange;

$filename = !empty($_GET['filename']) ? sanitize_text_field( $_GET['filename'] ) : null;

$files  = Parser::getFiles( $filename );
$Parser = Parser::getInstance();
$Parser->__parse($files);
$Parser->__fillExists();

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
    if( !isset($offers[ $product->getRawExternal() ]) ) $orphanedProducts++; // print_r($product);
}

$newOffersCount = 0;
$negativeCount = 0;
foreach ($offers as $offer)
{
    if( !$offer->get_id() ) $newOffersCount++;
    if( $offer->get_quantity() < 0 ) $negativeCount++;
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
        <td><?= sizeof($products) ;?></td>
    </tr>
    <tr>
        <td>Новых товаров</td>
        <td><?= $newProductsCount ?></td>
    </tr>
    <tr>
        <td>Товаров без предложений</td>
        <td><?= $orphanedProducts ?></td>
    </tr>
    <tr>
        <td>Кол-во предложений</td>
        <td><?= sizeof($offers) ;?></td>
    </tr>
    <tr>
        <td>Новых предложений</td>
        <td><?= $newOffersCount ?></td>
    </tr>
    <tr>
        <td>Отрицательные остатки</td>
        <td><?= $negativeCount ?></td>
    </tr>
    <tr>
        <td>Кол-во категорий</td>
        <td><?= sizeof($categories) ;?></td>
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
        <td><?= sizeof($developers) ;?></td>
    </tr>
    <tr>
        <td>Кол-во складов</td>
        <td><?= sizeof($warehouses) ?></td>
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
