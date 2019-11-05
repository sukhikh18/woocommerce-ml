<?php
/**
 * Class PluginTest
 *
 * @package Woocommerce.1c.Exchanger
 */

use NikolayS93\Exchanger\Plugin;
use NikolayS93\Exchanger\Request;
use NikolayS93\Exchanger\REST_Controller;
use const NikolayS93\Exchanger\PLUGIN_DIR;

require __DIR__ . '/../../helper.php';

class REST_ControllerTest extends WP_UnitTestCase {

	public function testDefinitions() {
		$this->assertTrue( defined( REST_Controller::OPTION_VERSION ) );
	}

	/**
	 * Check exchange is started
	 */
	public function testExchange() {
	}

	public function testCheckauth() {
	}

	public function testInit() {
		$rest = new REST_Controller();
		$rest->init( true );
		$this->assertNotEmpty( get_option( 'exchange_start-date' ) );
	}

	public function testFile() {
		$_REQUEST['type']     = 'catalog';
		$_REQUEST['mode']     = 'file';
		$_REQUEST['filename'] = 'test.zip';

		$REST = new REST_Controller();
		$REST->file( PLUGIN_DIR . 'tests/fixtures/import0_1.zip' );

		$plugin       = Plugin::get_instance();
		$exchange_dir = $plugin->get_exchange_dir( Request::get_type() );
		$this->assertTrue( is_file( $exchange_dir . '/import0_1.xml' ) );
	}

	public function testImport() {
	}

	public function testDeactivate() {
	}

	public function testComplete() {
	}
}
