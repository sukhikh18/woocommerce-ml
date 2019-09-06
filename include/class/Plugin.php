<?php

namespace NikolayS93\PluginName;

use NikolayS93\PluginName\Creational\Singleton;

class Plugin {

	use Singleton;

	/**
	 * Uniq plugin slug name
	 */
	const DOMAIN = 'plugin';

	/**
	 * Uniq plugin prefix
	 */
	const PREFIX = 'plugin_';

	/**
	 * The capability required to use this plugin.
	 * Please don't change this directly. Use the "regenerate_thumbs_cap" filter instead.
	 *
	 * @var string
	 */
	private $permissions = 'manage_options';

	/**
	 * Get option name for a options in the Wordpress database
	 *
	 * @param string $suffix
	 *
	 * @return string
	 */
	public function get_option_name( $suffix = '' ) {
		$option_name = $suffix ? self::PREFIX . $suffix : self::DOMAIN;

		return apply_filters( self::PREFIX . 'get_option_name', $option_name, $suffix );
	}

	public function get_permissions() {
		return $this->permissions;
	}

	public function get_dir( $path = '' ) {
		return PLUGIN_DIR . ltrim( $path, DIRECTORY_SEPARATOR );
	}

	public function get_file( $dir_path, $filename ) {
		return $this->get_dir( $dir_path ) . trim( $filename, DIRECTORY_SEPARATOR );
	}

	/**
	 * Get plugin url
	 *
	 * @param string $path
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
	 * Get plugin setting from cache or database
	 *
	 * @param string $prop_name Option key or null (for a full request)
	 * @param mixed $default What's return if field value not defined.
	 * @param string $context
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
		$option = apply_filters( self::PREFIX . 'get_option',
			get_option( $option_name, $default ) );

		if ( ! $prop_name ) {
			return ! empty( $option ) ? $option : $default;
		}

		return isset( $option[ $prop_name ] ) ? $option[ $prop_name ] : $default;
	}

	/**
	 * Set new plugin setting
	 *
	 * @param string|array $prop_name Option key || array
	 * @param string $value prop_name key => value
	 * @param string $context
	 *
	 * @return bool                   Is updated @see update_option()
	 *
	 */
	public function set_setting( $prop_name, $value = '', $context = '' ) {
		if ( ! $prop_name || ( $value && ! (string) $prop_name ) ) {
			return false;
		}

		// Get all defined settings by context
		$option = $this->get_setting( null, false, $context );

		if ( is_array( $prop_name ) ) {
			$option = array_merge( $option, $prop_name );
		} else {
			$option[ $prop_name ] = $value;
		}

		if ( ! empty( $option ) ) {
			// Do not auto load for plugin settings (default)
			$autoload = ! $context ? 'no' : null;

			return update_option( $this->get_option_name( $context ), $option,
				apply_filters( self::PREFIX . 'autoload', $autoload, $option, $context ) );
		}

		return false;
	}

	/**
	 * Setup plugin.
	 */
	public function __init() {
		// Allow people to change what capability is required to use this plugin.
		$this->permissions = apply_filters( self::PREFIX . 'permissions', $this->permissions );

		// load plugin languages
		load_plugin_textdomain( self::DOMAIN, false,
			basename( self::get_dir() ) . '/languages/' );
	}
}