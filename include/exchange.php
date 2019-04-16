<?php

namespace NikolayS93\Exchange;

function get_mode($status, $version)
{
    if( in_array(MODE, array('checkauth', 'init', 'file')) ) return MODE;

    // if( version_compare($version, '3.0') < 0 ) {
    //     if( Utils::get_status(4) == $status ) {
    //         return 'deactivate';
    //     }
    // }

    return MODE;
}

add_action( '1c4wp_exchange', function() {
    start_exchange_session();

    /**
     * Check required arguments
     */
    if (!defined(__NAMESPACE__ . '\TYPE') || empty(TYPE)) ex_error("No type");
    if (!defined(__NAMESPACE__ . '\MODE') || empty(MODE)) ex_error("No mode");

    /**
     * CGI fix
     */
    if (!$_GET && isset($_SERVER['REQUEST_URI'])) {
        $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($query, $_GET);
    }

    $Plugin = Plugin::getInstance();

    /**
     * Get status (from request for debug)
     */
    $status = ( !empty($_GET['status']) ) ? Utils::get_status(intval($_GET['status'])) : $Plugin->get( 'status', false );

    /**
     * CommerceML protocol version
     * @var string (float value)
     */
    $version = get_option( 'exchange_version', '' );

    $mode = get_mode($status, $version);

    /**
     * @url http://v8.1c.ru/edi/edi_stnd/131/
     *
     * A. Начало сеанса (Авторизация)
     * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
     * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
     *
     * A. Начало сеанса
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
     * @return success\nCookie\nCookie_value
     */
    if ('checkauth' == get_mode($status, $version)) {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $server_key) {
            if (!isset($_SERVER[$server_key])) continue;

            list(, $auth_value) = explode(' ', $_SERVER[$server_key], 2);
            $auth_value = base64_decode($auth_value);
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $auth_value);

            break;
        }

        if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))
            ex_error("No authentication credentials");

        $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if ( is_wp_error($user) ) ex_wp_error($user);
        check_user_permissions($user);

        $expiration = time() + apply_filters('auth_cookie_expiration', DAY_IN_SECONDS, $user->ID, false);
        $auth_cookie = wp_generate_auth_cookie($user->ID, $expiration);

        exit("success\nex-auth\n$auth_cookie");
    }

    check_wp_auth();

    /**
     * B. Запрос параметров от сайта
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=init
     * B. Уточнение параметров сеанса
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=init
     *
     * @return
     * zip=yes|no - Сервер поддерживает Zip
     * file_limit=<число> - максимально допустимый размер файла в байтах для передачи за один запрос
     */
    if ('init' == get_mode($status, $version)) {
        /** Zip required (if not must die) */
        check_zip();

        /**
         * Option is empty then exchange end
         * @var [type]
         */
        if( !$start = get_option( 'exchange_start-date', '' ) ) {
            /**
             * Refresh exchange version
             * @var float isset($_GET['version']) ? ver >= 3.0 : ver <= 2.99
             */
            update_option( 'exchange_version', !empty($_GET['version']) ? $_GET['version'] : '' );

            /**
             * Set start wp date sql format
             */
            update_option( 'exchange_start-date', date('Y-m-d H:i:s') );
        }

        exit("zip=yes\nfile_limit=" . get_maxsizelimit());
    }

    /**
     * C. Получение файла обмена с сайта
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=query.
     */
    elseif ('query' == $mode) {
        // ex_mode__query($_REQUEST['type']);
    }

    /**
     * C. Выгрузка на сайт файлов обмена
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
     * D. Отправка файла обмена на сайт
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
     *
     * Загрузка CommerceML2 файла или его части в виде POST.
     * @return success
     */
    elseif ('file' == $mode) {
        /**
         * Принимает файл и распаковывает его
         */
        $filename = FILENAME;
        $path_dir = PathFinder::get_dir( TYPE );

        if ( !empty($filename) ) {
            $path = $path_dir . ltrim($filename, "./\\");

            $input_file = fopen("php://input", 'r');
            $temp_path = "$path~";
            $temp_file = fopen($temp_path, 'w');
            stream_copy_to_stream($input_file, $temp_file);

            if ( is_file($path) ) {
                $temp_header = file_get_contents($temp_path, false, null, 0, 32);
                if (strpos($temp_header, "<?xml ") !== false) unlink($path);
            }

            $temp_file = fopen($temp_path, 'r');
            $file = fopen($path, 'a');
            stream_copy_to_stream($temp_file, $file);
            fclose($temp_file);
            unlink($temp_path);

            if( 0 == filesize( $path ) ) {
                ex_error( sprintf("File %s is empty", $path) );
            }
        }

        $zip_paths = glob("$path_dir/*.zip");

        $r = ex_unzip( $zip_paths, $path_dir, $remove = true );
        if( true !== $r ) ex_error($r);

        if ('catalog' == $_REQUEST['type']) exit("success\nФайл принят.");
    }

     /**
     * D. Пошаговая загрузка данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
     * @return progress|success|failure
     */
    elseif ('import' == $mode) {
        ex_set_transaction_mode();

        /**
         * Parse
         */
        $Parser = Parser::getInstance( $fillExists = true );

        $categories = $Parser->getCategories();
        $properties = $Parser->getProperties();
        $developers = $Parser->getDevelopers();
        $warehouses = $Parser->getWarehouses();

        $products = $Parser->getProducts();
        $offers = $Parser->getOffers();

        $attributeValues = array();
        foreach ($properties as $property)
        {
            /** Collection to simple array */
            foreach ($property->getTerms() as $term)
            {
                $attributeValues[] = $term;
            }
        }

        /**
         * Write
         */
        Update::terms( $categories );
        Update::termmeta( $categories );

        Update::terms( $developers );
        Update::termmeta( $developers );

        Update::terms( $warehouses );
        Update::termmeta( $warehouses );

        Update::properties( $properties );

        Update::terms( $attributeValues );
        Update::termmeta( $attributeValues );

        $pluginMode = '';
        if( !empty($products) || !empty($offers) ) {

            if( 'relationships' != ($pluginMode = $Plugin->get('mode')) ) {
                Update::posts( $products );
                Update::postmeta( $products );

                Update::offers( $offers );
                Update::offerPostMetas( $offers );

                $Plugin->set('mode', 'relationships');
                exit("progress\nNeed_relationships");
            }
            else {
                Update::relationships( $products );
                Update::relationships( $offers );

                $Plugin->set('mode', '');
            }
        }

        exit("success\nStatus: $status\nMode: $mode\n$pluginMode");
    }

    /**
     * E. Деактивация данных данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
     * @since  3.0
     * @return progress|success|failure
     */
    elseif ('deactivate' == $mode) {
        /**
         * Reset start date
         */
        update_option( 'exchange_start-date', '' );

        /**
         * Чистим и пересчитываем количество записей в терминах
         * /
        $filename = FILENAME;

        /**
         * Get valid namespace ('import', 'offers', 'orders')
         * /
        // $namespace = $filename ?
        //     preg_replace("/^([a-zA-Z]+).+/", '$1', $filename) : 'import';

        // rest, prices (need debug in new sheme version)
        // if (!in_array($namespace, array('import', 'offers', 'orders'))) {
        //     ex_error( sprintf("Unknown import file type: %s", $namespace) );
        // }

        $dir = PathFinder::get_dir('catalog');

        /**
         * Get import filepath
         * /
        if( $filename && is_readable($dir . $filename) ) {
            $path = $dir . $filename;
        }
        else {
            $filename = PathFinder::get_files( $namespace );
            // check in once
            $path = current($filename);
        }

        list($is_full, $is_moysklad) = ex_check_head_meta($path);


        $catalogFiles = PathFinder::get_files();
        $bkpDir = PathFinder::get_dir( '_backup' );

        foreach ($catalogFiles as $i => $file) {
            // remove
            // @unlink($file); or move:
            rename( $file, $bkpDir . basename($file));
        }

        /**
         * Need products to archive
         * /
        // if( $is_full ) {
        //     Archive::posts( $products );
        // }

        /**
         * Insert count the number of records in a category
         * /
        Update::update_term_counts();
        */
        $Plugin->set( array(
            'status' => Utils::get_status(0),
            'last_update' => date('Y-m-d H:i:s'),
        ) );

        exit("success\nдеактивация товаров завершена");
    }

    /**
     * F. Завершающее событие загрузки данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=complete
     * @since  3.0
     */
    elseif ('complete' == $mode) {

        exit("success\nВыгрузка данных завершена");
    }

    /**
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=success
     */
    elseif ('success' == MODE) {
        // ex_mode__success($_REQUEST['type']);
    }

    else {
        ex_error("Unknown mode");
    }

    /** Need early end */
    exit("failure\n". 'with status: ' . $status);

} );