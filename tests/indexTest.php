<?php
/**
 * Class PluginTest
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

require __DIR__ . '/helper.php';

/**
 * Sample test case.
 */
class ExchangerPluginTest extends \WP_UnitTestCase {

	public function testDefinitions() {
		$this->assertTrue( defined( '\NikolayS93\Exchanger\PLUGIN_DIR' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchanger\EXCHANGE_EXTERNAL_CODE_KEY' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchanger\EXCHANGE_COOKIE_NAME' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchanger\EXCHANGE_CHARSET' ) );
	}

	public function testCallInstanceFunctions() {
		$this->assertInstanceOf( 'NikolayS93\Exchanger\Plugin', plugin() );
		$this->assertInstanceOf( 'NikolayS93\Exchanger\Error', error() );
		$this->assertInstanceOf( 'NikolayS93\Exchanger\Transaction', transaction() );
	}

	public function testInclude_plugin_file() {
		$this->assertEquals( include_plugin_file( 'test' ), PLUGIN_DIR . 'test' );
		$this->assertEquals( include_plugin_file( PLUGIN_DIR . 'test' ), PLUGIN_DIR . 'test' );
	}
}
