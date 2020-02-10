<?php
/**
 * Class UpdateTest
 *
 * @package woocommerce-ml
 */

use NikolayS93\Exchange\Update;
use function NikolayS93\Exchange\unzip;

require __DIR__ . '/../../helper.php';

class UpdateTest extends WP_UnitTestCase {

	public function testParseProductsAndOffers() {
		$path_dir = \NikolayS93\Exchange\PLUGIN_DIR . 'tests/fixtures/';
		$path     = $path_dir . 'import0_1.zip';

		unzip( $path, $path_dir );

		$Parser = new \NikolayS93\Exchange\Parser( $path );
		$Parser
			->listen_product()
			->listen_offer()
			->parse();

		$products = $Parser->get_products();
		$offers   = $Parser->get_offers();

		$this->assertTrue( is_a( $products, '\NikolayS93\Exchange\ORM\Collection' ) );
		$this->assertIsArray( is_a( $offers, '\NikolayS93\Exchange\ORM\Collection' ) );
	}
}
