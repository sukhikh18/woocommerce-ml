<?php
/**
 * Class PluginTest
 *
 * @package Newproject.wordpress.plugin/
 */

if( !class_exists('WP_UnitTestCase') ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
	}
}


/**
 * Sample test case.
 */
class PluginTest extends WP_UnitTestCase {

	private $Plugin;

	public function setUp() {
		$this->Plugin = Plugin();
	}

	public function testInstance() {
		$this->assertIsObject($this->Plugin);
		$this->assertEquals( get_class( $this->Plugin ), 'Plugin' );
	}

	public function testActivate() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testDeactivate() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testUninstall() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testGet_option_name() {
		$filter_name = $this->Plugin::PREFIX . 'get_option_name';
		$option_name = 'test';

		$this->assertEquals( $this->Plugin::get_option_name(),
			apply_filters( $filter_name, $this->Plugin::DOMAIN, null ) );

		$this->assertEquals( $this->Plugin::get_option_name( $option_name ),
			apply_filters( $filter_name, $this->Plugin::PREFIX . $option_name, $option_name ) );
	}

	public function testGet_permissions() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testGet_dir() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testGet_file() {
		/** @todo */
		$this->assertTrue( true );
	}

	public function testGet_url() {
		$filter_name = $this->Plugin::PREFIX . 'get_plugin_url';
		$plugins_url = plugins_url();
		$plugin_url  = $plugins_url . '/' . basename( $this->Plugin::DIR );

		$path         = '/test/';
		$path2        = 'test/';
		$required_url = $plugin_url . $path;

		$this->assertEquals( $this->Plugin->get_url( $path ),
			apply_filters( $filter_name, $required_url, $path ) );
		$this->assertEquals( $this->Plugin->get_url( $path2 ),
			apply_filters( $filter_name, $required_url, $path ) );
	}

	public function testGet_template() {
		$template = 'admin/template/menu-page';
		$tpl      = $this->Plugin->get_dir() . "/$template";

		$this->assertFalse( $this->Plugin->get_template( 'fail/template/path' ) );
		$this->assertEquals( $this->Plugin->get_template( $template ), $tpl . '.php' );
		$this->assertEquals( $this->Plugin->get_template( '/' . $template . '.php' ), $tpl . '.php' );
	}

	public function test_get_setting() {
		$this->assertEquals( $this->Plugin->get_setting('test', false), 1 );
		$this->assertEquals( $this->Plugin->get_setting('test', false, 'context'), 2 );
		$this->assertEquals( $this->Plugin->get_setting('test2', false), 3 );
		// Reset options
		delete_option( $this->Plugin->get_option_name() );
		delete_option( $this->Plugin->get_option_name('context') );
		$this->assertFalse( $this->Plugin->get_setting('test', false) );
		$this->assertNull( $this->Plugin->get_setting('test', null, 'context') );
		$this->assertTrue( $this->Plugin->get_setting('test2', true) );
	}

	public function test_set_setting() {
		$this->assertTrue( $this->Plugin->set_setting('test', 1) );
		$this->assertFalse( $this->Plugin->set_setting('test', 1) );
		$this->assertTrue( $this->Plugin->set_setting('test', 2, 'context') );
		$this->assertTrue( $this->Plugin->set_setting(array('test2' => 3)) );
	}
}
