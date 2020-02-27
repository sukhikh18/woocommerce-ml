<?php

namespace NikolayS93\Exchange;

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
		Plugin::session_start();

		Plugin::write_log( 'get', $_GET );
		Plugin::write_log( 'post', $_POST );
		Plugin::write_log( 'cookie', $_COOKIE );
		Plugin::write_log( 'session', $_SESSION, array(
			'session_name=' . session_name(),
			'session_id=' . session_id(),
		) );

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