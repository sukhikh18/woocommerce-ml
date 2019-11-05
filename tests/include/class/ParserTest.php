<?php
/**
 * Class UpdateTest
 *
 * @package Woocommerce.1c.Exchanger
 */

use NikolayS93\Exchanger\Update;
use function NikolayS93\Exchanger\unzip;

require __DIR__ . '/../../helper.php';

class UpdateTest extends WP_UnitTestCase {

	public function testParseProductsAndOffers() {
		$path_dir = \NikolayS93\Exchanger\PLUGIN_DIR . 'tests/fixtures/';
		$path     = $path_dir . 'import0_1.zip';

		unzip( $path, $path_dir );

		$Parser = new \NikolayS93\Exchanger\Parser( $path );
		$Parser
			->listen_product()
			->listen_offer()
			->parse();

		$products = $Parser->get_products();
		$offers   = $Parser->get_offers();

		$this->assertTrue( is_a( $products, '\NikolayS93\Exchanger\ORM\Collection' ) );
		$this->assertIsArray( is_a( $offers, '\NikolayS93\Exchanger\ORM\Collection' ) );
	}
}
