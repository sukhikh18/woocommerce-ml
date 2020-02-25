<?php

namespace NikolayS93\Exchange;

if ( ! function_exists( 'write_log' ) ) {
	function write_log($file, $args, $advanced = array()) {
		if( empty($args) ) return;

		if( is_array( $args ) ) {
			$arRes = array();
			foreach ($args as $key => $value) {
				$arRes[] = "$key=$value";
			}

			$args = implode(', ', $arRes);
		}

		$fw = fopen($file, "a");
		fwrite($fw, '[' . date('d.M.Y H:i:s') . "] " . $args . implode(', ', $advanced) . "\r\n");
		fclose($fw);
	}
}

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
		if ( ! is_session_started() ) {
			session_start();
		}

		write_log(PLUGIN_DIR . "/logs/get.log", $_GET);
		write_log(PLUGIN_DIR . "/logs/post.log", $_POST);
		write_log(PLUGIN_DIR . "/logs/cookie.log", $_COOKIE);
		write_log(PLUGIN_DIR . "/logs/session.log", $_SESSION, array(
			'session_name=' . session_name(),
			'session_id=' . session_id(),
		));

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