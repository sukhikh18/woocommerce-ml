<?php
/**
 * C. Выгрузка на сайт файлов обмена
 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
 * D. Отправка файла обмена на сайт
 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
 *
 * Загрузка CommerceML2 файла или его части в виде POST. (Пишет поток в файл и распаковывает его)
 *
 * @print string 'success'
 */

namespace NikolayS93\Exchange;

if( ! isset( $requested ) ) {
    $requested = 'php://input';
}

$Plugin = Plugin();
$file   = Request::get_file();
// get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable().
$path_dir = get_exchange_dir( Request::get_type() );
$path     = $path_dir . '/' . $file['name'] . '.' . $file['ext'];

$from     = fopen( $requested, 'r' );
$resource = fopen( $path, 'a' );
stream_copy_to_stream( $from, $resource );
fclose( $from );
fclose( $resource );

unzip( $path, $path_dir );

if ( 'catalog' == Request::get_type() ) {
    exit( "success\n" );
}