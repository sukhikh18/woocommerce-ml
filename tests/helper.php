<?php
/**
 * Class PluginTest
 *
 * @package woocommerce-ml
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
	}
}

include_once __DIR__ . '/../vendor/autoload.php';
