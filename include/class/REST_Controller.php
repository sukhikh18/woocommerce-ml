<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\ExchangeOffer;
use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\ORM\Collection;
use WP_REST_Server;

class REST_Controller {

    const option_version = 'exchange_version';

    /**
     * The capability required to use REST API.
     *
     * @var array
     */
    public $permissions = array( 'shop_manager', 'administrator' );

    /**
     * The namespace for the REST API routes.
     *
     * @var string
     */
    public $namespace = 'exchange/v1';

    /**
     * @var string (float value)
     */
    public $version;

    function __construct() {
        // Set CommerceML protocol version
        $this->version = get_option( self::option_version, '' );

        // Allow people to change what capability is required to use this plugin.
        $this->permissions = apply_filters( Plugin::PREFIX . 'rest_permissions', $this->permissions );
    }

    /**
     * @param int|\WP_User $user User ID or object.
     *
     * @return bool
     */
    public function has_permissions( $user ) {
        foreach ( $this->get_permissions() as $permission ) {
            if ( user_can( $user, $permission ) ) {
                return true;
            }
        }

        return false;
    }

    public function get_permissions() {
        return $this->permissions;
    }

    function register_routes() {
        register_rest_route( $this->namespace, '/status/', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'status' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/init/', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'init' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/file/', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'file' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/import/', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'import' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/deactivate/', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'deactivate' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/complete/', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'complete' ),
                'permission_callback' => array( $this, 'has_permissions' ),
            ),
        ) );
    }

    public function status() {
        the_statistic_table();
    }

    /**
     * @url http://v8.1c.ru/edi/edi_stnd/131/
     */
    function do_exchange() {
        if ( ! headers_sent() ) {
            header( "Content-Type: text/plain; charset=" . EXCHANGE_CHARSET );
        }

        Error::set_strict_mode();

        // CGI fix
        if ( ! $_GET && isset( $_SERVER['REQUEST_URI'] ) ) {
            $query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
            parse_str( $query, $_GET );
        }

        if ( ! ( $type = Request::get_type() ) || ! in_array( $type, array( 'catalog' ) ) ) {
            Error()->add_message( "Type no support" );
        }

        if ( ! $mode = Request::get_mode() ) {
            Error()->add_message( "Mode no support" );
        }

        global $user_id;

        if ( 'checkauth' === $mode ) {
            $this->checkauth();
        }
        else {
            if ( is_user_logged_in() ) {
                $user         = wp_get_current_user();
                $user_id      = $user->ID;
                $method_error = "Not logged in";
            } elseif ( ! empty( $_COOKIE[ EXCHANGE_COOKIE_NAME ] ) ) {
                $user_id      = $user = wp_validate_auth_cookie( $_COOKIE[ EXCHANGE_COOKIE_NAME ], 'auth' );
                $method_error = __("Invalid cookie", Plugin::DOMAIN);
            } else {
                $method_error = __("User not identified", Plugin::DOMAIN);
            }

            if ( ! $user_id ) {
                Error()->add_message( $method_error );
            }

            if ( ! $this->has_permissions( $user_id ) ) {
                Error()->add_message( sprintf( "User %s not has permissions",
                    get_user_meta( $user_id, 'nickname', true ) ) );
            }

            call_user_func( array( $this, $mode ) );
        }
    }

    /**
     * A. Начало сеанса (Авторизация)
     * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
     * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
     *
     * A. Начало сеанса
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
     * @return 'success\nCookie\nCookie_value'
     */
    public function checkauth() {
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
                Error()->add_message( "No authentication credentials" );
            }

            $user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
            if ( is_wp_error( $user ) ) {
                Error()->add_message( $user );
            }
        } else {
            $user = wp_get_current_user();
        }

        if ( ! $this->has_permissions( $user ) ) {
            Error()->add_message( sprintf( "No %s user permissions",
                get_user_meta( $user->ID, 'nickname', true ) ) );
        }

        $expiration  = EXCHANGE_START_TIMESTAMP + apply_filters( 'auth_cookie_expiration', DAY_IN_SECONDS, $user->ID,
                false );
        $auth_cookie = wp_generate_auth_cookie( $user->ID, $expiration );

        exit( "success\n" . EXCHANGE_COOKIE_NAME . "\n$auth_cookie" );
    }

    /**
     * B. Запрос параметров от сайта
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=init
     * B. Уточнение параметров сеанса
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=init
     *
     * @exit
     * zip=yes|no - Сервер поддерживает Zip
     * file_limit=<число> - максимально допустимый размер файла в байтах для передачи за один запрос
     */
    public function init() {
        /** Zip required (if no - must die) */
        check_zip_extension();

        $Update = Update::get_instance();

        /**
         * Option is empty then exchange end
         * @var [type]
         */
        if ( ! $start = get_option( 'exchange_start-date', '' ) ) {
            /**
             * Refresh exchange version
             * @var float isset($_GET['version']) ? ver >= 3.0 : ver <= 2.99
             */
            update_option( 'exchange_version', ! empty( $_GET['version'] ) ? $_GET['version'] : '' );

            /**
             * Set start wp date sql format
             */
            update_option( 'exchange_start-date', current_time( 'mysql' ) );

            $Update->set_mode( '' );
        }

        exit( "zip=yes\nfile_limit=" . wp_max_upload_size() );
    }

//    protected function put_requested_file( $requested, $path ) {
//        $temp_path = "$path~";
//
//        // Open raw stream and create temporary file
//        $input_file = fopen( $requested, 'r' );
//        $temp_file  = fopen( $temp_path, 'w' );
//        stream_copy_to_stream( $input_file, $temp_file );
//
//        // if file with no xml data is exists remove it
//        if ( is_file( $path ) ) {
//            $temp_header = file_get_contents( $temp_path, false, null, 0, 32 );
//            if ( strpos( $temp_header, "<?xml " ) !== false ) {
//                unlink( $path );
//            }
//        }
//        $file = fopen( $path, 'a' );
//        stream_copy_to_stream( $temp_file, $file );
//        fclose( $temp_file );
//        unlink( $temp_path );
//
//        return $file;
//    }

    protected function unzip() {

    }

    /**
     * C. Выгрузка на сайт файлов обмена
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
     * D. Отправка файла обмена на сайт
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
     *
     * Загрузка CommerceML2 файла или его части в виде POST. (Пишет поток в файл и распаковывает его)
     * @exit string 'success'
     */
    public function file( $requested = "php://input" ) {
        $Plugin = Plugin();
        $file   = Request::get_file();
        // get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable()
        $path_dir = $Plugin->get_exchange_dir( Request::get_type() );
        $path     = $path_dir . '/' . $file['name'] . '.' . $file['ext'];

        // $resource = $this->put_requested_file( $requested, $path );
        $from     = fopen( $requested, 'r' );
        $resource = fopen( $path, 'a' );
        stream_copy_to_stream( $from, $resource );
        fclose( $from );
        fclose( $resource );

        unzip( $path, $path_dir );

        if ( 'catalog' == Request::get_type() ) {
            exit( "success\n" );
        }
    }

    /**
     * C. Получение файла обмена с сайта
     * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=query.
     */
    public function query() {
    }

    /**
     * D. Пошаговая загрузка данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
     * @return 'progress|success|failure'
     */
    public function import() {
        $file = Request::get_file();

        /**
         * Parse
         */
        if ( ! $files = Plugin()->get_exchange_files( $file['path'] ) ) {
            Error()->add_message( __('File %s not found.', $file['path']) );
        }

        $Parser = new Parser( $files );
        $Update = Update::get_instance();

        Transaction()->set_transaction_mode();

        if ( ! in_array( Request::get_mode(), array( 'import_posts', 'import_relationships' ) ) ) {
            $Update->update_terms( $Parser );
        } elseif ( 'import_relationships' != Request::get_mode() ) {
            $Update->update_products( $Parser );
            $Update->update_offers( $Parser );
        } else {
            $Update->update_products_relationships( $Parser );
            $Update->update_offers_relationships( $Parser );
        }

        exit( "success" ); // \n$mode
    }

    /**
     * E. Деактивация данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
     * @return 'progress|success|failure'
     * @note We need always update post_modified for true deactivate
     * @since  3.0
     */
    public function deactivate() {
        /**
         * Чистим и пересчитываем количество записей в терминах
         */
        if ( ! $start_date = get_option( 'exchange_start-date', '' ) ) {
            return;
        }

        $Plugin = Plugin();
        /**
         * move .xml files from exchange folder
         */
        $path_dir = $Plugin->get_exchange_dir();
        $files    = $Plugin->get_exchange_files();

        if ( ! empty( $files ) ) {
            reset( $files );

            /**
             * Meta data from any finded file
             * @var array { version: float, is_full: bool }
             */
            $summary = Plugin::get_summary_meta( current( $files ) );

            /**
             * Need deactivate deposits products
             * $summary['version'] < 3 && $version < 3 &&
             */
            if ( true === $summary['is_full'] ) {
                $post_lost = Plugin::get( 'post_lost' );

                if ( ! $post_lost ) {
                    // $postmeta['_stock'] = 0; // required?
                    $wpdb->query( "
                        UPDATE $wpdb->postmeta pm SET pm.meta_value = 'outofstock'
                        WHERE pm.meta_key = '_stock_status' AND pm.post_id IN (
                                  SELECT p.ID FROM $wpdb->posts p
                                  WHERE p.post_type = 'product'
                                        AND p.post_modified < '$start_date'
                        ) " );
                } elseif ( 'delete' == $post_lost ) {
                    // delete query
                }
            }
        }

        /**
         * Set pending status when post no has price meta
         * Most probably no has offer (or code error in last versions)
         * @var array $notExistsPrice List of objects
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
        $notExistsPriceIDs = array_map( 'intval', wp_list_pluck( $notExistsPrice, 'ID' ) );

        /**
         * Set pending status when post has a less price meta (null value)
         * @var array $nullPrice List of objects
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
        $nullPriceIDs = array_map( 'intval', wp_list_pluck( $nullPrice, 'post_id' ) );

        // Merge Ids
        $deactivateIDs = array_unique( array_merge( $notExistsPriceIDs, $nullPriceIDs ) );

        $price_lost = Plugin::get( 'price_lost' );

        /**
         * Deactivate
         */
        if ( ! $price_lost && sizeof( $deactivateIDs ) ) {
            /**
             * Execute query (change post status to pending)
             */
            $wpdb->query(
                "UPDATE $wpdb->posts SET post_status = 'pending'
                    WHERE ID IN (" . implode( ',', $deactivateIDs ) . ")"
            );
        } elseif ( 'delete' == $price_lost ) {
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

        if ( floatval( $version ) < 3 ) {
            Plugin::set_mode( 'complete' );
            exit( "progress\n$msg" );
        }

        exit( "success\n$msg" );
    }

    /**
     * F. Завершающее событие загрузки данных
     * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=complete
     * @since  3.0
     */
    public function complete() {
        /**
         * Insert count the number of records in a category
         * /
         * Update::update_term_counts();
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

        Plugin::set_mode( '' );
        update_option( 'exchange_last-update', current_time( 'mysql' ) );

        exit( "success\nВыгрузка данных завершена" );
    }
}