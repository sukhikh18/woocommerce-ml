<?php
/**
 * Class PluginTest
 *
 * @package woocommerce-ml
 */

namespace NikolayS93\Exchange;

require __DIR__ . '/helper.php';

/**
 * Sample test case.
 */
class ExchangerPluginTest extends \WP_UnitTestCase {

	public function testDefinitions() {
		$this->assertTrue( defined( '\NikolayS93\Exchange\PLUGIN_DIR' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchange\EXCHANGE_EXTERNAL_CODE_KEY' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchange\EXCHANGE_COOKIE_NAME' ) );
		$this->assertTrue( defined( '\NikolayS93\Exchange\EXCHANGE_CHARSET' ) );
	}

	public function testCallInstanceFunctions() {
		$this->assertInstanceOf( 'NikolayS93\Exchange\Plugin', plugin() );
		$this->assertInstanceOf( 'NikolayS93\Exchange\Error', error() );
		$this->assertInstanceOf( 'NikolayS93\Exchange\Transaction', transaction() );
	}

	public function testInclude_plugin_file() {
		$this->assertEquals( include_plugin_file( 'test' ), PLUGIN_DIR . 'test' );
		$this->assertEquals( include_plugin_file( PLUGIN_DIR . 'test' ), PLUGIN_DIR . 'test' );
	}
}
