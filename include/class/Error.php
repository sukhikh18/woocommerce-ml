<?php
/**
 * @package woocommerce-ml
 */

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Traits\Singleton;

class Error {

	use Singleton;

	/**
	 * @var \WP_Error
	 */
	private $WP_Error;

	public static $errors = array();

	protected function constructor() {
		$this->WP_Error = new \WP_Error();
	}

	public function get_messages( $code = '' ) {
		return $this->WP_Error->get_error_messages( $code );
	}

	public function is_empty( $code = '' ) {
		$messages = $this->get_messages( $code );

		return empty( $messages );
	}

	public function show_messages( $code = '' ) {
		array_map( function ( $message ) use ( $code ) {
			echo sprintf( "%s: %s\n", $code, strip_tags( $message ) );
		}, $this->get_messages( $code ) );

		echo "\n";
		debug_print_backtrace(); // phpcs:ignore.

		$arInfo = array(
			"Request URI"       => Request::get_full_request_uri(),
			"Server API"        => PHP_SAPI,
			"Memory limit"      => ini_get( 'memory_limit' ),
			"Maximum POST size" => ini_get( 'post_max_size' ),
			"PHP version"       => PHP_VERSION,
			"WordPress version" => get_bloginfo( 'version' ),
			"Plugin version"    => Plugin::VERSION,
		);

		echo "\n";
		array_walk( $arInfo, function ( $info_val, $info_key ) {
			echo "$info_key: $info_val\n";
		} );
	}

	public function add_message( $message, $code = 'Error', $no_exit = false ) {
		if ( $message instanceof \WP_Error ) {
			array_map( function ( $message ) use ( $code ) {
				$this->add_message( $message, $code );
			}, $this->WP_Error->get_error_messages() ); // $code

			return $this;
		} elseif ( is_object( $message ) ) {
			$message = print_r( $message, 1 );
		} elseif ( $message ) {
			// set dot if last space
			$last_char = substr( $message, - 1 );
			if ( ! in_array( $last_char, array( '.', '!', '?' ) ) ) {
				$message .= '.';
			}
		}

		if ( $message ) {
			error_log( $message );
			$this->WP_Error->add( $code, $message );
		}

		if ( ! $no_exit ) {
			if ( is_debug() ) {
				$this->show_messages( $code );
			}

			Transaction()->wpdb_stop();
			exit;
		}

		return $this;
	}

	/**
	 * Except all notices\warnings\errors
	 */
	public static function set_strict_mode() {
		set_error_handler( array( __CLASS__, 'strict_error_handler' ) );
		set_exception_handler( array( __CLASS__, 'strict_exception_handler' ) );
	}

	static function strict_error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {
		if ( 0 === error_reporting() ) {
			return false;
		}

		switch ( $errno ) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$type = "Notice";
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$type = "Warning";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$type = "Fatal Error";
				break;
			default:
				$type = "Unknown Error";
				break;
		}

		$message = sprintf( "%s in %s on line %d", $errstr, $errfile, $errline );
		Error()->add_message( $message, "PHP $type" );
	}

	/**
	 * @param \Exception $exception
	 */
	static function strict_exception_handler( $exception ) {
		$message = sprintf(
			"%s in %s on line %d",
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);

		Error()->add_message( $message, "Exception" );
	}
}
