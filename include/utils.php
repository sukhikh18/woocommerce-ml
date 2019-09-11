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
		@exec( "which unzip", $_, $status );

		if ( ! 0 === @$status || ! class_exists( 'ZipArchive' ) ) {
			Error::set_message( "The PHP extension zip is required." );
		}
	}
}

/**
 * @param array|string $paths for ex. glob("$fld/*.zip")
 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
 * @param Boolean $rm is remove after unpack
 *
 * @return String|true    error message | all right
 */
function unzip( $paths, $dir, $rm = false ) {
    // распаковывает но возвращает статус 0
    // $command = sprintf("unzip -qqo -x %s -d %s", implode(' ', array_map('escapeshellarg', $paths)), escapeshellarg($dir));
    // @exec($command, $_, $status);
    $paths = is_string( $paths ) ? array($paths) : (array) $paths;

    // if (@$status !== 0) {
    foreach ( $paths as $zip_path ) {
        $zip    = new \ZipArchive();
        $result = $zip->open( $zip_path );
        if ( $result !== true ) {
            return sprintf( "Failed open archive %s with error code %d", $zip_path, $result );
        }

        $zip->extractTo( $dir ) or Error::set_message( sprintf( "Failed to extract from archive %s", $zip_path ) );
        $zip->close() or Error::set_message( sprintf( "Failed to close archive %s", $zip_path ) );
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

if ( ! function_exists( 'esc_external' ) ) {
	function esc_external( $ext ) {
		if ( false !== $pos = stripos( $ext, '/' ) ) {
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
