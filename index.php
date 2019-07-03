<?php

/*
 * Plugin Name: 1c4wp
 * Plugin URI: https://github.com/nikolays93
 * Description: 1c exchange prototype
 * Version: 0.2
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 1c4wp
 * Domain Path: /languages/
 */

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\ExchangeOffer;

// for debug
// $_SERVER['PHP_AUTH_USER'] = 'root';
// $_SERVER['PHP_AUTH_PW'] = 'q1w2';

// define('EX_DEBUG_ONLY', TRUE);

if ( !defined( 'ABSPATH' ) ) exit('You shall not pass');
if (version_compare(PHP_VERSION, '5.4') < 0) {
    throw new \Exception('Plugin requires PHP 5.4 or above');
}

if( defined(__NAMESPACE__ . '\PLUGIN_DIR') || defined(__NAMESPACE__ . '\PLUGIN_FILE') ) return;

if( !defined(__NAMESPACE__ . '\PLUGIN_DIR') ) define(__NAMESPACE__ . '\PLUGIN_DIR', __DIR__);
if( !defined(__NAMESPACE__ . '\PLUGIN_FILE') ) define(__NAMESPACE__ . '\PLUGIN_FILE', __FILE__);

require_once ABSPATH . "wp-admin/includes/plugin.php";
require_once PLUGIN_DIR . '/vendor/autoload.php';

define(__NAMESPACE__ . '\DEFAULT_DEVELOPER_TAX_SLUG', 'developer');
define(__NAMESPACE__ . '\DEFAULT_WAREHOUSE_TAX_SLUG', 'warehouse');

/**
 * Uniq prefix
 */
define(__NAMESPACE__ . '\DOMAIN', Plugin::get_plugin_data('TextDomain'));

/**
 * Server can get max size
 * @todo set to filter
 */
if(!defined(__NAMESPACE__ . '\FILE_LIMIT')) define(__NAMESPACE__ . '\FILE_LIMIT', null);

/**
 * Work in charset
 */
if(!defined(__NAMESPACE__ . '\XML_CHARSET') ) define(__NAMESPACE__ . '\XML_CHARSET', 'UTF-8');

/**
 * Notice type
 * @todo check this
 */
if (!defined(__NAMESPACE__ . '\SUPPRESS_NOTICES')) define(__NAMESPACE__ . '\SUPPRESS_NOTICES', false);

/**
 * Simple products only
 * @todo check this
 */
if (!defined(__NAMESPACE__ . '\DISABLE_VARIATIONS')) define(__NAMESPACE__ . '\DISABLE_VARIATIONS', false);

/**
 * Current timestamp
 */
if (!defined(__NAMESPACE__ . '\TIMESTAMP')) define(__NAMESPACE__ . '\TIMESTAMP', time());

/**
 * Auth cookie name
 */
if (!defined(__NAMESPACE__ . '\COOKIENAME')) define(__NAMESPACE__ . '\COOKIENAME', 'ex-auth');

/**
 * Woocommerce currency for single price type
 * @todo move to function
 */
if (!defined(__NAMESPACE__ . '\CURRENCY')) define(__NAMESPACE__ . '\CURRENCY', null);

if(!defined('NikolayS93\Exchange\Model\EXT_ID')) define('NikolayS93\Exchange\Model\EXT_ID', 'EXT_ID');
if (!defined('EX_EXT_METAFIELD')) define('EX_EXT_METAFIELD', 'EXT_ID');

require_once PLUGIN_DIR . '/.register.php';

add_filter( 'exchange_posts_import_offset', __NAMESPACE__ . '\metas_exchange_posts_import_offset', 10, 4 );
function metas_exchange_posts_import_offset($offset, $productsCount, $offersCount, $filename) {
    if( 0 === strpos($filename, 'rest') || 0 === strpos($filename, 'price') ) {
        $offset = 1000;
    }

    return $offset;
}

add_action( '1c4wp_exchange', __NAMESPACE__ . '\doExchange', 10 );
function doExchange() {
    /**
     * @global $wpdb
     */
    global $wpdb;

    /**
     * Start buffer in strict mode
     */
    Utils::start_exchange_session();

    /**
     * Check required arguments
     */
    if ( !$type = Utils::get_type() ) Utils::error("No type");
    if ( !$mode = Utils::get_mode() ) Utils::error("No mode");

    if( 'catalog' != $type ) Utils::error("Type no support");

    /**
     * CGI fix
     */
    if ( !$_GET && isset($_SERVER['REQUEST_URI']) ) {
        $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($query, $_GET);
    }

    /**
     * CommerceML protocol version
     * @var string (float value)
     */
    $version = get_option( 'exchange_version', '' );

    /**
     * @url http://v8.1c.ru/edi/edi_stnd/131/
     *
     * A. Начало сеанса (Авторизация)
     * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
     * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
     *
     * A. Начало сеанса
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
     * @return 'success\nCookie\nCookie_value'
     */
    if ( 'checkauth' == $mode ) {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $server_key) {
            if ( !isset($_SERVER[ $server_key ]) ) continue;

            list(, $auth_value) = explode(' ', $_SERVER[$server_key], 2);
            $auth_value = base64_decode($auth_value);
            list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', $auth_value);

            break;
        }

        if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            Utils::error("No authentication credentials");
        }

        $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if ( is_wp_error($user) ) Utils::wp_error($user);
        Utils::check_user_permissions($user);

        $expiration = TIMESTAMP + apply_filters('auth_cookie_expiration', DAY_IN_SECONDS, $user->ID, false);
        $auth_cookie = wp_generate_auth_cookie($user->ID, $expiration);

        exit("success\n". COOKIENAME ."\n$auth_cookie");
    }

    Utils::check_wp_auth();

    switch ( $mode ) {
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
        case 'init':
            /** Zip required (if no - must die) */
            Utils::check_zip();

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
                update_option( 'exchange_start-date', current_time('mysql') );

                Utils::setMode('');
            }

            exit("zip=yes\nfile_limit=" . Utils::get_filesize_limit());
        break;

        /**
         * C. Получение файла обмена с сайта
         * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=query.
         */
        case 'query':
            // ex_mode__query($_REQUEST['type']);
        break;

        /**
         * C. Выгрузка на сайт файлов обмена
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
         * D. Отправка файла обмена на сайт
         * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
         *
         * Загрузка CommerceML2 файла или его части в виде POST.
         * @return success
         */
        case 'file':
            /**
             * Принимает файл и распаковывает его
             */
            $filename = Utils::get_filename();
            $path_dir = Parser::getDir( Utils::get_type() );

            if ( !empty($filename) ) {
                $path = $path_dir . '/' . ltrim($filename, "./\\");

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
                    Utils::error( sprintf("File %s is empty", $path) );
                }
            }

            $zip_paths = glob("$path_dir/*.zip");

            $r = Utils::unzip( $zip_paths, $path_dir, $remove = true );
            if( true !== $r ) Utils::error($r);

            if ('catalog' == Utils::get_type()) exit("success\nФайл принят.");
        break;

        /**
         * D. Пошаговая загрузка данных
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
         * @return 'progress|success|failure'
         */
        case 'import':
        // case 'products':
        case 'relationships':
            if( !$filename = Utils::get_filename() ) {
                Utils::error( "Filename is empty" );
            }

            /**
             * Parse
             */
            $files  = Parser::getFiles( $filename );
            $Parser = Parser::getInstance();
            $Parser->__parse($files);
            $Parser->__fillExists();

            $products = $Parser->getProducts();
            $offers = $Parser->getOffers();
            $productsCount = sizeof( $products );
            $offersCount = sizeof( $offers );

            /** @var $progress int Offset from */
            $progress = intval( Plugin::get('progress', 0) );

            $categories = $Parser->getCategories();
            $properties = $Parser->getProperties();
            $developers = $Parser->getDevelopers();
            $warehouses = $Parser->getWarehouses();

            $attributeValues = array();
            foreach ($properties as $property)
            {
                /** Collection to simple array */
                foreach ($property->getTerms() as $term)
                {
                    $attributeValues[] = $term;
                }
            }

            // if( 'import' == $mode ) {

                /**
                 * Write terms and meta
                 */
                // if( !empty($categories) ||
                //     !empty($properties) ||
                //     !empty($developers) ||
                //     !empty($warehouses) ||
                //     !empty($attributeValues) ) {
                    // Utils::set_transaction_mode();

                    Update::terms( $categories );
                    Update::termmeta( $categories );

                    Update::terms( $developers );
                    Update::termmeta( $developers );

                    Update::terms( $warehouses );
                    Update::termmeta( $warehouses );

                    Update::properties( $properties );

                    /**
                     * Create attribute terms
                     */
                    Update::terms( $attributeValues );
                    Update::termmeta( $attributeValues );
                // }
            // }

            /**
             * If terms updated mode == products
             */
            if( 'import' == $mode || 'products' == $mode ) {
                /**
                 * Write products and offers, with meta
                 */

                /** @recursive update if is $productsCount > $offset */
                if( $productsCount > $progress ) {
                    // Utils::set_transaction_mode();
                    $offset = apply_filters('exchange_posts_import_offset', 500, $productsCount, $offersCount, $filename);

                    /**
                     * Slice products who offset better
                     */
                    $products = array_slice($products, $progress, $offset);

                    /** Count products will be updated */
                    $progress += sizeof( $products );

                    /** Require retry */
                    if( $progress < $productsCount ) {
                        Utils::setMode('', array('progress' => (int) $progress));
                    }
                    /** Go away */
                    else {
                        Utils::setMode('relationships');
                    }

                    $resProducts = Update::posts( $products );
                    $resProductsMeta = array('update' => 0);

                    // has new products without id
                    if( 0 < $resProducts['create'] ) {
                        ExchangeProduct::fillExistsFromDB( $products, $orphaned_only = true );
                        $resProductsMeta = Update::postmeta( $products );
                    }

                    $status = array();
                    $status[] = "$progress из $productsCount записей товаров обработано.";
                    $status[] = $resProducts['create'] . " товаров добавлено.";
                    $status[] = $resProducts['update'] . " товаров обновлено.";
                    $status[] = $resProductsMeta['update'] . " произвольных записей товаров обновлено.";

                    $msg = implode(' -- ', $status);

                    exit("progress\n$msg");
                }

                /** @recursive update if is $offersCount > $offset */
                if( $offersCount > $progress ) {
                    // Utils::set_transaction_mode();
                    $offset = apply_filters('exchange_posts_offers_offset', 1000, $productsCount, $offersCount, $filename);
                    /**
                     * Slice offers who offset better
                     */
                    $offers = array_slice($offers, $progress, $offset);

                    /** Count offers who will be updated */
                    $progress += sizeof($offers);

                    $answer = 'progress';

                    /** Require retry */
                    if( $progress < $offersCount ) {
                        Utils::setMode('', array('progress' => (int) $progress));
                    }
                    /** Go away */
                    else {
                        if( 0 === strpos($filename, 'offers') ) {
                            Utils::setMode('relationships');
                        }
                        else {
                            $answer = 'success';
                            Utils::setMode('');
                        }
                    }

                    $resOffers = Update::offers( $offers );

                    // has new products without id
                    if( 0 < $resOffers['create'] ) {
                        ExchangeOffer::fillExistsFromDB( $offers, $orphaned_only = true );
                    }

                    Update::offerPostMetas( $offers );

                    if( 0 === strpos($filename, 'price') ) {
                        $msg = "$progress из $offersCount цен обработано.";
                    }
                    elseif( 0 === strpos($filename, 'rest') ) {
                        $msg = "$progress из $offersCount запасов обработано.";
                    }
                    else {
                        $msg = "$progress из $offersCount предложений обработано.";
                    }

                    exit("$answer\n2: $msg");
                }
            }

            if( 'relationships' == $mode ) {
                $msg = 'Обновление зависимостей завершено.';

                if( $productsCount > $progress ) {
                    // Utils::set_transaction_mode();
                    $offset = apply_filters('exchange_products_relationships_offset', 500, $productsCount, $filename);
                    $products = array_slice($products, $progress, $offset);

                    /**
                     * @todo write realy update counter
                     */
                    $relationships = Update::relationships( $products );
                    $progress += sizeof( $products );
                    $msg = "$relationships зависимостей $offset товаров (всего $progress из $productsCount) обработано.";

                    /** Require retry */
                    if( $progress < $productsCount ) {
                        Utils::setMode('relationships', array('progress' => (int) $progress));
                        exit("progress\n$msg");
                    }
                }

                if( $offersCount > $progress ) {
                    // Utils::set_transaction_mode();
                    $offset = apply_filters('exchange_offers_relationships_offset', 500, $offersCount, $filename);
                    $offers = array_slice($offers, $progress, $offset);

                    $relationships = Update::relationships( $offers );
                    $progress += sizeof( $offers );
                    $msg = "$relationships зависимостей $offset предложений (всего $progress из $offersCount) обработано.";

                    /** Require retry */
                    if( $progress < $offersCount ) {
                        Utils::setMode('relationships', array('progress' => (int) $progress));
                        exit("progress\n$msg");
                    }

                    if( floatval($version) < 3 ) {
                        Utils::setMode('deactivate');
                        exit("progress\n$msg");
                    }
                }

                Utils::setMode('');
                exit("success\n$msg");
            }

            exit("success"); // \n$mode
        break;

        /**
         * E. Деактивация данных
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
         * @since  3.0
         * @return 'progress|success|failure'
         */
        case 'deactivate':
            /**
             * Чистим и пересчитываем количество записей в терминах
             * /
            $filename = Utils::get_filename();

            /**
             * Get valid namespace ('import', 'offers', 'orders')
             * /
            // $namespace = $filename ?
            //     preg_replace("/^([a-zA-Z]+).+/", '$1', $filename) : 'import';

            // rest, prices (need debug in new sheme version)
            // if (!in_array($namespace, array('import', 'offers', 'orders'))) {
            //     Utils::error( sprintf("Unknown import file type: %s", $namespace) );
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
            */

            // $noprice = $wpdb->get_results( "
            //     SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
            //     FROM $wpdb->postmeta pm
            //     INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            //     WHERE   p.post_type   = 'product'
            //         AND p.post_status = 'publish'
            //         AND
            //             (pm.meta_key = '_price' AND pm.meta_value = 0)
            //             OR NOT EXISTS (
            //                 SELECT pm.post_id, pm.meta_key FROM $wpdb->postmeta pm
            //                 WHERE p.ID = pm.post_id AND pm.meta_key = '_price'
            //             )

            //         -- AND p.post_date = p.post_modified
            //     " );
            $start_date = get_option( 'exchange_start-date', '' );
            if( $start_date ) {
                /**
                 * Set pending status when post no has price meta
                 * Most probably no has offer (or code error in last versions)
                 * @var array $notExistsPrice  List of objects
                 */
                $notExistsPrice = $wpdb->get_results( "
                    SELECT p.ID, p.post_type, p.post_status
                    FROM $wpdb->posts p
                    WHERE
                        p.post_type = 'product'
                        AND p.post_status = 'publish'
                        AND p.post_modified > '$start_date'
                        AND NOT EXISTS (
                            SELECT pm.post_id, pm.meta_key FROM $wpdb->postmeta pm
                            WHERE p.ID = pm.post_id AND pm.meta_key = '_price'
                        )
                " );

                // Collect Ids
                $notExistsPriceIDs = array_map('intval', wp_list_pluck( $notExistsPrice, 'ID' ));

                /**
                 * Set pending status when post has a less price meta (null value)
                 * @var array $nullPrice  List of objects
                 */
                $nullPrice = $wpdb->get_results( "
                    SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
                    FROM $wpdb->postmeta pm
                    INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                    WHERE   p.post_type   = 'product'
                        AND p.post_status = 'publish'
                        AND p.post_modified > '$start_date'
                        AND pm.meta_key = '_price'
                        AND pm.meta_value = 0
                " );

                // Collect Ids
                $nullPriceIDs = array_map('intval', wp_list_pluck( $nullPrice, 'post_id' ));

                // Merge Ids
                $deactivateIDs = array_unique( array_merge( $notExistsPriceIDs, $nullPriceIDs ) );

                /**
                 * Execute query (change post status to pending)
                 */
                if( sizeof($deactivateIDs) ) {
                    $wpdb->query(
                        "UPDATE $wpdb->posts SET post_status = 'pending'
                        WHERE ID IN (". implode(',', $deactivateIDs) .")"
                    );
                }
            }

            /**
             * @todo how define time rengу one exhange (if exchange mode complete clean date before new part of offers)
             * Return post status if product has a better price (only new)
             */
            // $betterPrice = $wpdb->get_results( "
            //     SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_status
            //     FROM $wpdb->postmeta pm
            //     INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            //     WHERE   p.post_type   = 'product'
            //         AND p.post_status = 'pending'
            //         AND p.post_modified = p.post_date
            //         AND pm.meta_key = '_price'
            //         AND pm.meta_value > 0
            // " );

            // // Collect Ids
            // $betterPriceIDs = array_map('intval', wp_list_pluck( $betterPrice, 'ID' ));

            // if( sizeof($betterPriceIDs) ) {
            //     $wpdb->query(
            //         "UPDATE $wpdb->posts SET post_status = 'publish'
            //         WHERE ID IN (". implode(',', $betterPriceIDs) .")"
            //     );
            // }

            $path_dir = Parser::getDir();
            $files = Parser::getFiles();

            foreach ($files as $file)
            {
                // @unlink($file);
                $pathname = $path_dir . '/' . date('Ymd') . '_debug/';
                @mkdir( $pathname );
                @rename( $file, $pathname . ltrim(basename($file), "./\\") );
            }

            $msg = 'Деактивация товаров завершена';

            if( floatval($version) < 3 ) {
                Utils::setMode('complete');
                exit("progress\n$msg");
            }

            exit("success\n$msg");
        break;

        /**
         * F. Завершающее событие загрузки данных
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=complete
         * @since  3.0
         */
        case 'complete':
            /**
             * Insert count the number of records in a category
             * /
            Update::update_term_counts();
            */
            // flush_rewrite_rules();

            /**
             * Reset start date
             * @todo @fixit (check between)
             */
            update_option( 'exchange_start-date', '' );

            /**
             * Refresh version
             */
            update_option( 'exchange_version', '' );

            delete_transient( 'wc_attribute_taxonomies' );

            Utils::setMode('');
            update_option( 'exchange_last-update', current_time('mysql') );

            exit("success\nВыгрузка данных завершена");
        break;

        case 'success':
            // ex_mode__success($_REQUEST['type']);

            exit("success\n");
        break;

        default:
            Utils::error("Unknown mode");
            break;
    }
}

// add_filter( '1c4wp_update_term', __NAMESPACE__ . '\update_term_filter', $priority = 10, $accepted_args = 1 );
function update_term_filter( $arTerm ) {
    /**
     * @todo fixit
     * #crunch
     * Update only parents (Need for second query)
     */
    $res['parent'] = $arTerm['parent'];

    return $res;
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
// register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );

function getTaxonomyByExternal( $raw_ext_code )
{
    global $wpdb;

    $rsResult = $wpdb->get_results( $wpdb->prepare("
        SELECT wat.*, watm.* FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id
        WHERE watm.meta_value = %d
        LIMIT 1
        ", $raw_ext_code) );

    if( $rsResult ) {
        $res = current($rsResult);
        $obResult = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $obResult;
}

function getAttributesMap()
{
    global $wpdb;

    $arResult = array();
    $rsResult = $wpdb->get_results( "
        SELECT wat.*, watm.*, watm.meta_value as ext FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id" );

    die();

    foreach ($rsResult as $res)
    {
        $arResult[ $res->meta_value ] = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $arResult;
}
