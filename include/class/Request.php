<?php


namespace NikolayS93\Exchanger;


// static
class Request {

	static function save_get_request( $k ) {
		$value = false;

		if ( isset( $_REQUEST[ $k ] ) ) {
			$value = sanitize_text_field( $_REQUEST[ $k ] );
		}

		return $value;
	}

	protected static function get_file_array() {
		$filepath  = (string) static::save_get_request( 'filename' );
		$path      = wp_parse_url( $filepath, PHP_URL_PATH );
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		$filename  = pathinfo( $path, PATHINFO_FILENAME );

		return array(
			'~path' => $filepath,
			'~name' => $filename,
			'ext'   => $extension,
		);
	}

	static function get_file() {
		$file               = static::get_file_array();
		$allowed_extensions = array( 'xml', 'zip' );

		$file['path'] = ltrim( $file['~path'], "./\\" );
		$file['name'] = ltrim( $file['~name'], "./\\" );

		if ( ! $file['name'] ) {
			Error()->add_message( "Filename is empty" );
		}

		if ( ! in_array( $file['ext'], $allowed_extensions, true ) ) {
			Error()->add_message( 'Тип файла противоречит политике безопасности.' );
		}

		return $file;
	}

	static function get_type() {
		return (string) static::save_get_request( 'type' );
	}

	static function get_full_request_uri() {
		$uri = 'http';
		if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) {
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

		if ( ! in_array( 'set_time_limit', $disabled_functions, true ) ) {
			set_time_limit( 0 );
		}
	}
}
