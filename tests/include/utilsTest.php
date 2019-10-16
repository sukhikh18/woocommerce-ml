<?php
/**
 * Class UtilsTest
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

require PLUGIN_DIR . 'tests/helper.php';

class UtilsTest extends \WP_UnitTestCase {
	public function testGet_time() {
		$this->assertEquals( microtime( true ), get_time() );
		$this->assertEquals( microtime( true ) - 1, get_time( 1 ) );
	}

	public function testIs_debug() {
		$this->assertTrue( true );
	}

	public function testCheck_zip_extension() {
		$this->assertTrue( true );
	}

	public function testUnzip() {
		$this->assertTrue( true );
	}

	public function testEsc_external() {
		$this->assertTrue( true );
	}

	public function testEsc_cyr() {
		$fixtures = array(
			'privet_mir'         => 'привет_мир',
			'kollaboraciya'      => 'коллаборация',
			'rekursiya'          => 'рекурсия',
			'vzaimozamenyaemost' => 'взаимозаменяемость',
			'shini'              => 'шины',
		);

		array_walk(
			$fixtures,
			function ( $ru, $en ) {
				$this->assertEquals( esc_cyr( $ru ), $en );
			}
		);
	}

	public function testCheck_mode() {
		$this->assertTrue( true );
	}
}
