<?php

namespace NikolayS93\Exchanger;

if( !function_exists(__NAMESPACE__ . '\the_statistic_table') ) {
	function the_statistic_table( Parser $Parser ) {
		$newCatsCount = 0;
		$newProductsCount = 0;
		$orphanedProducts = 0;
		$newOffersCount = 0;
		$negativeCount = 0;
		$nullPrice = 0;

		$products = $Parser->get_products();
		$offers   = $Parser->get_offers();

		foreach ( $products as $product ) {
			if ( ! $product->get_id() ) {
				$newProductsCount ++;
			}
			if ( ! isset( $offers[ $product->get_raw_external() ] ) ) {
				$orphanedProducts ++;
			}
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
		$attributeValues = array();
		foreach ( $properties as $property ) {
			foreach ( $property->get_values() as $term ) {
				$attributeValues[] = $term;
			}
		}

		$warehouses = $Parser->get_warehouses();
		?>
		<table class="table widefat striped">
			<tr>
				<td><?= __( 'Finded files', Plugin::DOMAIN ); ?></td>
				<td><?= implode('<br>', $Parser->get_filenames()) ?></td>
			</tr>
			<tr>
				<td><?= __( 'Products count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $products ); ?> (<?= $newProductsCount ?>
					<?= _n( 'new', 'new', $newProductsCount, Plugin::DOMAIN ) ?>)</td>
			</tr>
			<tr>
				<td><?= __( 'Offers count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $offers ); ?> (<?= $newOffersCount ?>
					<?= _n( 'new', 'new', $newOffersCount, Plugin::DOMAIN ) ?>)</td>
			</tr>
			<?php if ( $orphanedProducts ): ?>
				<tr>
					<td style="color: #f00;"><?= __( 'Orphaned products', Plugin::DOMAIN ); ?></td>
					<td style="color: #f00;"><?= $orphanedProducts ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( $negativeCount ): ?>
				<tr>
					<td style="color: #f00;"><?= __( 'Negative counts', Plugin::DOMAIN ); ?></td>
					<td style="color: #f00;"><?= $negativeCount ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( $nullPrice ) : ?>
				<tr>
					<td style="color: #f00;"><?= __( 'Null price offers', Plugin::DOMAIN ); ?></td>
					<td style="color: #f00;"><?= $negativeCount ?></td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><?= __( 'Category count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $categories ); ?> (<?= $newCatsCount ?>
					<?= _n( 'new', 'new', $newCatsCount, Plugin::DOMAIN ) ?>)</td>
			</tr>
			<tr>
				<td><?= __( 'Properties count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $properties ); ?></td>
			</tr>
			<tr>
				<td><?= __( 'Property\'s value count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $attributeValues ); ?></td>
			</tr>
			<tr>
				<td><?= __( 'Warehouses count', Plugin::DOMAIN ); ?></td>
				<td><?= sizeof( $warehouses ) ?></td>
			</tr>
			<tr>
				<td><?= __( 'Last update', Plugin::DOMAIN ); ?></td>
				<td><?= get_option( 'exchange_last-update' ) ?></td>
			</tr>
		</table>
		<?php
	}
}

if( !function_exists(__NAMESPACE__ . '\get_post_statistic') ) {
	function get_post_statistic( Parser $Parser ) {
		$products = $Parser->get_products();
		$offers   = $Parser->get_offers();

		$html = "\n" . '<pre style="max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;">';
		$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
		$html .= "\n" . '       <h3>Товары</h3>';
		$html .= "\n" . '   ' . print_r( array_slice( $products->fetch(), 0, 20 ), 1 );
		$html .= "\n" . '   </div>';
		$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
		$html .= "\n" . '       <h3>Предложения</h3>';
		$html .= "\n" . '   ' . print_r( array_slice( $offers->fetch(), 0, 20 ), 1 );
		$html .= "\n" . '   </div>';
		$html .= "\n" . '</pre>';
		$html .= "\n" . '<div style="clear: both;"></div>';

		return $html;
	}
}

if( !function_exists('get_term_statistic') ) {
	function get_term_statistic( Parser $Parser ) {
		$categories = $Parser->get_categories();
		$properties = $Parser->get_properties();
		$warehouses = $Parser->get_warehouses();

//		foreach ( $properties as $property ) {
//			$property->sliceTerms();
//		}

		$html = "\n" . '<pre style="max-width: 1400px;margin: 0 auto;display: flex;flex-wrap: wrap;">';
		$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
		$html .= "\n" . '       <h3>Склады</h3>';
		$html .= "\n" . '   ' . print_r( array_slice( $warehouses->fetch(), 0, 2 ), 1 );
		$html .= "\n" . '       <h3>Категории</h3>';
		$html .= "\n" . '   ' . print_r( array_slice( $categories->fetch(), 0, 2 ), 1 );
		$html .= "\n" . '   </div>';

		$html .= "\n" . '   <div style="flex: 1 1 50%;overflow: auto;">';
		$html .= "\n" . '       <h3>Свойства</h3>';
		$html .= "\n" . '   ' . print_r( $properties, 1 );
		$html .= "\n" . '   </div>';
		$html .= "\n" . '</pre>';
		$html .= "\n" . '<div style="clear: both;"></div>';

		return $html;
	}
}

add_action( 'wp_ajax_update_statistic', __NAMESPACE__ . '\ajax_update_statistic' );
function ajax_update_statistic() {
	if ( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], Plugin::DOMAIN ) ) {
		echo 'Ошибка! нарушены правила безопасности';
		wp_die();
	}

	$filename = null;
	if( ! empty( $_GET['filename'] ) ) {
		$filename = is_array( $_GET['filename'] ) ? array_filter( $_GET['filename'], 'sanitize_text_field' ) :
			sanitize_text_field( $_GET['filename'] );
	}

	$Parser = new Parser();

	$Dispatcher = Dispatcher();
	$Dispatcher
		->addListener( "ProductEvent", array( $Parser, 'product_event' ) )
		->addListener( "OfferEvent", array( $Parser, 'offer_event' ) )
		->addListener( "CategoryEvent", array( $Parser, 'category_event' ) )
		->addListener( "WarehouseEvent", array( $Parser, 'warehouse_event' ) )
		->addListener( "PropertyEvent", array( $Parser, 'property_event' ) );

	if( is_array( $filename ) ) {
		foreach ($filename as $file) {
			$Dispatcher->parse( $file );
		}
	}
	else {
		$Dispatcher->parse( $filename );
	}

	$result = array(
		'table' => '',
		'posts' => get_post_statistic( $Parser ),
		'terms' => get_term_statistic( $Parser ),
	);

	ob_start();
	the_statistic_table( $Parser );
	$result['table'] = ob_get_clean();

	echo json_encode( $result );

	wp_die();
}
