<?php
/**
 * A. Начало сеанса (Авторизация)
 * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
 * A. Начало сеанса
 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
 *
 * @print 'success\nCookie\nCookie_value'
 */

namespace NikolayS93\Exchange;

if ( ! is_user_logged_in() ) {
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
        Error()->add_message( 'No authentication credentials' );
    }

    $user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
    if ( is_wp_error( $user ) ) {
        Error()->add_message( $user );
    }
} else {
    $user = wp_get_current_user();
}

if ( ! has_permissions( $user ) ) {
    Error()->add_message(
        sprintf(
            'No %s user permissions',
            get_user_meta( $user->ID, 'nickname', true )
        )
    );
}

$expiration  = EXCHANGE_START_TIMESTAMP + apply_filters(
    'auth_cookie_expiration',
    DAY_IN_SECONDS,
    $user->ID,
    false
);
$auth_cookie = wp_generate_auth_cookie( $user->ID, $expiration );

exit( "success\n" . EXCHANGE_COOKIE_NAME . "\n$auth_cookie" );
