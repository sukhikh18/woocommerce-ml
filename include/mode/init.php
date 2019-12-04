<?php
/**
 * B. Запрос параметров от сайта
 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=init
 * B. Уточнение параметров сеанса
 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=init
 *
 * @print
 * zip=yes|no - Сервер поддерживает Zip
 * file_limit=<число> - максимально допустимый размер файла в байтах для передачи за один запрос
 */

namespace NikolayS93\Exchange;

$skip_zip_checks = false;

if ( ! $skip_zip_checks ) {
    $is_zip = check_zip_extension();
    if ( is_wp_error( $is_zip ) ) {
        error()->add_message( $is_zip );
    }
}

/**
 * Option is empty then exchange end
 *
 * @var [type]
 */
if ( ! $start = get_option( 'exchange_start-date', '' ) ) {
    /**
     * Refresh exchange version
     *
     * @var float isset($_GET['version']) ? ver >= 3.0 : ver <= 2.99
     */
    update_option( 'exchange_version', ! empty( $_GET['version'] ) ? $_GET['version'] : '' );

    /**
     * Set start wp date sql format
     */
    update_option( 'exchange_start-date', current_time( 'mysql' ) );

    Request::reset_mode();
}

exit( "zip=yes\nfile_limit=" . wp_max_upload_size() );