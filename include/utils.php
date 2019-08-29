<?php

namespace NikolayS93\Exchange;

function is_debug_show() {
	return ( ! defined( 'WP_DEBUG_DISPLAY' ) && defined( 'WP_DEBUG' ) && true == WP_DEBUG ) ||
	       defined( 'WP_DEBUG_DISPLAY' ) && true == WP_DEBUG_DISPLAY;
}

function is_debug() {
	return ( defined( 'EX_DEBUG_ONLY' ) && true === EX_DEBUG_ONLY );
}

if ( ! function_exists('get_filename') ) {
	function get_filename() {
		return save_get_request( 'filename' );
	}
}

if ( ! function_exists('get_type') ) {
	function get_type() {
		return save_get_request( 'type' );
	}
}

function get_time( $time = false ) {
	return $time === false ? microtime( true ) : microtime( true ) - $time;
}

function get_summary_meta( $filename ) {
	$version = 0;
	$is_full = null;
	// $is_moysklad = null;

	$fp = @fopen( $filename, 'r' ) or static::error( sprintf( "Failed to open file %s", $filename ) );

	while ( ( $buffer = fgets( $fp ) ) !== false ) {
		if ( false !== $pos = strpos( $buffer, " ВерсияСхемы=" ) ) {
			$version = substr( $buffer, $pos + 14, 4 );
		}

		// if( false !== strpos($buffer, " СинхронизацияТоваров=") ) {
		//     $is_moysklad = true;
		// }

		if ( strpos( $buffer, " СодержитТолькоИзменения=" ) === false && strpos( $buffer, "<СодержитТолькоИзменения>" ) === false ) {
			continue;
		}
		$is_full = strpos( $buffer, " СодержитТолькоИзменения=\"false\"" ) !== false || strpos( $buffer, "<СодержитТолькоИзменения>false<" ) !== false;
		break;
	}

	@rewind( $fp ) or static::error( sprintf( "Failed to rewind on file %s", $filename ) );
	@fclose( $fp ) or static::error( sprintf( "Failed to close file %s", $filename ) );

	$result = array(
		'version' => (float) $version,
		'is_full' => (bool) $is_full,
		// 'is_moysklad' => $is_moysklad,
	);

	return $result;
}

function error( $message, $type = "Error", $no_exit = false ) {
	global $ex_is_error;

	$ex_is_error = true;

	// failure\n think about
	$message   = "$type: $message";
	$last_char = substr( $message, - 1 );
	if ( ! in_array( $last_char, array( '.', '!', '?' ) ) ) {
		$message .= '.';
	}

	error_log( $message );
	echo "$message\n";

	if ( static::is_debug_show() ) {
		echo "\n";
		debug_print_backtrace();

		$arInfo = array(
			"Request URI"       => get_full_request_uri(),
			"Server API"        => PHP_SAPI,
			"Memory limit"      => ini_get( 'memory_limit' ),
			"Maximum POST size" => ini_get( 'post_max_size' ),
			"PHP version"       => PHP_VERSION,
			"WordPress version" => get_bloginfo( 'version' ),
			"Plugin version"    => Plugin::get_plugin_data( 'Version' ),
		);
		echo "\n";
		foreach ( $arInfo as $info_name => $info_value ) {
			echo "$info_name: $info_value\n";
		}
	}

	if ( ! $no_exit ) {
		static::wpdb_stop();
		exit;
	}
}

function wp_error( $wp_error, $only_error_code = null ) {
	$messages = array();
	foreach ( $wp_error->get_error_codes() as $error_code ) {
		if ( $only_error_code && $error_code != $only_error_code ) {
			continue;
		}

		$wp_error_messages = implode( ", ", $wp_error->get_error_messages( $error_code ) );
		$wp_error_messages = strip_tags( $wp_error_messages );
		$messages[]        = sprintf( "%s: %s", $error_code, $wp_error_messages );
	}

	static::error( implode( "; ", $messages ), "WP Error" );
}

function check_wpdb_error() {
	global $wpdb;

	if ( ! $wpdb->last_error ) {
		return;
	}

	static::error( sprintf( "%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query ), "DB Error", true );

	static::wpdb_stop( false, true );

	exit;
}

/**
 * @param Array $paths for ex. glob("$fld/*.zip")
 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
 * @param Boolean $rm is remove after unpack
 *
 * @return String|true    error message | all right
 */
function unzip( $paths, $dir, $rm = false ) {
	// if (!$paths) sprintf("No have a paths");

	// распаковывает но возвращает статус 0
	// $command = sprintf("unzip -qqo -x %s -d %s", implode(' ', array_map('escapeshellarg', $paths)), escapeshellarg($dir));
	// @exec($command, $_, $status);

	// if (@$status !== 0) {
	foreach ( $paths as $zip_path ) {
		$zip    = new \ZipArchive();
		$result = $zip->open( $zip_path );
		if ( $result !== true ) {
			return sprintf( "Failed open archive %s with error code %d", $zip_path, $result );
		}

		$zip->extractTo( $dir ) or static::error( sprintf( "Failed to extract from archive %s", $zip_path ) );
		$zip->close() or static::error( sprintf( "Failed to close archive %s", $zip_path ) );
	}

	if ( $rm ) {
		$remove_errors = array();

		foreach ( $paths as $zip_path ) {
			if ( ! @unlink( $zip_path ) ) {
				$remove_errors[] = sprintf( "Failed to unlink file %s", $zip_path );
			}
		}

		if ( ! empty( $remove_errors ) ) {
			return implode( "\n", $remove_errors );
		}
	}

	return true;
	// }
}

if ( ! function_exists('add_log') ) {
	function add_log() {
		if ( is_wp_error( $err ) ) {
			$err = $err->get_error_code() . ': ' . $err->get_error_message();
			// var_dump( $err );
		}

		if ( $thing ) {
			// var_dump( $thing );
			// echo "<br><br>";
		}

		file_put_contents( __DIR__ . '/debug.log', print_r( array( $err, $thing ), 1 ) );
		// static::error($err);
	}
}

/**
 * User validation
 * [check_user_permissions description]
 *
 * @param int|WP_User $user [description]
 *
 * @return [type]       [description]
 */
function check_user_permissions( $user ) {
	if ( ! user_can( $user, 'shop_manager' ) && ! user_can( $user, 'administrator' ) ) {
		error( "No {$user} user permissions" );
	}
}

if ( ! function_exists('get_exchange_data_dir') ) {
    function get_exchange_data_dir() {
        $wp_upload_dir = wp_upload_dir();

        return apply_filters( "get_exchange_data_dir", $wp_upload_dir['basedir'] . "/1c-exchange/" );
    }
}

if ( ! function_exists( 'save_get_request' ) ) {
    /**
     * Get requested data
     */
    function save_get_request( $k ) {
        $value = false;

        if ( isset( $_REQUEST[ $k ] ) ) {
            $value = sanitize_text_field( $_REQUEST[ $k ] );
        }

        return apply_filters( 'get_request__' . $k, $value );
    }
}

if ( ! function_exists( 'get_full_request_uri' ) ) {
	function get_full_request_uri() {
		$uri = 'http';
		if ( @$_SERVER['HTTPS'] == 'on' ) {
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
}

if ( ! function_exists( 'check_zip_extension' ) ) {
	/**
	 * Zip functions required
	 */
	function check_zip_extension() {
        @exec( "which unzip", $_, $status );

		if ( ! 0 === @$status || !class_exists( 'ZipArchive' ) ) {
			ex_error( "The PHP extension zip is required." );
		}
	}
}

if ( ! function_exists( 'disable_time_limit' ) ) {
	function disable_time_limit() {
        $disabled_functions = explode( ',', ini_get( 'disable_functions' ) );

		if ( ! in_array( 'set_time_limit', $disabled_functions ) ) {
			@set_time_limit( 0 );
		}
	}
}

if( ! function_exists('check_user_permissions') ) {
    function check_user_permissions( $user ) {
        if ( ! user_can( $user, 'shop_manager' ) && ! user_can( $user, 'administrator' ) ) {
            return false;
        }

        return true;
    }
}

if ( ! function_exists( 'check_wp_auth' ) ) {
	function check_wp_auth() {
		global $user_id;

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			if ( ! $user_id = $user->ID ) {
				ex_error( "Not logged in" );
			}
		} elseif ( ! empty( $_COOKIE[ COOKIENAME ] ) ) {
			$user = wp_validate_auth_cookie( $_COOKIE[ COOKIENAME ], 'auth' );

			if ( ! $user_id = $user ) {
				ex_error( "Invalid cookie" );
			}
        }

		if( ! check_user_permissions( $user_id ) ) {
            ex_error( "No {$user} user permissions" );
        }
	}
}

if ( ! function_exists( 'esc_cyr' ) ) {
	/**
	 * Escape cyrilic chars
	 */
	function esc_cyr( $s, $context = 'url' ) {
		if ( 'url' == $context ) {
			$s = strip_tags( (string) $s );
			$s = str_replace( array( "\n", "\r" ), " ", $s );
			$s = preg_replace( "/\s+/", ' ', $s );
		}

		$s = trim( $s );
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		$s = strtr( $s, array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'e',
			'ж' => 'j',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'c',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shch',
			'ы' => 'y',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			'ъ' => '',
			'ь' => ''
		) );

		if ( 'url' == $context ) {
			$s = preg_replace( "/[^0-9a-z-_ ]/i", "", $s );
			$s = str_replace( " ", "-", $s );
		}

		return $s;
	}
}
