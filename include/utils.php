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

if ( ! function_exists( 'check_user_permissions' ) ) {
	function check_user_permissions( $user ) {
		if ( ! user_can( $user, 'shop_manager' ) && ! user_can( $user, 'administrator' ) ) {
			Error::set_message( "No {$user} user permissions" );

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
				Error::set_message( "Not logged in" );
			}
		} elseif ( ! empty( $_COOKIE[ COOKIE_NAME ] ) ) {
			$user = wp_validate_auth_cookie( $_COOKIE[ COOKIE_NAME ], 'auth' );

			if ( ! $user_id = $user ) {
				Error::set_message( "Invalid cookie" );
			}
		}

		if ( ! check_user_permissions( $user_id ) ) {
			Error::set_message( "No {$user} user permissions" );
		}
	}
}

if ( ! function_exists( 'esc_external' ) ) {
	function esc_external( $ext ) {
		if ( 0 === stripos( $ext, 'XML/' ) ) {
			$ext = substr( $ext, 4 );
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
