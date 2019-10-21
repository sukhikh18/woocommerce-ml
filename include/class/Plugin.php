<?php
/**
 * Main singleton plugin class
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

use NikolayS93\Exchanger\Traits\Singleton;
use NikolayS93\Exchanger\Traits\IO;

/**
 * Class Plugin
 */
class Plugin {

	use Singleton;
	use IO;

	/**
	 * Plugin version
	 */
	const VERSION = '0.3';

	/**
	 * Uniq plugin slug name.
	 */
	const DOMAIN = 'w1ce';

	/**
	 * Uniq plugin prefix.
	 */
	const PREFIX = 'w1ce_';

	/**
	 * The capability required to use this plugin.
	 * Please don't change this directly.
	 * Use the self::PREFIX . "get_permissions" filter instead.
	 *
	 * @var array
	 */
	private $permissions = array( 'shop_manager', 'administrator' );

	/**
	 * Get option name for a options in the WordPress database
	 *
	 * @param string $suffix option name suffix "plugin_$suffix".
	 *
	 * @return string
	 */
	public function get_option_name( $suffix = '' ) {
		$option_name = $suffix ? self::PREFIX . $suffix : self::DOMAIN;

		return apply_filters( self::PREFIX . 'get_option_name', $option_name, $suffix );
	}

	/**
	 * Get plugin user permissions
	 *
	 * @return string
	 */
	public function get_permissions() {
		return apply_filters( self::PREFIX . 'get_permissions', $this->permissions );
	}

	/**
	 * Get plugin dir (without slash end)
	 *
	 * @param string $path Path to something relative.
	 *
	 * @return string
	 */
	public function get_dir( $path = '' ) {
		return PLUGIN_DIR . ltrim( $path, DIRECTORY_SEPARATOR );
	}

	/**
	 * Get file by plugin dir path
	 *
	 * @param string $dir_path [description].
	 * @param string $filename [description].
	 *
	 * @return string
	 */
	public function get_file( $dir_path, $filename ) {
		return $this->get_dir( $dir_path ) . trim( $filename, DIRECTORY_SEPARATOR );
	}

	/**
	 * Get plugin url
	 *
	 * @param string $path Path to something relative.
	 *
	 * @return string
	 */
	public function get_url( $path = '' ) {
		$url = plugins_url( basename( $this->get_dir() ) ) . '/' . ltrim( $path, '/' );

		return apply_filters( self::PREFIX . 'get_url', $url, $path );
	}


	/**
	 * Get plugin template path
	 *
	 * @param  [type] $template [description].
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
	 * Get plugin setting from cache or database
	 *
	 * @param string $prop_name Option key or null (for a full request).
	 * @param mixed  $default What's return if field value not defined.
	 * @param string $context suffix option name. @see get_option_name().
	 *
	 * @return mixed
	 */
	public function get_setting( $prop_name = null, $default = false, $context = '' ) {
		$option_name = $this->get_option_name( $context );

		/**
		 * Get field value from wp_options
		 *
		 * @link https://developer.wordpress.org/reference/functions/get_option/
		 * @var mixed
		 */
		$option = apply_filters(
			self::PREFIX . 'get_option',
			get_option( $option_name, $default )
		);

		if ( ! $prop_name ) {
			return ! empty( $option ) ? $option : $default;
		}

		return isset( $option[ $prop_name ] ) ? $option[ $prop_name ] : $default;
	}

	/**
	 * Set new plugin setting
	 *
	 * @param string|array $prop_name Option key || array.
	 * @param string       $value prop_name key => value.
	 * @param string       $context suffix option name. @see get_option_name().
	 *
	 * @return bool                   Is updated @see update_option()
	 */
	public function set_setting( $prop_name, $value = '', $context = '' ) {
		if ( ! $prop_name || ( $value && ! (string) $prop_name ) ) {
			return false;
		}

		// Get all defined settings by context.
		$option = $this->get_setting( null, false, $context );

		if ( is_array( $prop_name ) ) {
			$option = is_array($option) ? array_merge( $option, $prop_name ) : $prop_name;
		} else {
			$option[ $prop_name ] = $value;
		}

		if ( ! empty( $option ) ) {
			// Do not auto load for plugin settings (default)!
			$autoload = ! $context ? 'no' : null;

			return update_option(
				$this->get_option_name( $context ),
				$option,
				apply_filters( self::PREFIX . 'autoload', $autoload, $option, $context )
			);
		}

		return false;
	}

	/**
	 * Setup plugin.
	 */
	public function constructor() {
		// Allow people to change what capability is required to use this plugin.
		$this->permissions = apply_filters(
			self::PREFIX . 'permissions',
			$this->permissions
		);

		// load plugin languages.
		load_plugin_textdomain( self::DOMAIN, false, basename( self::get_dir() ) . '/languages/' );
	}

	/**
	 * Allowed modes from GET
	 *
	 * @return array
	 */
	private function get_allowed_modes() {
		$allowed = apply_filters( Plugin::PREFIX . 'get_allowed_modes', array(
			'checkauth',
			'init',
			'file',
			'import',
			'import_posts',
			'import_relationships',
			'deactivate',
			'complete',
		) );

		return (array) $allowed;
	}

	public function get_mode() {
		$allowed_modes = $this->get_allowed_modes();
		$mode          = $this->get_setting( 'mode', false, 'status' );
		$_mode         = Request::save_get_request( 'mode' );

		if ( 'complete' === $_mode ) {
			return $_mode;
		}

		if ( ! in_array( $mode, $allowed_modes ) ) {
			$mode = in_array( $_mode, $allowed_modes ) ? $_mode : false;
		}

		return $mode;
	}

	/**
	 * Set inner plug-in mode
	 *
	 * @param string $mode
	 * @param Update $update instance
	 * @return $this|Update
	 */
	public function set_mode( $mode, $update = 0 ) {
		$args = array(
			'mode'     => $mode,
			'progress' => $update->get_progress(),
		);

		return Plugin()->set_setting( $args, null, 'status' );
	}

	function reset_mode() {
		return $this->set_mode( '', new Update() );
	}
}
