<?php

namespace NikolayS93\Exchange;

function the_statistic_table( $files = array() ) {
	if ( ! is_array( $files ) ) {
		$files = array();
	}

	extract( array(
		'products'         => array(),
		'offers'           => array(),
		'categories'       => array(),
		'developers'       => array(),
		'warehouses'       => array(),
		'properties'       => array(),
		'attributeValues'  => array(),
		'newCatsCount'     => 0,
		'newDevsCount'     => 0,
		'newProductsCount' => 0,
		'orphanedProducts' => 0,
		'newOffersCount'   => 0,
		'negativeCount'    => 0,
		'nullPrice'        => 0,
	) );

	$Parser = Parser::get_instance();

	$products = $Parser->get_products();
	$offers   = $Parser->get_offers();
	foreach ( $products as $product ) {
		if ( ! $product->get_id() ) {
			$newProductsCount ++;
		}
		if ( ! isset( $offers[ $product->get_raw_external() ] ) ) {
			$orphanedProducts ++;
		} // print_r($product);
	}

	foreach ( $offers as $offer ) {
		if ( ! $offer->get_id() ) {
			$newOffersCount ++;
		}
		if ( $offer->get_quantity() < 0 ) {
			$negativeCount ++;
		}
		if ( $offer->get_price() < 0 ) {
			$nullPrice ++;
		}
	}

	$categories = $Parser->get_categories();
	foreach ( $categories as $cat ) {
		if ( ! $cat->get_id() ) {
			$newCatsCount ++;
		}
	}

	$properties = $Parser->get_properties();
	foreach ( $properties as $property ) {
		/** Collection to simple array */
		foreach ( $property->get_terms() as $term ) {
			$attributeValues[] = $term;
		}
	}

	$developers = $Parser->get_developers();
	foreach ( $developers as $dev ) {
		if ( ! $dev->get_id() ) {
			$newDevsCount ++;
		}
	}

	$warehouses = $Parser->get_warehouses();
	?>
    <table class="table widefat striped">
        <tr>
            <td><?= __( 'Finded files', DOMAIN ); ?></td>
            <td><?php foreach ( $files as $file ) {
					echo basename( $file ), '<br>';
				} ?></td>
        </tr>
        <tr>
            <td><?= __( 'Products count', DOMAIN ); ?></td>
            <td><?= sizeof( $products ); ?> (<?= $newProductsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __( 'Offers count', DOMAIN ); ?></td>
            <td><?= sizeof( $offers ); ?> (<?= $newOffersCount ?> новых)</td>
        </tr>
		<?php if ( $orphanedProducts ): ?>
            <tr>
                <td style="color: #f00;"><?= __( 'Orphaned products', DOMAIN ); ?></td>
                <td style="color: #f00;"><?= $orphanedProducts ?></td>
            </tr>
		<?php endif; ?>
		<?php if ( $negativeCount ): ?>
            <tr>
                <td style="color: #f00;"><?= __( 'Negative counts', DOMAIN ); ?></td>
                <td style="color: #f00;"><?= $negativeCount ?></td>
            </tr>
		<?php endif; ?>
		<?php if ( $nullPrice ) : ?>
            <tr>
                <td style="color: #f00;"><?= __( 'Null price offers', DOMAIN ); ?></td>
                <td style="color: #f00;"><?= $negativeCount ?></td>
            </tr>
		<?php endif; ?>
        <tr>
            <td><?= __( 'Category count', DOMAIN ); ?></td>
            <td><?= sizeof( $categories ); ?> (<?= $newCatsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __( 'Properties count', DOMAIN ); ?></td>
            <td><?= sizeof( $properties ); ?></td>
        </tr>
        <tr>
            <td><?= __( 'Property\'s value count', DOMAIN ); ?></td>
            <td><?= sizeof( $attributeValues ); ?></td>
        </tr>
        <tr>
            <td><?= __( 'Manufacturers count', DOMAIN ); ?></td>
            <td><?= sizeof( $developers ); ?> (<?= $newDevsCount ?> новых)</td>
        </tr>
        <tr>
            <td><?= __( 'Warehouses count', DOMAIN ); ?></td>
            <td><?= sizeof( $warehouses ) ?></td>
        </tr>
        <tr>
            <td><?= __( 'Last update', DOMAIN ); ?></td>
            <td><?= get_option( 'exchange_last-update' ) ?></td>
        </tr>
    </table>
	<?php
}

function get_post_statistic() {
	$Parser = Parser::get_instance();

	$products = $Parser->get_products();
	$offers   = $Parser->get_offers();

	$html = "\n" . '<pre style="max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;">';
	$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
	$html .= "\n" . '       <h3>Товары</h3>';
	$html .= "\n" . '   ' . print_r( array_slice( $products, 0, 20 ), 1 );
	$html .= "\n" . '   </div>';
	$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
	$html .= "\n" . '       <h3>Предложения</h3>';
	$html .= "\n" . '   ' . print_r( array_slice( $offers, 0, 20 ), 1 );
	$html .= "\n" . '   </div>';
	$html .= "\n" . '</pre>';
	$html .= "\n" . '<div style="clear: both;"></div>';

	return $html;
}

function get_term_statistic() {
	$Parser = Parser::get_instance();

	$categories = $Parser->get_categories();
	$properties = $Parser->get_properties();
	$developers = $Parser->get_developers();
	$warehouses = $Parser->get_warehouses();

	foreach ( $properties as $property ) {
		$property->slice_terms();
	}

	$html = "\n" . '<pre style="max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;">';
	$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
	$html .= "\n" . '       <h3>Склады</h3>';
	$html .= "\n" . '   ' . print_r( array_slice( $warehouses, 0, 2 ), 1 );
	$html .= "\n" . '       <h3>Категории</h3>';
	$html .= "\n" . '   ' . print_r( array_slice( $categories, 0, 2 ), 1 );
	$html .= "\n" . '   </div>';

	$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
	// $html.= "\n" . '       <h3>Производители</h3>';
	// $html.= "\n" . '   ' . print_r( array_slice($developers, 0, 2), 1 );
	$html .= "\n" . '       <h3>Свойства</h3>';
	$html .= "\n" . '   ' . print_r( $properties, 1 );
	$html .= "\n" . '   </div>';
	$html .= "\n" . '</pre>';
	$html .= "\n" . '<div style="clear: both;"></div>';

	return $html;
}

add_action( 'wp_ajax_update_statistic', __NAMESPACE__ . '\ajax_update_statistic' );
function ajax_update_statistic() {
	if ( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], DOMAIN ) ) {
		echo 'Ошибка! нарушены правила безопасности';
		wp_die();
	}

	$filename = ! empty( $_GET['filename'] ) ? sanitize_text_field( $_GET['filename'] ) : null;
	$files    = Parser::get_files( $filename );
	$Parser   = Parser::get_instance();
	$Parser->__parse( $files );
	$Parser->__fill_exists();

	$result = array(
		'table' => '',
		'posts' => get_post_statistic(),
		'terms' => get_term_statistic(),
	);

	ob_start();
	the_statistic_table( $files );
	$result['table'] = ob_get_clean();

	echo json_encode( $result );

	wp_die();
}
