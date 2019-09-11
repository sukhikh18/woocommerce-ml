<?php
/**
 * Class PluginTest
 *
 * @package Newproject.wordpress.plugin/
 */

use NikolayS93\Exchange\Error;

if( !class_exists('WP_UnitTestCase') ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
	}
}


/**
 * Sample test case.
 */
class ErrorTest extends WP_UnitTestCase {

    public function testCheck_wpdb_error() {
	    global $wpdb;

        $wpdb = new \stdClass();

        $wpdb->last_error = false;
        Error::check_wpdb_error();

	    $this->assertEmpty( Error::$errors );

        $wpdb->last_error = 'test';
        Error::check_wpdb_error();

        $this->assertNotEmpty( Error::$errors );
    }

//    public function testShow_message() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testSet_message() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testShow_last_error() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testSet_wp_error() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testSet_strict_mode() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testStrict_error_handler() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
//    public function testStrict_exception_handler() {
//	    // todo write test
//	    $this->assertTrue( true );
//    }
}
