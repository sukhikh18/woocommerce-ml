<?php

namespace NikolayS93\Exchange;

/**
 * @var string (float value)
 */
$version = get_option( 'exchange_version', '' );

$mode = Request::get_mode();
$type = Request::get_type();

if ( ! headers_sent() ) {
    header( 'Content-Type: text/plain; charset=' . CHARSET );
}

Error::set_strict_mode();

// CGI fix.
if ( ! $_GET && isset( $_SERVER['REQUEST_URI'] ) ) {
    $query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
    parse_str( $query, $_GET );
}

if ( ! in_array( $type, get_allowed_types(), true ) ) {
    Error()->add_message( 'Type no support' );
}

if ( ! in_array( $mode, get_allowed_modes(), true ) ) {
    Error()->add_message( 'Mode no support' );
}

if ( 'checkauth' !== $mode ) {
    $user = check_current_user();
    if ( is_wp_error( $user ) ) {
        Error()->add_message( $user );
    }
}

include_plugin_file( "include/mode/$mode.php" );
