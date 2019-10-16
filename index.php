<?php
/**
 * Plugin Name: Woocommerce 1c Exchanger
 * Plugin URI: https://github.com/nikolays93
 * Description: Передача данных для актуализации информации на сайте
 * Version: 0.3.1
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: w1ce
 * Domain Path: /languages/
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'You shall not pass' );
}

if ( ! defined( __NAMESPACE__ . '\PLUGIN_DIR' ) ) {
	define( __NAMESPACE__ . '\PLUGIN_DIR', dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
}

/**
 * Post meta field key (name)
 */
if ( ! defined( 'EXCHANGE_EXTERNAL_CODE_KEY' ) ) {
	define( 'EXCHANGE_EXTERNAL_CODE_KEY', '_ext_ID' );
}

/**
 * Plugin auth cookie name
 */
if ( ! defined( 'EXCHANGE_COOKIE_NAME' ) ) {
	define( 'EXCHANGE_COOKIE_NAME', 'ex-auth' );
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
		if ( is_file( $path ) && is_readable( $path ) ) {
			require $path; // phpcs:ignore
		}
	}
}

/**
 * include required files
 */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
include_plugin_file( 'include/utils.php' );
if ( ! include_once PLUGIN_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ) {
	array_map(
		__NAMESPACE__ . '\include_plugin_file',
		array(
			'include/class/Traits/Singleton.php',
			'include/class/Traits/IO.php',
			'include/class/ORM/Collection.php',
			'include/class/Model/Traits/ItemMeta.php',
			'include/class/Model/Interfaces/ExternalCode.php',
			'include/class/Model/Interfaces/HasParent.php',
			'include/class/Model/Interfaces/Identifiable.php',
			'include/class/Model/Interfaces/Taxonomy.php',
			'include/class/Model/Interfaces/Term.php',
			'include/class/Model/Interfaces/Value.php',
			'include/class/Model/Abstracts/Term.php',
			'include/class/Model/Attribute.php',
			'include/class/Model/AttributeValue.php',
			'include/class/Model/Category.php',
			'include/class/Model/Developer.php',
			'include/class/Model/ExchangeOffer.php',
			'include/class/Model/ExchangePost.php',
			'include/class/Model/ExchangeProduct.php',
			'include/class/Model/Warehouse.php',
			'include/class/Error.php',
			'include/class/Request.php',
			'include/class/Transaction.php',
			'include/class/Parser.php',
			'include/class/REST_Controller.php',
			'include/class/Plugin.php',
			'include/class/Register.php',
		)
	);
}

/**
 * Returns the single instance of this plugin, creating one if needed.
 *
 * @return Plugin
 */
function plugin() {
	return Plugin::get_instance();
}

/**
 * @return Error
 */
function error() {
	return Error::get_instance();
}

/**
 * @return Transaction
 */
function transaction() {
	return Transaction::get_instance();
}

/**
 * Initialize this plugin once all other plugins have finished loading.
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin', 10 );
add_action(
	'plugins_loaded',
	function () {
		$register = new Register();
		$register->register_plugin_page();
		// Register //example.com/exchange/ query
		$register->register_exchange_url();

		// Initialize the REST API routes.
//		add_action( 'rest_api_init', function () {
//			$this->rest_api = new REST_Controller();
//			$this->rest_api->register_routes();
////		$this->rest_api->register_filters();
//		} );
//
//		add_action( 'woocommerce_attribute_deleted',
//			array( $register, 'delete_attribute_taxonomy_meta' ), 10, 3 );
//
//		// Show external Fields
//		add_action( 'woocommerce_product_options_general_product_data',
//			array( $register, 'add_product_external_code_field' ) );
//
//		// Save external Fields
//		add_action( 'woocommerce_process_product_meta',
//			array( $register, 'sanitize_product_external_code_field' ) );
	},
	20
);

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'activate' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Register', 'uninstall' ) );

add_action( 'wp_ajax_1c4wp_exchange', __NAMESPACE__ . '\ajax_1c4wp_exchange' );
function ajax_1c4wp_exchange() {
	if ( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], Plugin::DOMAIN ) ) {
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
			echo '<abbr title="' . $post->post_modified . '">' . apply_filters( 'post_date_column_time', $showTime,
					$post, 'date', $mode ) . '</abbr><br />';
		}
	}

	return $status;
}, 10, 4 );

/** @todo Change hook */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'product' === $post_type ) : ?>
        <!--		<style>-->
        <!--			body table.wp-list-table td.column-thumb img {-->
        <!--				max-width: 75px;-->
        <!--				max-height: 75px;-->
        <!--			}-->
        <!--		</style>-->
	<?php endif;
}, 10, 1 );

if ( ! function_exists( 'mb_ucfirst' ) ) {
	function mb_ucfirst( $string, $enc = 'UTF-8' ) {
		if ( function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) ) {
			return mb_strtoupper( mb_substr( $string, 0, 1, $enc ), $enc ) .
			       mb_substr( $string, 1, mb_strlen( $string, $enc ), $enc );
		}

		return ucfirst( $string );
	}
}

add_filter( 'Term::set_name', function ( $name, $obj ) {
	$name  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
	$name  = str_replace( '  ', ' ', $name );
	$_name = preg_replace( "#^[0-9.,\\\|/)(-+_\s]+#si", "", $name );

	return mb_ucfirst( $_name ? $_name : $name );
}, 10, 2 );

add_filter( 'Term::set_slug', function ( $slug, $obj ) {
	return esc_cyr( (string) $slug, false );
}, 10, 2 );
