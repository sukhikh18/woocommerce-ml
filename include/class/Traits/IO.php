<?php

namespace NikolayS93\Exchange\Traits;

use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Plugin;
use const NikolayS93\Exchange\PLUGIN_DIR;

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
			@mkdir( $dir, 0777, true ) or Error::set_message( printf(
				__( "<strong>%s</strong>: Sorry but <strong>%s</strong> not has write permissions", static::DOMAIN ),
				__( "Fatal error", static::DOMAIN ),
				$dir
			) );

			return true;
		}

		return false;
	}

	public function check_writable( $dir ) {
		if ( ! is_dir( $dir ) && ! is_writable( $dir ) ) {
			Error::set_message( printf(
				__( "<strong>%s</strong>: Sorry but <strong>%s</strong> not found. Direcory is writable?", DOMAIN ),
				__( "Fatal error", Plugin::DOMAIN ),
				$dir
			) );
		}
	}

	public function get_exchange_dir( $namespace = null ) {
		$dir = trailingslashit( apply_filters( static::PREFIX . "get_exchange_dir",
			$this->get_upload_dir() . "/1c-exchange/" . $namespace, $namespace) );

		$this->try_make_dir( $dir );
		$this->check_writable( $dir );

		return realpath( $dir );
	}

	public function get_exchange_files( $filename = null, $namespace = 'catalog' ) {
		$arResult = array();

		// Get all folder objects
		$dir     = $this->get_exchange_dir( $namespace );

		$objects = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		/**
		 * Check objects name
		 */
		foreach ( $objects as $path => $object ) {
			if ( ! $object->isFile() || ! $object->isReadable() ) {
				continue;
			}
			if ( 'xml' != strtolower( $object->getExtension() ) ) {
				continue;
			}

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

	/**
	 * @param array $paths for ex. glob("$fld/*.zip")
	 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
	 * @param Boolean $rm is remove after unpack
	 *
	 * @return String|true    error message | all right
	 */
	static function unzip( $paths, $dir, $rm = false ) {
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
}