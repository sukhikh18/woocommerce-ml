<?php

/*
 * Plugin Name: 1C Exchange
 * Plugin URI: https://github.com/nikolays93
 * Description: New plugin boilerplate
 * Version: 0.3
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: _plugin
 * Domain Path: /languages/
 */

namespace NikolayS93\Exchange;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You shall not pass' );
}

if ( ! defined( __NAMESPACE__ . '\PLUGIN_DIR' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_DIR', dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
}

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

require_once ABSPATH . "wp-admin/includes/plugin.php";
if ( ! include_once PLUGIN_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ) {
	include PLUGIN_DIR . 'include/class/Traits/Singleton.php';
	include PLUGIN_DIR . 'include/class/Traits/IO.php';
	include PLUGIN_DIR . 'include/class/ORM/Collection.php';

	include PLUGIN_DIR . 'include/class/Model/Traits/ItemMeta.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/ExternalCode.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/HasParent.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/Identifiable.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/Taxonomy.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/Term.php';
	include PLUGIN_DIR . 'include/class/Model/Interfaces/Value.php';
	include PLUGIN_DIR . 'include/class/Model/Abstracts/Term.php';
	include PLUGIN_DIR . 'include/class/Model/Attribute.php';
	include PLUGIN_DIR . 'include/class/Model/AttributeValue.php';
	include PLUGIN_DIR . 'include/class/Model/Category.php';
	include PLUGIN_DIR . 'include/class/Model/Developer.php';
	include PLUGIN_DIR . 'include/class/Model/ExchangeOffer.php';
	include PLUGIN_DIR . 'include/class/Model/ExchangePost.php';
	include PLUGIN_DIR . 'include/class/Model/ExchangeProduct.php';
	include PLUGIN_DIR . 'include/class/Model/Warehouse.php';

	include PLUGIN_DIR . 'include/class/Error.php';
	include PLUGIN_DIR . 'include/class/Request.php';
	include PLUGIN_DIR . 'include/class/Transaction.php';
	include PLUGIN_DIR . 'include/class/Parser.php';
	include PLUGIN_DIR . 'include/class/REST_Controller.php';
	include PLUGIN_DIR . 'include/class/Plugin.php';
	include PLUGIN_DIR . 'include/class/Register.php';
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
add_action( 'plugins_loaded', function () {

	$Register = new Register();
	$Register->register_plugin_page();

	// Initialize the REST API routes.
	add_action( 'rest_api_init', 'rest_api_init' );

	add_action( 'woocommerce_attribute_deleted',
		array( $Register, 'delete_attribute_taxonomy_meta' ), 10, 3 );

	// Show external Fields
	add_action( 'woocommerce_product_options_general_product_data',
		array($Register, 'add_product_external_code_field') );

	// Save external Fields
	add_action( 'woocommerce_process_product_meta',
		array($Register, 'sanitize_product_external_code_field') );

}, 20 );

function rest_api_init() {
	$this->rest_api = new Plugin_REST_Controller();
	$this->rest_api->register_routes();
//		$this->rest_api->register_filters();
}


register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'uninstall' ) );

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
