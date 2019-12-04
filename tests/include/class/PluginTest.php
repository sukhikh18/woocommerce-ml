<?php
/**
 * Class PluginTest
 *
 * @package Newproject.WordPress.plugin
 */

use NikolayS93\PluginName\Plugin;

/**
 * Sample test case.
 */
class PluginTest extends WP_UnitTestCase {
	/**
	 * Plugin singleton class instance
	 *
	 * @var NikolayS93\PluginName\Plugin
	 */
	private $plugin;

	/**
	 * This is the method that is called before each test.
	 */
	public function setUp() {
		$this->plugin = Plugin::get_instance();
	}

	/**
	 * Singleton instance
	 */
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

	/**
	 * Any plugins has settings and setting name. Test get it.
	 */
	public function testGet_option_name() {
		$filter_name = Plugin::PREFIX . 'get_option_name';
		$option_name = 'test';

		$this->assertEquals(
			$this->plugin->get_option_name(),
			apply_filters( $filter_name, Plugin::DOMAIN, null )
		);

		$this->assertEquals(
			$this->plugin->get_option_name( $option_name ),
			apply_filters( $filter_name, Plugin::PREFIX . $option_name, $option_name )
		);
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

	/**
	 * Get url.
	 */
	public function testGet_url() {
		$filter_name = Plugin::PREFIX . 'get_plugin_url';
		$plugins_url = plugins_url();
		$plugin_url  = $plugins_url . '/' . basename( $this->plugin->get_dir() );

		$path         = '/test/';
		$path2        = 'test/';
		$required_url = $plugin_url . $path;

		$this->assertEquals(
			$this->plugin->get_url( $path ),
			apply_filters( $filter_name, $required_url, $path )
		);
		$this->assertEquals(
			$this->plugin->get_url( $path2 ),
			apply_filters( $filter_name, $required_url, $path )
		);
	}

	/**
	 * Get plugin template path
	 */
	public function testGet_template() {
		$template = 'admin/template/section';
		$tpl      = $this->plugin->get_dir() . "$template";

		$this->assertFalse( $this->plugin->get_template( 'fail/template/path' ) );
		$this->assertEquals( $this->plugin->get_template( $template ), $tpl . '.php' );
		$this->assertEquals( $this->plugin->get_template( '/' . $template . '.php' ), $tpl . '.php' );
	}

	/**
	 * Reset options for check clear.
	 */
	private function resetOptions() {
		delete_option( $this->plugin->get_option_name() );
		delete_option( $this->plugin->get_option_name( 'context' ) );
	}

	/**
	 * Get setting.
	 */
	public function testGet_setting() {
		$this->testSet_setting();

		$this->assertEquals( $this->plugin->get_setting( 'test', false ), 1 );
		$this->assertEquals( $this->plugin->get_setting( 'test', false, 'context' ), 2 );
		$this->assertEquals( $this->plugin->get_setting( 'test2', false ), 3 );
		$this->resetOptions();

		$this->assertFalse( $this->plugin->get_setting( 'test', false ) );
		$this->assertNull( $this->plugin->get_setting( 'test', null, 'context' ) );
		$this->assertTrue( $this->plugin->get_setting( 'test2', true ) );
	}

	/**
	 * Set setting
	 */
	public function testSet_setting() {
		$this->assertTrue( $this->plugin->set_setting( 'test', 1 ) );
		$this->assertFalse( $this->plugin->set_setting( 'test', 1 ) );
		$this->assertTrue( $this->plugin->set_setting( 'test', 2, 'context' ) );
		$this->assertTrue( $this->plugin->set_setting( array( 'test2' => 3 ) ) );
	}
}
