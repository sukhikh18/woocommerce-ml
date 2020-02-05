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

	/**
	 * @return bool|mixed|string
	 */
	static function get_mode() {
		return (string) static::save_get_request( 'mode' );
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

	/**
	 * @return bool
	 */
	static function is_session_started() {
	    if ( 'cli' !== php_sapi_name() ) {
	        if ( version_compare(phpversion(), '5.4.0', '>=') ) {
	            return session_status() === PHP_SESSION_ACTIVE;
	        } else {
	            return session_id() !== '';
	        }
	    }

	    return FALSE;
	}

	static function set_session_args( $args ) {
		if( ! static::is_session_started() ) {
			session_start();
		}

		foreach ($args as $key => $value) {
			$_SESSION[ 'exchange_' . $key ] = $value;
		}
	}

	static function get_session_arg( $key, $default = null ) {
		if( ! static::is_session_started() ) {
			session_start();
		}

		return isset( $_SESSION[ 'exchange_' . $key ] ) ? $_SESSION[ 'exchange_' . $key ] : $default;
	}

	static function clear_session() {
		if( ! static::is_session_started() ) {
			session_start();
		}

		foreach ($_SESSION as $key => $value) {
			if( 0 === strpos($key, 'exchange_') ) {
				usnet($_SESSION[$key]);
			}
		}
	}
}
