<?php


namespace NikolayS93\Exchange;


// static
class Request {

	static function save_get_request( $k ) {
		$value = false;

		if ( isset( $_REQUEST[ $k ] ) ) {
			$value = sanitize_text_field( $_REQUEST[ $k ] );
		}

		return $value;
	}

	static function get_filename() {
		return (string) static::save_get_request( 'filename' );
	}

	static function get_type() {
		return (string) static::save_get_request( 'type' );
	}

	/**
	 * Allowed modes from GET
	 *
	 * @return array
	 */
	private static function get_allowed_modes() {
		$allowed = apply_filters( Plugin::PREFIX . 'get_allowed_modes',
			array( 'checkauth', 'init', 'file', 'import', 'deactivate', 'complete' ) );

		return (array) $allowed;
	}

	public static function get_mode() {
		$allowed_modes = Request::get_allowed_modes();
		$mode = Plugin()->get_setting( 'mode', false, 'status' );

		if( ! in_array( $mode, $allowed_modes ) ) {
			$_mode = Request::save_get_request( 'mode' );
			$mode = in_array( $_mode, $allowed_modes ) ? $_mode : false;
		}

		return $mode;
	}

	static function get_full_request_uri() {
		$uri = 'http';
		if ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ) {
			$uri .= 's';
		}
		$uri .= "://{$_SERVER['SERVER_NAME']}";
		if ( $_SERVER['SERVER_PORT'] != 80 ) {
			$uri .= ":{$_SERVER['SERVER_PORT']}";
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri .= $_SERVER['REQUEST_URI'];
		}

		return $uri;
	}

	static function disable_request_time_limit() {
		$disabled_functions = explode( ',', ini_get( 'disable_functions' ) );

		if ( ! in_array( 'set_time_limit', $disabled_functions ) ) {
			@set_time_limit( 0 );
		}
	}
}