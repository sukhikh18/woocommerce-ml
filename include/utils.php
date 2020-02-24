<?php

namespace NikolayS93\Exchange;

if ( ! function_exists( 'get_time' ) ) {
	function get_time( $time = false ) {
		return $time === false ? microtime( true ) : microtime( true ) - $time;
	}
}

if ( ! function_exists( 'is_debug' ) ) {
	function is_debug() {
		return ( defined( 'WP_DEBUG_SHOW' ) && WP_DEBUG_SHOW ) ||
		       ( ! defined( 'WP_DEBUG_SHOW' ) && defined( 'WP_DEBUG' ) && WP_DEBUG );
	}
}

if ( ! function_exists( 'check_zip_extension' ) ) {
	/**
	 * Zip functions required
	 */
	function check_zip_extension() {
		// @exec( "which unzip", $_, $status );
		// ! 0 === @$status

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'ZIP_ABSENT', 'The PHP extension zip is required.' );
		}

		return true;
	}
}

if ( ! function_exists( 'unzip' ) ) {
	/**
	 * @param array|string $paths for ex. glob("$fld/*.zip")
	 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
	 * @param Boolean $rm is remove after unpack
	 *
	 * @return String|true    error message | all right
	 */
	function unzip( $zip_path, $dir, $nondelete = false ) {
		$zip    = new \ZipArchive();
		$result = $zip->open( $zip_path );
		if ( $result !== true ) {
			return sprintf( 'Failed open archive %s with error code %d', $zip_path, $result );
		}

		$zip->extractTo( $dir ) or Error()->add_message( sprintf( 'Failed to extract from archive %s', $zip_path ) );
		$zip->close() or Error()->add_message( sprintf( 'Failed to close archive %s', $zip_path ) );

		if ( ! $nondelete ) {
			unlink( $zip_path );
		}

		return true;
	}
}

if ( ! function_exists( 'esc_external' ) ) {
	function esc_external( $ext ) {
		$pos = stripos( $ext, '/' );
		if ( false !== $pos ) {
			$ext = substr( $ext, $pos );
		}

		return $ext;
	}
}

if ( ! function_exists( 'esc_cyr' ) ) {
	/**
	 * Escape cyrillic chars
	 */
	function esc_cyr( $s, $context = 'url' ) {
		if ( 'url' === $context ) {
			$s = wp_strip_all_tags( (string) $s );
			$s = str_replace( array( "\n", "\r" ), ' ', $s );
			$s = preg_replace( '/\s+/', ' ', $s );
		}

		$s = trim( $s );
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
		$s = strtr(
			$s,
			array(
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
				'ь' => '',
			)
		);

		if ( 'url' === $context ) {
			$s = preg_replace( '/[^0-9a-z-_ ]/i', '', $s );
			$s = str_replace( ' ', '-', $s );
		}

		return $s;
	}
}

if( ! function_exists( 'check_mode' ) ) {
	function check_mode( $id, $setting ) {
		switch ( $setting ) {
			case 'off':
				return false;

			case 'create':
				return ! $id;

			case 'update':
				return (bool) $id;
		}

		return true;
	}
}

if( ! function_exists( 'make_dir' ) ) {
	/**
	 * @param string $dir
	 *
	 * @return bool is make try
	 */
	function make_dir( $dir = '' ) {
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true ) or Error()->add_message( printf(
				__( "Sorry but %s not has write permissions", Plugin::DOMAIN ),
				$dir
			), "Error" );

			return true;
		}

		return false;
	}
}

if( ! function_exists( 'check_writable' ) ) {
	function check_writable( $dir, $show_error = false ) {
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			if( $show_error ) {
				Error()->add_message( printf(
					__( "Sorry but %s not found. Direcory is writable?", Plugin::DOMAIN ),
					$dir
				) );
			}

			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'check_readble' ) ) {
	/**
	 * Check is file and is readble.
	 *
	 * @param string $path path to file
	 * @param boolean $show_error
	 *
	 * @return boolean
	 */
	function check_readble( $path, $show_error = false ) {
		if ( is_file( $path ) && is_readable( $path ) ) {
			return true;
		} elseif ( $show_error ) {
			Error()->add_message( sprintf( __( 'File %s not found.', Plugin::DOMAIN ), $path ) );
		}

		return false;
	}
}

if ( ! function_exists( 'include_plugin_file' ) ) {
	/**
	 * Safe dynamic expression include.
	 *
	 * @param string $path relative path.
	 */
	function include_plugin_file( $path ) {
		if ( 0 !== strpos( $path, PLUGIN_DIR ) ) {
			$path = PLUGIN_DIR . $path;
		}

		return check_readble( $path ) ? require_once $path : false;
	}
}

if ( ! function_exists( 'write_log' ) ) {
	function write_log($file, $args, $advanced = array()) {
		if( empty($args) ) return;

		$arRes = array();
		foreach ($args as $key => $value) {
			$arRes[] = "$key=$value";
		}

		$fw = fopen($file, "a");
		fwrite($fw, '[' . date('d.M.Y H:i:s') . "] " . implode(', ', $arRes) . implode(', ', $advanced) . "\n");
		fclose($fw);
	}
}
