<?php

/*
 * Plugin Name: 1c4wp
 * Plugin URI: https://github.com/nikolays93
 * Description: 1c exchange prototype
 * Version: 0.0.1
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: _plugin
 * Domain Path: /languages/
 */

namespace NikolayS93\Plugin;

use NikolayS93\WPAdminPage as Admin;

// define('EX_DEBUG_ONLY', TRUE);

if ( !defined( 'ABSPATH' ) ) exit('You shall not pass');
if (version_compare(PHP_VERSION, '5.4') < 0) {
    throw new \Exception('Plugin requires PHP 5.4 or above');
}

if( !defined(__NAMESPACE__ . '\PLUGIN_DIR') ) define(__NAMESPACE__ . '\PLUGIN_DIR', __DIR__);
if( !defined(__NAMESPACE__ . '\PLUGIN_FILE') ) define(__NAMESPACE__ . '\PLUGIN_FILE', __FILE__);

require_once ABSPATH . "wp-admin/includes/plugin.php";
require_once PLUGIN_DIR . '/include/Creational/Singleton.php';
require_once PLUGIN_DIR . '/include/class-plugin.php';

add_action( 'plugins_loaded', function() {

    $Plugin = Plugin::getInstance();

    // $PluginRoutes = PluginRoutes::getInstance();
    // add_action( 'init', array($PluginRoutes, '__register') );

    // $PluginQueries = PluginQueries::getInstance();
    // add_action( 'pre_get_posts', array($PluginQueries, '__register') );

    // add_action( 'widgets_init', array(__NAMESPACE__ . '\PluginWidget', '__register') );

    /** @var Admin\Page */
    $Page = $Plugin->addMenuPage(__('1C Exchange', DOMAIN), array(
        'parent' => 'woocommerce',
        'menu' => __('Example', DOMAIN),
    ));

    $Page->set_assets( function() {
        wp_enqueue_style( 'exchange-page', Utils::get_plugin_url('/admin/assets/exchange-page.css') );
        wp_enqueue_script( 'exchange-requests', Utils::get_plugin_url('/admin/assets/exchange-requests.js') );
        wp_localize_script('exchange-requests', DOMAIN, array(
            'debug_only' => is_debug(),
            'exchange_url' => site_url('/exchange/'),
        ) );

        /**
         * Upload Script
         */
        wp_enqueue_script( 'exchange-upload-ui', Utils::get_plugin_url('/admin/assets/exchange-upload-ui.js') );
    } );

    $Page->set_content( function() {
        Plugin::get_admin_template('menu-page', false, $inc = true);
    } );

    $Page->add_section( new Admin\Section(
        'Section',
        __('Report', DOMAIN),
        function() {
            Plugin::get_admin_template('section', false, $inc = true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'statusbox',
        __('Status', DOMAIN),
        function() {
            Plugin::get_admin_template('statusbox', false, $inc = true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'uploadbox',
        __('Upload New Files', DOMAIN),
        function() {
            Plugin::get_admin_template('uploadbox', false, $inc = true);
        }
    ) );
}, 10 );

add_action( '1c4wp_exchange', function() {
    \NikolayS93\Exchange\Request::set_strict_mode();
    ob_start( array('\NikolayS93\Exchange\Request', 'output_callback') );

    /**
     * Check required arguments
     */
    if (!defined('TYPE') || empty(TYPE)) ex_error("No type");
    if (!defined('MODE') || empty(MODE)) ex_error("No mode");

    /**
     * CGI fix
     */
    if (!$_GET && isset($_SERVER['REQUEST_URI'])) {
        $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($query, $_GET);
    }

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
    if ('checkauth' == MODE) {
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
     * Get status (from request for debug)
     */
    $status = ( !empty($_GET['status']) ) ? Utils::get_status(intval($_GET['status'])) : Utils::get( 'status', false );

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
    if ('init' == MODE) {
        /** Zip required (if not must die) */
        check_zip();

        /**
         * Option is empty then exchange end
         * @var [type]
         */
        if( !$start = get_option( 'exception_start-date', '' ) ) {
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
    elseif ('query' == MODE) {
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
    elseif ('file' == MODE) {
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
    elseif ('import' == MODE && Utils::get_status(3) != $status ) {
        $version = get_option( 'exchange_version', '' );

        ex_set_transaction_mode();

        if( $categories = Parser::getInstance()->getCategories() ) {
            Parser::fillExistsTermData( $categories );

            Update::terms( $categories, array('taxonomy' => 'product_cat') );
            Update::update_termmetas( $categories, array('taxonomy' => 'product_cat') );
        }

        if( $properties = Parser::getInstance()->getProperties() ) {
            if( true !== ($res = Update::properties( $properties )) ) {
                exit("progress\n" . $res);
            }
        }

        if( $manufacturer = Parser::getInstance()->getManufacturer() ) {
            Parser::fillExistsTermData( $manufacturer );

            Update::terms( $manufacturer, array('taxonomy' => 'manufacturer') );
            Update::update_termmetas( $manufacturer, array('taxonomy' => 'manufacturer') );
        }

        if( $warehouses = Parser::getInstance()->getWarehouses() ) {
            Parser::fillExistsTermData( $warehouses );

            Update::terms( $warehouses, array('taxonomy' => 'warehouses') );
            Update::update_termmetas( $warehouses, array('taxonomy' => 'warehouses') );
        }

        if( $products = Parser::getInstance()->getProducts() ) {
            Parser::fillExistsProductData( $products, false );
            Update::posts( $products );
            Parser::fillExistsProductData( $products, $orphaned = true );
            // $products = $Parser->parse_exists_products( $orphaned = true );
            Update::postmetas( $products );

            Update::relationships( $products, array('taxonomy' => 'product_cat') );
            Update::relationships( $products, array('taxonomy' => 'properties') );
            Update::relationships( $products, array('taxonomy' => 'manufacturer') );
        }

        if( $offers = Parser::getInstance()->getOffers() ) {
            Parser::fillExistsProductData( $offers, false );
            Update::offers( $offers );
            Parser::fillExistsProductData( $offers, $orphaned = true );
            Update::offers( $offers );
            Update::offerPostMetas( $offers );
        }

        if (version_compare($version, '3.0') < 0 && false !== strpos(FILENAME, 'offers')) {
            $status = Utils::get_status(3);
            Utils::set( 'status', $status );

            exit("progress\nВыгрузка данных из файла успешно завершена");
        }
        else {
            exit("success\nВыгрузка данных из файла успешно завершена");
        }
    }

    /**
     * E. Деактивация данных данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
     * @since  3.0
     * @return progress|success|failure
     */
    elseif ('deactivate' == MODE || Utils::get_status(3) == $status) {
        /**
         * Reset start date
         */
        update_option( 'exchange_start-date', '' );

        /**
         * Чистим и пересчитываем количество записей в терминах
         */
                $filename = FILENAME;
        /**
         * Get valid namespace ('import', 'offers', 'orders')
         */
        // $namespace = $filename ?
        //     preg_replace("/^([a-zA-Z]+).+/", '$1', $filename) : 'import';

        // rest, prices (need debug in new sheme version)
        // if (!in_array($namespace, array('import', 'offers', 'orders'))) {
        //     ex_error( sprintf("Unknown import file type: %s", $namespace) );
        // }

        $dir = PathFinder::get_dir('catalog');

        /**
         * Get import filepath
         */
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
         */
        // if( $is_full ) {
        //     Archive::posts( $products );
        // }

        /**
         * Insert count the number of records in a category
         */
        Update::update_term_counts();

        Utils::set( array(
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
    elseif ('complete' == MODE) {

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

// register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
// register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );

// $_SERVER['PHP_AUTH_USER'] = 'wordpress';
// $_SERVER['PHP_AUTH_PW'] = 'q1w2';
// // class tax_rates
//
// $ProductCat   = new ExchangeTerm();
// $Property     = new ExchangeTerm();
// /** @var ExchangeTerm Need parse values */
// $Developer    = new ExchangeTerm();
// $Warehouse    = new ExchangeTerm();
//
// $Product      = new ExchangePost();
// $Offer        = new ExchangePost();