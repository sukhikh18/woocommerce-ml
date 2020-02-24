<?php
/**
 * Class PluginTest
 *
 * @package woocommerce-ml
 */

use NikolayS93\Exchange\Plugin;
use const NikolayS93\Exchange\PLUGIN_DIR;

require __DIR__ . '/../../helper.php';

class PluginTest extends WP_UnitTestCase {

	/** @var \NikolayS93\Exchange\Plugin */
	private $plugin;

	public function setUp() {
		$this->plugin = Plugin::get_instance();
	}

	public function testInstance() {
		$this->assertInstanceOf( Plugin::class, $this->plugin );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testActivate() {
		$this->assertTrue( true );
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

	public function testGet_option_name() {
		$filter_name = Plugin::PREFIX . 'get_option_name';
		$option_name = 'test';

		$this->assertEquals( $this->plugin->get_option_name(),
			apply_filters( $filter_name, Plugin::DOMAIN, null ) );

		$this->assertEquals( $this->plugin->get_option_name( $option_name ),
			apply_filters( $filter_name, Plugin::PREFIX . $option_name, $option_name ) );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_permissions() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_dir() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_file() {
		$this->assertTrue( true );
	}

	public function testGet_url() {
		$filter_name = Plugin::PREFIX . 'get_plugin_url';
		$plugins_url = plugins_url();
		$plugin_url  = $plugins_url . '/' . basename( $this->plugin->get_dir() );

		$path         = '/test/';
		$path2        = 'test/';
		$required_url = $plugin_url . $path;

		$this->assertEquals( $this->plugin->get_url( $path ),
			apply_filters( $filter_name, $required_url, $path ) );
		$this->assertEquals( $this->plugin->get_url( $path2 ),
			apply_filters( $filter_name, $required_url, $path ) );
	}

	public function testGet_template() {
		$template = 'admin/template/menu-page';
		$tpl      = $this->plugin->get_dir() . "$template";

		$this->assertFalse( $this->plugin->get_template( 'fail/template/path' ) );
		$this->assertEquals( $this->plugin->get_template( $template ), $tpl . '.php' );
		$this->assertEquals( $this->plugin->get_template( '/' . $template . '.php' ), $tpl . '.php' );
	}

	private function resetOptions() {
		delete_option( $this->plugin->get_option_name() );
		delete_option( $this->plugin->get_option_name( 'context' ) );
	}

	public function testGet_setting() {
		$this->testSet_setting();

		$this->assertEquals( $this->plugin->get( 'test', false ), 1 );
		$this->assertEquals( $this->plugin->get( 'test', false, 'context' ), 2 );
		$this->assertEquals( $this->plugin->get( 'test2', false ), 3 );
		$this->resetOptions();

		$this->assertFalse( $this->plugin->get( 'test', false ) );
		$this->assertNull( $this->plugin->get( 'test', null, 'context' ) );
		$this->assertTrue( $this->plugin->get( 'test2', true ) );
	}

	public function testSet_setting() {
		$this->assertTrue( $this->plugin->set( 'test', 1 ) );
		$this->assertFalse( $this->plugin->set( 'test', 1 ) );
		$this->assertTrue( $this->plugin->set( 'test', 2, 'context' ) );
		$this->assertTrue( $this->plugin->set( array( 'test2' => 3 ) ) );
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
	public function testSet_mode() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testReset_mode() {
		$this->assertTrue( true );
	}
}
