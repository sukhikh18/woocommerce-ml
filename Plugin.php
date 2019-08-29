<?php

/*
 * Plugin Name: 1C Exchange
 * Plugin URI: https://github.com/nikolays93
 * Description: New plugin boilerplate
 * Version: 0.0.6
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: _plugin
 * Domain Path: /languages/
 */

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Creational\Singleton;

if ( ! defined( 'EXCHANGE_EXTERNAL_CODE_KEY' ) ) {
	define( 'EXCHANGE_EXTERNAL_CODE_KEY', 'EXT' );
}

/**
 * Plugin auth cookie name
 */
if ( ! defined( 'EXCHANGE_COOKIE_NAME' ) ) {
	define( 'EXCHANGE_COOKIE_NAME', 'ex-auth' );
}

/**
 * Current timestamp
 */
if ( ! defined( 'EXCHANGE_START_TIMESTAMP' ) ) {
	define( 'EXCHANGE_START_TIMESTAMP', time() );
}

/**
 * Work with charset
 */
if ( ! defined( 'EXCHANGE_XML_CHARSET' ) ) {
	define( 'EXCHANGE_XML_CHARSET', 'UTF-8' );
}

require_once __DIR__ . '/vendor/autoload.php';

class Plugin {
	use Singleton;

	/**
	 * Path to this file
	 */
	const FILE = __FILE__;

	/**
	 * Path to plugin directory
	 */
	const DIR = __DIR__;

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
	protected $permissions = 'manage_options';

	/**
	 * The instance of the REST API controller class used to extend the REST API.
	 *
	 * @var Plugin_REST_Controller
	 */
	public $rest_api;

	public static function set_mime_type_indexes() {
		global $wpdb;

		/**
		 * Maybe insert posts mime_type INDEX if is not exists
		 */
		$postMimeIndexName = 'id_post_mime_type';
		$result            = $wpdb->get_var( "SHOW INDEX FROM $wpdb->posts WHERE Key_name = '$postMimeIndexName';" );
		if ( ! $result ) {
			return $wpdb->query( "ALTER TABLE $wpdb->posts
				ADD INDEX $postMimeIndexName (ID, post_mime_type(78))" );
		}

		return false;
	}

	public static function create_taxonomy_meta_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * Maybe create taxonomy meta table
		 */
		$taxonomymeta = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$taxonomymeta'" ) != $taxonomymeta ) {
			/** Required for dbDelta */
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			dbDelta( "CREATE TABLE {$taxonomymeta} (
                `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `tax_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) NULL,
                `meta_value` longtext NULL
            ) {$charset_collate};" );

			$wpdb->query( "
                ALTER TABLE {$taxonomymeta}
                    ADD INDEX `tax_id` (`tax_id`),
                    ADD INDEX `meta_key` (`meta_key`(191));" );
		}
	}

	/**
	 * Call this method before activate plugin
	 */
	public static function activate() {
		/**
		 * Create empty folder for (temporary) exchange data
		 */
		$dir = get_exchange_data_dir();
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir );
		}

		self::set_mime_type_indexes();
		self::create_taxonomy_meta_table();
	}

	/**
	 * Call this method before disable plugin
	 */
	public static function deactivate() {
	}

	/**
	 * Call this method before delete plugin
	 */
	public static function uninstall() {
	}

	/**
	 * Get option name for a options in the Wordpress database
	 */
	public static function get_option_name( $suffix = '' ) {
		$option_name = $suffix ? self::PREFIX . $suffix : substr(self::PREFIX, 0, -1);

		return apply_filters( self::PREFIX . 'get_option_name', $option_name, $suffix );
	}

	/**
	 * Get plugin url
	 *
	 * @param string $path path must be started from / (also as __DIR__)
	 *
	 * @return string
	 */
	public static function get_plugin_url( $path = '' ) {
		$url = plugins_url( basename( self::DIR ) ) . $path;

		return apply_filters( self::PREFIX . 'get_plugin_url', $url, $path );
	}

	/**
	 * Get plugin template (and include with $data maybe)
	 *
	 * @param  [type]  $template [description]
	 * @param array $data @todo
	 *
	 * @return string|false
	 */
	public static function get_template( $template, $data = array(), $include = false ) {
		if ( false !== ($pos = strrpos( $template, '.' )) ) {
			$template = substr($template, 0, $pos - 1);
			$ext = substr($template, $pos + 1);
		} else {
			$ext = 'php';
		}

		$path = self::DIR . "/$template.$ext";
		if( file_exists( $path ) && is_readable( $path ) ) {
			if( !empty($data) && is_array($data) ) {
				extract( $data, EXTR_SKIP );
			}

			if ( $include ) {
				include $path;
			}

			return $path;
		}

		return false;
	}

	/**
	 * Get plugin setting from cache or database
	 *
	 * @param string $prop_name Option key or null (for a full request)
	 * @param mixed $default What's return if field value not defined.
	 *
	 * @return mixed
	 *
	 */
	public static function get_setting( $prop_name = null, $default = false, $context = '' ) {
		$option_name = static::get_option_name( $context );

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
	 * @param string $value           value for $prop_name string key
	 * @param string $context
	 *
	 * @return bool                   Is updated @see update_option()
	 *
	 */
	public static function set_setting( $prop_name = null, $value = '', $context = '' ) {
		if ( ! $prop_name || ( $value && ! (string) $prop_name ) ) {
			return false;
		}

		if ( ! is_array( $prop_name ) ) {
			$prop_name = array( (string) $prop_name => $value );
		}

		$option = static::get_setting( null, false, $context );

		foreach ( $prop_name as $prop_key => $prop_value ) {
			$option[ $prop_key ] = $prop_value;
		}

		if ( ! empty( $option ) ) {
			$option_name = static::get_option_name( $context );
			// Do not auto load for plugin settings (default)
			$autoload    = ! $context ? 'no' : null;

			return update_option( $option_name, $option,
				apply_filters( self::PREFIX . 'autoload', $autoload, $option, $context ) );
		}

		return false;
	}

	/**
	 * Register all of the needed hooks and actions.
	 */
	public function register() {
		// Initialize the REST API routes.
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );

		$Register = new Register();
		$Register->register_plugin_page();

		add_action( 'woocommerce_attribute_deleted',
			array( $Register, 'delete_attribute_taxonomy_meta' ), 10, 3 );

		// Show external Fields
		add_action( 'woocommerce_product_options_general_product_data',
			array($Register, 'add_product_external_code_field') );

		// Save external Fields
		add_action( 'woocommerce_process_product_meta',
			array($Register, 'sanitize_product_external_code_field') );
	}

	public function rest_api_init() {
		$this->rest_api = new Plugin_REST_Controller();
		$this->rest_api->register_routes();
//		$this->rest_api->register_filters();
	}

	/**
	 * Load plugin
	 */
	public function __init() {
		// Allow people to change what capability is required to use this plugin.
		$this->permissions = apply_filters( self::PREFIX . 'permissions', $this->permissions );

		// load plugin languages
		load_plugin_textdomain( self::DOMAIN, false,
			basename( self::DIR ) . '/languages/' );

		$this->register();
	}

	/**
	 * Allowed modes from GET
	 *
	 * @return array
	 */
	function get_allowed_modes() {
		$allowed = apply_filters('get_allowed_modes', array('checkauth', 'init'));

		return $allowed;
	}

	/**
	 * @return string
	 */
	function get_mode() {
		$mode = self::get_setting( 'mode', false, 'status' );
		$current_mode = save_get_request( 'mode' );

		if ( ! in_array( $current_mode, self::get_allowed_modes() ) && $mode ) {
			$current_mode = $mode;
		}

		return $current_mode;
	}

	/**
	 * @param $mode
	 * @param array $args
	 *
	 * @return bool
	 */
	function set_mode( $mode, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'mode'     => $mode,
			'progress' => 0,
		) );

		return self::set_setting( $args, null, 'status' );
	}
}

/**
 * Returns the single instance of this plugin, creating one if needed.
 *
 * @return Plugin
 */
function Plugin() {
	return Plugin::get_instance();
}

/**
 * Initialize this plugin once all other plugins have finished loading.
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\Plugin', 10 );

register_activation_hook( Plugin::FILE, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
register_deactivation_hook( Plugin::FILE, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );
register_uninstall_hook( Plugin::FILE, array( __NAMESPACE__ . '\Plugin', 'uninstall' ) );

/**
 * Register //example.com/exchange/ query
 */
add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );
function query_vars( $query_vars ) {
	$query_vars[] = 'ex1с';

	return $query_vars;
}

add_action( 'init', __NAMESPACE__ . '\query_map', 1000 );
function query_map() {
	add_rewrite_rule( "exchange", "index.php?ex1с=exchange", 'top' );
	// add_rewrite_rule("clean", "index.php?ex1с=clean");

	flush_rewrite_rules();
}

add_action( 'template_redirect', __NAMESPACE__ . '\template_redirect', - 10 );
function template_redirect() {
	$value = get_query_var( 'ex1с' );
	if ( empty( $value ) ) {
		return;
	}

	if ( false !== strpos( $value, '?' ) ) {
		list( $value, $query ) = explode( '?', $value, 2 );
		parse_str( $query, $query );
		$_GET = array_merge( $_GET, $query );
	}

	if ( $value == 'exchange' ) {
		do_action( '1c4wp_exchange' );
	}
	// elseif ($value == 'clean') {
	//     // require_once PLUGIN_DIR . "/include/clean.php";
	//     exit;
	// }
}

add_action( 'wp_ajax_1c4wp_exchange', __NAMESPACE__ . '\ajax_1c4wp_exchange' );
function ajax_1c4wp_exchange() {
	if ( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], DOMAIN ) ) {
		echo 'Ошибка! нарушены правила безопасности';
		wp_die();
	}

	do_action( '1c4wp_exchange' );
	wp_die();
}

add_filter( 'exchange_posts_import_offset', __NAMESPACE__ . '\metas_exchange_posts_import_offset', 10, 4 );
function metas_exchange_posts_import_offset( $offset, $productsCount, $offersCount, $filename ) {
	if ( 0 === strpos( $filename, 'rest' ) || 0 === strpos( $filename, 'price' ) ) {
		$offset = 1000;
	}

	return $offset;
}
add_filter( 'post_date_column_status', function ( $status, $post, $strDate, $mode ) {
	if ( 'product' != $post->post_status ) {
		return $status;
	}

	if ( $post->post_date < $post->post_modified && 'future' !== $post->post_status ) {

		if ( 'publish' === $post->post_status ) {
			$time      = get_post_modified_time( 'G', true, $post );
			$time_diff = time() - $time;

			if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
				$showTime = sprintf( __( '%s ago' ), human_time_diff( $time ) );
			} else {
				$showTime = mysql2date( __( 'Y/m/d' ), $time );
			}

			echo __( 'Last Modified' ) . '<br />';

			/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
			echo '<abbr title="' . $post->post_modified . '">' . apply_filters( 'post_date_column_time', $showTime, $post, 'date', $mode ) . '</abbr><br />';
		}
	}

	return $status;
}, 10, 4 );

/** @var @todo Change hook */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'product' == $post_type ) {
		?>
		<style>
			body table.wp-list-table td.column-thumb img {
				max-width: 75px;
				max-height: 75px;
			}
		</style>
		<?php
	}
}, 10, 1 );

function strict_error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {
	if ( 0 === error_reporting() ) {
		return false;
	}

	switch ( $errno ) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$type = "Notice";
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$type = "Warning";
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$type = "Fatal Error";
			break;
		default:
			$type = "Unknown Error";
			break;
	}

	$message = sprintf( "%s in %s on line %d", $errstr, $errfile, $errline );
	Utils::error( $message, "PHP $type" );
}

function strict_exception_handler( $exception ) {
	$message = sprintf( "%s in %s on line %d", $exception->getMessage(), $exception->getFile(), $exception->getLine() );
	Utils::error( $message, "Exception" );
}

function output_callback( $buffer ) {
	global $ex_is_error;

	if ( ! headers_sent() ) {
		$is_xml       = @$_GET['mode'] == 'query';
		$content_type = ! $is_xml || $ex_is_error ? 'text/plain' : 'text/xml';
		header( "Content-Type: $content_type; charset=" . XML_CHARSET );
	}

	$buffer = ( XML_CHARSET == 'UTF-8' ) ? "\xEF\xBB\xBF$buffer" : mb_convert_encoding( $buffer, XML_CHARSET, 'UTF-8' );

	return $buffer;
}

function transaction_shutdown_function() {
	$error = error_get_last();

	$is_commit = ! isset( $error['type'] ) || $error['type'] > E_PARSE;

	Utils::wpdb_stop( $is_commit );
}

function do_exchange() {
	/**
	 * @global $wpdb
	 */
	global $wpdb;

	/**
	 * Start buffer in strict mode
	 */
	Plugin::start_exchange_session();

	/**
	 * Check required arguments
	 */
	if ( ! $type = Plugin::get_type() ) {
		Plugin::error( "No type" );
	}

	if ( ! $mode = Plugin::get_mode() ) {
		Plugin::error( "No mode" );
	}

	if ( 'catalog' != $type ) {
		Plugin::error( "Type no support" );
	}

	/**
	 * CGI fix
	 */
	if ( ! $_GET && isset( $_SERVER['REQUEST_URI'] ) ) {
		$query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
		parse_str( $query, $_GET );
	}

	/**
	 * @url http://v8.1c.ru/edi/edi_stnd/131/
	 *
	 * A. Начало сеанса (Авторизация)
	 * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
	 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
	 *
	 * A. Начало сеанса
	 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
	 * @return 'success\nCookie\nCookie_value'
	 */
	if ( 'checkauth' == $mode ) {
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $server_key ) {
			if ( ! isset( $_SERVER[ $server_key ] ) ) {
				continue;
			}

			list( , $auth_value ) = explode( ' ', $_SERVER[ $server_key ], 2 );
			$auth_value = base64_decode( $auth_value );
			list( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) = explode( ':', $auth_value );

			break;
		}

		if ( ! isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			Plugin::error( "No authentication credentials" );
		}

		$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		if ( is_wp_error( $user ) ) {
			Plugin::wp_error( $user );
		}
		Plugin::check_user_permissions( $user );

		$expiration  = TIMESTAMP + apply_filters( 'auth_cookie_expiration', DAY_IN_SECONDS, $user->ID, false );
		$auth_cookie = wp_generate_auth_cookie( $user->ID, $expiration );

		exit( "success\n" . COOKIENAME . "\n$auth_cookie" );
	}

	check_wp_auth();

}