<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Section;
use NikolayS93\Exchange\Parser;

$Page->add_section( new Section(
    'postsinfo',
    __('Posts', DOMAIN),
    function() {
        $Parser = Parser::getInstance();

        $products = $Parser->getProducts();
        $offers = $Parser->getOffers();
        ?>
        <pre style='max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;'>
            <div style="flex: 1 1 50%;overflow: auto;">
                <h3>Товары</h3><?php print_r( array_slice($products, 0, 20) ); ?>
            </div>

            <div style="flex: 1 1 50%;overflow: auto;">
                <h3>Предложения</h3><?php print_r( array_slice($offers, 0, 20) ); ?>
            </div>
        </pre>
        <div style="clear: both;"></div>
        <?php
    }
) );
