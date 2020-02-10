<?php
/**
 * Class ErrorTest
 *
 * @package woocommerce-ml
 */

use NikolayS93\Exchange\Error;

require __DIR__ . '/../../helper.php';

class ErrorTest extends WP_UnitTestCase {

	public function testInstance() {
		$this->assertInstanceOf( Error::class, Error::get_instance() );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testGet_messages() {
		$this->assertTrue( true );
	}

	public function testIs_empty() {
		$err = Error::get_instance();
		$this->assertTrue( $err->is_empty() );

		$err->add_message( 'Test', $code = 'Unit', $no_exit = true );
		$this->assertFalse( $err->is_empty() );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testShow_messages() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testAdd_message() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testSet_strict_mode() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testStrict_error_handler() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testStrict_exception_handler() {
		$this->assertTrue( true );
	}
}
