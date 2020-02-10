<?php
/**
 * Class ErrorTest
 *
 * @package woocommerce-ml
 */

use NikolayS93\Exchange\Transaction;
use const NikolayS93\Exchange\PLUGIN_DIR;

require __DIR__ . '/../../helper.php';

class TransactionTest extends WP_UnitTestCase {

	public function testInstance() {
		$this->assertInstanceOf( Transaction::class, Transaction::get_instance() );
	}

	public function testIs_transaction() {
		$this->assertSame( gettype( Transaction::get_instance()->is_transaction() ), 'bool' );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testSet_transaction_mode() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testTransaction_shutdown_function() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testCheck_wpdb_error() {
		$this->assertTrue( true );
	}

	/**
	 * Unrealized test
	 *
	 * @todo
	 */
	public function testWpdb_stop() {
		$this->assertTrue( true );
	}
}
