<?php
/**
 * Singleton pattern
 *
 * @link https://en.wikipedia.org/wiki/Singleton_pattern
 * @package Newproject.WordPress.plugin
 */

namespace NikolayS93\Exchange\Creational;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

trait Singleton {
	/**
	 * The stored singleton instance
	 *
	 * @var static
	 */
	protected static $instance;

	/**
	 * Creates the original or retrieves the stored singleton instance
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( ! static::$instance ) {
			try {
				static::$instance = ( new ReflectionClass( get_called_class() ) )
					->newInstanceWithoutConstructor();
				call_user_func_array( array( static::$instance, 'constructor' ), func_get_args() );
			} catch ( ReflectionException $e ) {
				wp_die( esc_html( $e->getMessage() ) );
			}
		}

		return static::$instance;
	}

	/**
	 * Init Singleton function
	 */
	protected function constructor() {
	}

	/**
	 * The constructor is disabled
	 *
	 * @throws RuntimeException If called.
	 */
	public function __construct() {
		throw new RuntimeException( 'You may not explicitly instantiate this object, because it is a singleton.' );
	}

	/**
	 * Cloning is disabled
	 *
	 * @throws RuntimeException If called.
	 */
	public function __clone() {
		throw new RuntimeException( 'You may not clone this object, because it is a singleton.' );
	}

	/**
	 * Unserialization is disabled
	 *
	 * @throws RuntimeException If called.
	 */
	public function __wakeup() {
		throw new RuntimeException( 'You may not unserialize this object, because it is a singleton.' );
	}

	/**
	 * Unserialization is disabled
	 *
	 * @throws RuntimeException If called.
	 */
	public function unserialize() {
		throw new RuntimeException( 'You may not unserialize this object, because it is a singleton.' );
	}
}
