<?php

/**
 * @TODO
 */

namespace NikolayS93\Exchanger\Traits;

use const NikolayS93\Exchanger\PLUGIN_DIR;
use function NikolayS93\Exchanger\error;

trait IO {

	/**
	 * Get plugin url
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_url( $path = '' ) {
		$url = plugins_url( basename( $this->get_dir() ) ) . '/' . ltrim( $path, '/' );

		return apply_filters( static::PREFIX . 'get_url', $url, $path );
	}

	public function get_dir( $path = '' ) {
		return PLUGIN_DIR . ltrim( $path, DIRECTORY_SEPARATOR );
	}

	public function get_file( $dir_path, $filename ) {
		return $this->get_dir( $dir_path ) . '/' . trim( $filename, DIRECTORY_SEPARATOR );
	}

	public function get_upload_dir() {
		$wp_upload_dir = wp_upload_dir();

		return apply_filters( static::PREFIX . "get_upload_dir",
			$wp_upload_dir['basedir'], $wp_upload_dir );
	}

	/**
	 * @param string $dir
	 *
	 * @return bool is make try
	 */
	public function try_make_dir( $dir = '' ) {
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true ) or Error()->add_message( printf(
				__( "Sorry but %s not has write permissions", static::DOMAIN ),
				$dir
			), "Error" );

			return true;
		}

		return false;
	}

	public function check_writable( $dir ) {
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			Error()->add_message( printf(
				__( "Sorry but %s not found. Direcory is writable?", static::DOMAIN ),
				$dir
			) );
		}
	}

	public function get_exchange_dir( $namespace = null ) {
		$dir = trailingslashit( apply_filters( static::PREFIX . "get_exchange_dir",
			$this->get_upload_dir() . "/1c-exchange/" . $namespace, $namespace ) );

		$this->try_make_dir( $dir );
		$this->check_writable( $dir );

		return realpath( $dir );
	}

	public function get_exchange_file( $filepath, $namespace = 'catalog' ) {
		if ( ! empty( $filepath['path'] ) ) {
			$filepath = $filepath['path'];
		}

		$file = new \SplFileObject( $this->get_exchange_dir( $namespace ) . '/' . $filepath );

		if ( $file->isFile() && $file->isReadable() && 'xml' == strtolower( $file->getExtension() ) ) {
			return $file->getPathname();
		}

		return false;
	}

	public function get_exchange_files( $filename = null, $namespace = 'catalog' ) {
		$arResult = array();

		// Get all folder objects
		$dir = $this->get_exchange_dir( $namespace );

		$objects = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		/**
		 * Check objects name
		 */
		foreach ( $objects as $path => $object ) {

			if ( ! empty( $filename ) ) {
				/**
				 * Filename start with search string
				 */
				if ( 0 === strpos( $object->getBasename(), $filename ) ) {
					$arResult[] = $path;
				}
			} else {
				/**
				 * Get all xml files
				 */
				$arResult[] = $path;
			}
		}

		return $arResult;
	}

	/**
	 * Get plugin template path
	 *
	 * @param  [type]  $template [description]
	 *
	 * @return string|false
	 */
	public function get_template( $template ) {
		if ( ! pathinfo( $template, PATHINFO_EXTENSION ) ) {
			$template .= '.php';
		}

		$path = $this->get_dir() . ltrim( $template, '/' );
		if ( file_exists( $path ) && is_readable( $path ) ) {
			return $path;
		}

		return false;
	}
}
