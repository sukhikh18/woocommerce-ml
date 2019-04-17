<?php

namespace NikolayS93\Exchange;

?>
<div class='progress'><div class='progress-fill'></div></div>
<div id='ajax_action' style='text-align: center;'></div>
<?php

$time = Utils::getTime();

$Parser = Parser::getInstance( $fillExists = true );

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

// Update::terms( $categories );
// Update::termmeta( $categories );

// Update::terms( $developers );
// Update::termmeta( $developers );

// Update::terms( $warehouses );
// Update::termmeta( $warehouses );

// Update::properties( $properties );
// Update::terms( $attributeValues );
// Update::termmeta( $attributeValues );

// Update::posts( $products );
// Update::postmeta( $products );

// Update::offers( $offers );
// Update::offerPostMetas( $offers );

// Update::relationships( $products );

?>
<pre style='max-width: 1000px;margin: 0 auto;display: flex;'>
    <div style="width: 500px;overflow: auto;float: left;">
        <h3>Товары</h3>
        <?php print_r( $products ); ?>
        <h3>Предложения</h3>
        <?php print_r( $offers ); ?>
    </div>
    <div style="width: 500px;overflow: auto;float: left;">
        <h3>Категории</h3>
        <?php print_r( $categories ); ?>
        <h3>Свойства</h3>
        <?php print_r( $properties ); ?>
        <h3>Производители</h3>
        <?php print_r( $developers ); ?>
        <h3>Склады</h3>
        <?php print_r( $warehouses ); ?>
    </div>
</pre>
<div style="clear: both;"></div>
<?php

echo "Потрачено времени: " . Utils::getTime($time) . " sec.";
