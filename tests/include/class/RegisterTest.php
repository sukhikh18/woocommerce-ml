<?php
/**
 * Class PluginTest
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

use const NikolayS93\Exchanger\PLUGIN_DIR;

require PLUGIN_DIR . 'tests/helper.php';

class RegisterTest extends \WP_UnitTestCase {

	public function testGet_warehouse_taxonomy_slug() {
		$this->assertEquals( gettype( Register::get_warehouse_taxonomy_slug() ), 'string' );
	}

	public function testActivate() {
		Register::activate();
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testDeactivate() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testUninstall() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testRegister_plugin_page() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testRegister_exchange_url() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testSet_mime_type_indexes() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testCreate_taxonomy_meta_table() {
		$this->assertTrue( true );
	}
}
