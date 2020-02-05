<?php

interface Error {
    public function get_messages( $code = '' );
    public function is_empty( $code = '' );
    public function show_messages( $code = '' );
    public function add_message( $message, $code = 'Error', $no_exit = false );

    public static function set_strict_mode();
    static function strict_error_handler();
    static function strict_exception_handler();
}
