<?php

namespace NikolayS93\Exchange;


use NikolayS93\Excnahge\Traits\Singleton;

class Transaction {

	use Singleton;

	private $is_transaction = false;

	public function is_transaction() {
		return $this->is_transaction;
	}

	function set_transaction_mode() {
		global $wpdb;

		Request::disable_request_time_limit();

		register_shutdown_function( array($this, 'transaction_shutdown_function') );

		$wpdb->show_errors( false );
		$wpdb->query( "START TRANSACTION" );

		$this->is_transaction = true;
		Error::check_wpdb_error();
	}

	function transaction_shutdown_function() {
		$error = error_get_last();
		$is_commit = $error['type'] > E_PARSE;

		$this->wpdb_stop($is_commit);
	}

	function wpdb_stop( $is_commit = false, $no_check = false ) {
		global $wpdb;

		if ( ! self::is_transaction() ) return;
		$this->is_transaction = false;

		$sql_query = ! $is_commit ? "ROLLBACK" : "COMMIT";
		$wpdb->query($sql_query);
		if (!$no_check) Error::check_wpdb_error();

		if (is_debug()) echo "\n" . strtolower($sql_query);
	}
}
