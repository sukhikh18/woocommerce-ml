<?php

namespace NikolayS93\Exchange;

?>
<div class='progress'><div class='progress-fill'></div></div>
<div id='ajax_action' style='text-align: center;'></div>
<?php

$time = getTime();

$categories = Parser::getInstance()->getCategories();
$properties = Parser::getInstance()->getProperties();
$manufacturer = Parser::getInstance()->getManufacturer();
$warehouses = Parser::getInstance()->getWarehouses();
$products = Parser::getInstance()->getProducts();
$offers = Parser::getInstance()->getOffers();

?>
<pre style='max-width: 900px;margin: 0 auto;display: flex;'>
    <div style="width: 450px;overflow: auto;float: left;">
        <h3>Товары</h3>
        <?php print_r( $products ); ?>
        <h3>Предложения</h3>
        <?php print_r( $offers ); ?>
    </div>
    <div style="width: 450px;overflow: auto;float: left;">
        <h3>Категории</h3>
        <?php print_r( $categories ); ?>
        <h3>Свойства</h3>
        <?php print_r( $properties ); ?>
        <h3>Производители</h3>
        <?php print_r( $manufacturer ); ?>
        <h3>Склады</h3>
        <?php print_r( $warehouses ); ?>
    </div>
</pre>
<div style="clear: both;"></div>
<?php

echo "Потрачено времени: " . getTime($time) . " sec.";
