<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Section;
use NikolayS93\Exchange\Parser;

$Page->add_section( new Section(
    'termsinfo',
    __('Terms', DOMAIN),
    function() {
        $Parser = Parser::getInstance();

        $categories = $Parser->getCategories();
        $properties = $Parser->getProperties();
        $developers = $Parser->getDevelopers();
        $warehouses = $Parser->getWarehouses();

        foreach ($properties as $property)
        {
            $property->sliceTerms();
        }

        ?>
        <pre style='max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;'>
            <div style="flex: 1 1 50%;overflow: auto;">
                <h3>Склады</h3><?php print_r( array_slice($warehouses, 0, 2) ); ?>
                <h3>Категории</h3><?php print_r( array_slice($categories, 0, 2) ); ?>
            </div>

            <div style="flex: 1 1 50%;overflow: auto;">
                <h3>Производители</h3><?php print_r( array_slice($developers, 0, 2) ); ?>
                <h3>Свойства</h3><?php print_r( array_slice($properties, 0, 2) ); ?>
            </div>
        </pre>
        <div style="clear: both;"></div>
        <?php
    }
) );




