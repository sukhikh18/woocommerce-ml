<?php
/**
 * Class PluginTest
 *
 * @package Newproject.wordpress.plugin/
 */

use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Request;

if( !class_exists('WP_UnitTestCase') ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
	}
}


/**
 * Sample test case.
 */
class RequestTest extends WP_UnitTestCase {

	public function testSave_get_request() {
		$_REQUEST['mode'] = 'test<script>alert(1);</script>';

		$this->assertFalse( Request::save_get_request('fake') );
		$this->assertSame('test', Request::save_get_request('mode'));
	}

	public function testGet_filename() {
		$_REQUEST['filename'] = 'test2';

		$this->assertSame('test2', Request::get_filename());
	}

	public function testGet_type() {
		$_REQUEST['type'] = 'test3';

		$this->assertSame('test3', Request::get_type());

		$_REQUEST['type'] = array('test4');

		$this->assertSame('Array', Request::get_type());
	}

	public function testGet_allowed_modes() {

	}
}
