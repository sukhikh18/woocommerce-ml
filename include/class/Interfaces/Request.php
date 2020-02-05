<?php

interface Request {
    static function save_get_request( $k );
    static function get_file_array(); // (protected) from filename request.
    static function get_file();
    static function get_type();
    static function get_mode();
    static function get_full_request_uri();
    // @todo move to Session
    static function disable_request_time_limit();
    static function is_session_started();
    static function set_session_args( $args );
    static function get_session_arg( $key, $default = null );
    static function clear_session();
    static function stop( $update = null, $messages = array(), $step = null );
}
