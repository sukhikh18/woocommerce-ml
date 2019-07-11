<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\ExchangeOffer;


function do_exchange() {
    /**
     * @global $wpdb
     */
    global $wpdb;

    /**
     * Start buffer in strict mode
     */
    Plugin::start_exchange_session();

    /**
     * Check required arguments
     */
    if ( !$type = Plugin::get_type() ) Plugin::error("No type");
    if ( !$mode = Plugin::get_mode() ) Plugin::error("No mode");

    if( 'catalog' != $type ) Plugin::error("Type no support");

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
            Plugin::error("No authentication credentials");
        }

        $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if ( is_wp_error($user) ) Plugin::wp_error($user);
        Plugin::check_user_permissions($user);

        $expiration = TIMESTAMP + apply_filters('auth_cookie_expiration', DAY_IN_SECONDS, $user->ID, false);
        $auth_cookie = wp_generate_auth_cookie($user->ID, $expiration);

        exit("success\n". COOKIENAME ."\n$auth_cookie");
    }

    check_wp_auth();

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
            check_zip_extension();

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

                Plugin::set_mode('');
            }

            exit("zip=yes\nfile_limit=" . get_filesize_limit());
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
            $filename = Plugin::get_filename();
            $path_dir = Parser::getDir( Plugin::get_type() );

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
                    Plugin::error( sprintf("File %s is empty", $path) );
                }
            }

            $zip_paths = glob("$path_dir/*.zip");

            $r = Plugin::unzip( $zip_paths, $path_dir, $remove = true );
            if( true !== $r ) Plugin::error($r);

            if ('catalog' == Plugin::get_type()) exit("success\nФайл принят.");
        break;

        /**
         * D. Пошаговая загрузка данных
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
         * @return 'progress|success|failure'
         */
        case 'import':
        // case 'products':
        case 'relationships':
            if( !$filename = Plugin::get_filename() ) {
                Plugin::error( "Filename is empty" );
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
            $progress = intval( Plugin::get('progress', 0, 'status') );

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
                        Plugin::set_mode('', array('progress' => (int) $progress));
                    }
                    /** Go away */
                    else {
                        Plugin::set_mode('relationships');
                    }

                    $resProducts = Update::posts( $products );

                    /**
                     * has created products without ID in array
                     */
                    if( 0 < $resProducts['create'] ) {
                        /** Update products array */
                        ExchangeProduct::fillExistsFromDB( $products, $orphaned_only = true );
                    }

                    $resProductsMeta = Update::postmeta( $products, $resProducts );

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
                        Plugin::set_mode('', array('progress' => (int) $progress));
                    }
                    /** Go away */
                    else {
                        if( 0 === strpos($filename, 'offers') ) {
                            Plugin::set_mode('relationships');
                        }
                        else {
                            $answer = 'success';
                            Plugin::set_mode('');
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

                    exit("$answer\n$msg");
                }
            }

            if( 'relationships' == $mode ) {
                $msg = 'Обновление зависимостей завершено.';

                if( $productsCount > $progress ) {
                    // Plugin::set_transaction_mode();
                    $offset = apply_filters('exchange_products_relationships_offset', 500, $productsCount, $filename);
                    $products = array_slice($products, $progress, $offset);
                    $sizeOfProducts = sizeof( $products );

                    /**
                     * @todo write realy update counter
                     */
                    $relationships = Update::relationships( $products );
                    $progress += $sizeOfProducts;
                    $msg = "$relationships зависимостей $sizeOfProducts товаров (всего $progress из $productsCount) обработано.";

                    /** Require retry */
                    if( $progress < $productsCount ) {
                        Plugin::set_mode('relationships', array('progress' => (int) $progress));
                        exit("progress\n$msg");
                    }
                }

                if( $offersCount > $progress ) {
                    // Plugin::set_transaction_mode();
                    $offset = apply_filters('exchange_offers_relationships_offset', 500, $offersCount, $filename);
                    $offers = array_slice($offers, $progress, $offset);
                    $sizeOfOffers = sizeof( $offers );

                    $relationships = Update::relationships( $offers );
                    $progress += $sizeOfOffers;
                    $msg = "$relationships зависимостей $sizeOfOffers предложений (всего $progress из $offersCount) обработано.";

                    /** Require retry */
                    if( $progress < $offersCount ) {
                        Plugin::set_mode('relationships', array('progress' => (int) $progress));
                        exit("progress\n$msg");
                    }

                    if( floatval($version) < 3 ) {
                        Plugin::set_mode('deactivate');
                        exit("progress\n$msg");
                    }
                }

                Plugin::set_mode('');
                exit("success\n$msg");
            }

            exit("success"); // \n$mode
        break;

        /**
         * E. Деактивация данных
         * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
         * @since  3.0
         * @return 'progress|success|failure'
         * @note We need always update post_modified for true deactivate
         */
        case 'deactivate':
            /**
             * move .xml files from exchange folder
             */
            $path_dir = Parser::getDir();
            $files = Parser::getFiles();

            foreach ($files as $file)
            {
                // @unlink($file);
                $pathname = $path_dir . '/' . date('Ymd') . '_debug/';
                @mkdir( $pathname );
                @rename( $file, $pathname . ltrim(basename($file), "./\\") );
            }

            /**
             * Чистим и пересчитываем количество записей в терминах
             */
            if( !$start_date = get_option( 'exchange_start-date', '' ) ) return;

            /**
             * Meta data from any finded file
             * @var array { version: float, is_full: bool }
             */
            $summary = Plugin::get_summary_meta( $files[0] );

            /**
             * Need deactivate deposits products
             * $summary['version'] < 3 && $version < 3 &&
             */
            if( true === $summary['is_full'] ) {
                $post_lost = Plugin::get('post_lost');

                if( !$post_lost ) {
                    // $postmeta['_stock'] = 0; // required?
                    $wpdb->query( "UPDATE $wpdb->postmeta pm
                        SET
                            pm.meta_value = 'outofstock'
                        WHERE
                            pm.meta_key = '_stock_status' AND
                            pm.post_id IN (
                                SELECT p.ID FROM $wpdb->posts p
                                WHERE
                                    p.post_type = 'product'
                                    AND p.post_modified < '$start_date'
                            )" );
                }
                elseif( 'delete' == $post_lost ) {
                    // delete query
                }
            }

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

            $price_lost = Plugin::get('price_lost');

            /**
             * Deactivate
             */
            if( !$price_lost && sizeof($deactivateIDs) ) {
                /**
                 * Execute query (change post status to pending)
                 */
                $wpdb->query(
                    "UPDATE $wpdb->posts SET post_status = 'pending'
                    WHERE ID IN (". implode(',', $deactivateIDs) .")"
                );
            }
            elseif( 'delete' == $price_lost ) {
                // delete query
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

            $msg = 'Деактивация товаров завершена';

            if( floatval($version) < 3 ) {
                Plugin::set_mode('complete');
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

            Plugin::set_mode('');
            update_option( 'exchange_last-update', current_time('mysql') );

            exit("success\nВыгрузка данных завершена");
        break;

        case 'success':
            // ex_mode__success($_REQUEST['type']);

            exit("success\n");
        break;

        default:
            Plugin::error("Unknown mode");
            break;
    }
}