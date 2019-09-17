<?php
/**
 * Class PluginTest
 *
 * @package Newproject.wordpress.plugin/
 */

use NikolayS93\Exchange\Plugin;
use NikolayS93\Exchange\Request;
use NikolayS93\Exchange\REST_Controller;
use const NikolayS93\Exchange\PLUGIN_DIR;

if( !class_exists('WP_UnitTestCase') ) {
    class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
    }
}


class REST_ControllerTest extends WP_UnitTestCase {
    public function testFile() {
        $_REQUEST['type'] = 'catalog';
        $_REQUEST['mode'] = 'file';
        $_REQUEST['filename'] = 'test.zip';

        $REST = new REST_Controller();
        $REST->file( PLUGIN_DIR . 'tests/fixtures/import0_1.zip' );

        $Plugin = Plugin::get_instance();
        $exchange_dir = $Plugin->get_exchange_dir( Request::get_type() );
        $this->assertTrue( is_file($exchange_dir . '/import0_1.xml') );
    }
}
