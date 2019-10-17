<?php
/**
 * Class PluginTest
 *
 * @package Woocommerce.1c.Exchanger
 */

use NikolayS93\Exchanger\Request;
use const NikolayS93\Exchanger\PLUGIN_DIR;

require __DIR__ . '/../../helper.php';

/**
 * Sample test case.
 */
class RequestTest extends WP_UnitTestCase {

	public function testSave_get_request() {
		$_REQUEST['mode'] = 'test<script>alert(1);</script>';

		$this->assertFalse( Request::save_get_request( 'fake' ) );
		$this->assertSame( 'test', Request::save_get_request( 'mode' ) );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_file_array() {
		$this->assertTrue( true );
	}

	public function testGet_file() {
		$_REQUEST['filename'] = 'test2';

		$this->assertSame( 'test2', Request::get_file() );
	}

	public function testGet_type() {
		$_REQUEST['type'] = 'test3';

		$this->assertSame( 'test3', Request::get_type() );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_allowed_modes() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_mode() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testDisable_request_time_limit() {
		$this->assertTrue( true );
	}
}
