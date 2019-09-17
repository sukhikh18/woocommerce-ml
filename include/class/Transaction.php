<?php

namespace NikolayS93\Exchange;


use NikolayS93\Exchange\Traits\Singleton;

class Transaction {

    use Singleton;

    private $is_transaction = false;

    public function is_transaction() {
        return $this->is_transaction;
    }

    function set_transaction_mode() {
        global $wpdb;

        Request::disable_request_time_limit();

        register_shutdown_function( array( $this, 'transaction_shutdown_function' ) );

        $wpdb->show_errors( false );
        $wpdb->query( "START TRANSACTION" );

        $this->is_transaction = true;
        self::check_wpdb_error();
    }

    function transaction_shutdown_function() {
        $error     = error_get_last();
        $is_commit = $error['type'] > E_PARSE;

        $this->wpdb_stop( $is_commit );
    }

    /**
     * Check error from global database manager
     */
    public static function check_wpdb_error() {
        global $wpdb;

        if ( ! $wpdb->last_error ) {
            return;
        }

        Error()->add_message( sprintf( "%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query ), "Error", true );

        Transaction()->wpdb_stop( false, true );
        exit;
    }

    function wpdb_stop( $is_commit = false, $no_check = false ) {
        global $wpdb;

        if ( ! self::is_transaction() ) {
            return;
        }
        $this->is_transaction = false;

        $sql_query = ! $is_commit ? "ROLLBACK" : "COMMIT";
        $wpdb->query( $sql_query );
        if ( ! $no_check ) {
            self::check_wpdb_error();
        }

        if ( is_debug() ) {
            echo "\n" . strtolower( $sql_query );
        }
    }

}
