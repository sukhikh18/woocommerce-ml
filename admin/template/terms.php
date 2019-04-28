<?php

namespace NikolayS93\Exchange;

$Parser = Parser::getInstance();

$categories = $Parser->getCategories();
$properties = $Parser->getProperties();
$developers = $Parser->getDevelopers();
$warehouses = $Parser->getWarehouses();
?>
<pre style='max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;'>
    <div style="flex: 1 1 50%;overflow: auto;">
        <h3>Склады</h3><?php print_r( array_slice($warehouses, 0, 20) ); ?>
        <h3>Категории</h3><?php print_r( array_slice($categories, 0, 20) ); ?>
    </div>

    <div style="flex: 1 1 50%;overflow: auto;">
        <h3>Производители</h3><?php print_r( array_slice($developers, 0, 20) ); ?>
        <h3>Свойства</h3><?php print_r( array_slice($properties, 0, 20) ); ?>
    </div>
</pre>
<div style="clear: both;"></div>
