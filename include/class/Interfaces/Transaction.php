<?php

interface Transaction {
    public function is_transaction();
    function set_transaction_mode();
    function transaction_shutdown_function();
    public static function check_wpdb_error();
    function wpdb_stop( $is_commit = false, $no_check = false );
}
