<?php

namespace NikolayS93\Exchanger;

use NikolayS93\Exchanger\Model\ExchangeOffer;
use NikolayS93\Exchanger\Model\ExchangeProduct;
use NikolayS93\Exchanger\ORM\Collection;
use WP_REST_Server;
use function NikolayS93\Exchange\the_statistic_table;

class REST_Controller {

	const OPTION_VERSION = 'exchange_version';

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
		$this->version = get_option( self::OPTION_VERSION, '' );
	}

	/**
	 * @param int|\WP_User $user User ID or object.
	 *
	 * @return bool
	 */
	public function has_permissions( $user ) {
		foreach ( plugin()->get_permissions() as $permission ) {
			if ( user_can( $user, $permission ) ) {
				return true;
			}
		}

		return false;
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
	 * @return \WP_Error|\WP_User
	 */
	private function check_current_user() {
		global $user_id;

		if ( is_user_logged_in() ) {
			$user         = wp_get_current_user();
			$user_id      = $user->ID;
			$method_error = __( "User not logged in", Plugin::DOMAIN );
		} elseif ( ! empty( $_COOKIE[ EXCHANGE_COOKIE_NAME ] ) ) {
			$user         = wp_validate_auth_cookie( $_COOKIE[ EXCHANGE_COOKIE_NAME ], 'auth' );
			$user_id      = $user->ID;
			$method_error = __( "Invalid cookie", Plugin::DOMAIN );
		} else {
			$user_id      = 0;
			$method_error = __( "User not identified", Plugin::DOMAIN );
		}

		if ( ! $user_id ) {
			$user = new \WP_Error( 'AUTH_ERROR', $method_error );
		}

		if ( ! $this->has_permissions( $user_id ) ) {
			$user = new \WP_Error( 'AUTH_ERROR', sprintf( "User %s has not permissions",
				get_user_meta( $user_id, 'nickname', true ) ) );
		}

		return $user;
	}

	/**
	 * Route 1c modes
	 *
	 * @url http://v8.1c.ru/edi/edi_stnd/131/
	 */
	function exchange() {
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

		if ( ! $mode = plugin()->get_mode() ) {
			Error()->add_message( "Mode no support" );
		}

		if ( 'checkauth' === $mode ) {
			$this->checkauth();
		} else {
			$user = $this->check_current_user();
			if ( is_wp_error( $user ) ) {
				Error()->add_message( $user );
			}

			$route = array(
				$this,
				false !== ( $pos = strpos( $mode, '_' ) ) ? substr( $mode, 0, $pos ) : $mode
			);

			if( is_callable($route) ) {
				// init();
				// file();
				// import();
				// deactivate();
				// complete();
				call_user_func( $route );
			}
		}
	}

	/**
	 * A. Начало сеанса (Авторизация)
	 * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
	 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
	 * A. Начало сеанса
	 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
	 * @print 'success\nCookie\nCookie_value'
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
	 * @print
	 * zip=yes|no - Сервер поддерживает Zip
	 * file_limit=<число> - максимально допустимый размер файла в байтах для передачи за один запрос
	 */
	public function init( $skip_zip_checks = false ) {
		if ( ! $skip_zip_checks ) {
			$is_zip = check_zip_extension();
			if ( is_wp_error( $is_zip ) ) {
				error()->add_message( $is_zip );
			}
		}

		/**
		 * Option is empty then exchange end
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

			plugin()->reset_mode();
		}

		exit( "zip=yes\nfile_limit=" . wp_max_upload_size() );
	}

	/**
	 * C. Выгрузка на сайт файлов обмена
	 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
	 * D. Отправка файла обмена на сайт
	 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
	 *
	 * Загрузка CommerceML2 файла или его части в виде POST. (Пишет поток в файл и распаковывает его)
	 * @print string 'success'
	 */
	public function file( $requested = "php://input" ) {
		$Plugin = Plugin();
		$file   = Request::get_file();
		// get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable()
		$path_dir = $Plugin->get_exchange_dir( Request::get_type() );
		$path     = $path_dir . '/' . $file['name'] . '.' . $file['ext'];
		print_r( $path, $path_dir );

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

	private function parse_items( &$Parser, $Dispatcher ) {
		$filename = Request::get_file();
		if ( ! $file = Plugin()->get_exchange_file( $filename ) ) {
			Error()->add_message( sprintf( __( 'File %s not found.', Plugin::DOMAIN ), $filename ) );
		}

		if( null === $Parser ) {
			$Parser = new Parser();
		}

		if( null === $Dispatcher ) {
			$Dispatcher = \CommerceMLParser\Parser::getInstance();
		}

		$Dispatcher->addListener( "ProductEvent", array( $Parser, 'product_event' ) );
		$Dispatcher->addListener( "OfferEvent", array( $Parser, 'offer_event' ) );
		$Dispatcher->addListener( "CategoryEvent", array( $Parser, 'category_event' ) );
		$Dispatcher->addListener( "WarehouseEvent", array( $Parser, 'warehouse_event' ) );
		$Dispatcher->addListener( "PropertyEvent", array( $Parser, 'property_event' ) );
		$Dispatcher->parse( $file );
	}

	/**
	 * D. Пошаговая загрузка данных
	 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
	 * @print 'progress|success|failure'
	 */
	public function import($Parser = null, $Dispatcher = null, $Update = null) {

		$this->parse_items( $Parser, $Dispatcher );
		$products   = $Parser->get_products();
		$offers     = $Parser->get_offers();
		$categories = $Parser->get_categories();
		$warehouses = $Parser->get_warehouses();
		$attributes = $Parser->get_properties();


		$mode = plugin()->get_mode();

		if( null === $Update ) {
			$Update = new Update();
		}

		if( 'import_temporary' === $mode && $products->count() ) {
			Transaction()->set_transaction_mode();

			$products
				->fill_exists()
				->fill_exists_terms( $Parser );

			$Update
				->update_products( $products )
				->update_products_meta( $products );

			$Update->stop( array(
				"Записаны временные данные товаров",
			) );
		}

		if( $categories->count() || $warehouses->count() || $attributes->count() ) {
			Transaction()->set_transaction_mode();

			$categories->fill_exists();
			$warehouses->fill_exists();
			$attributes->fill_exists();

			$Update->terms( $categories )->term_meta( $categories );
			$Update->terms( $warehouses )->term_meta( $warehouses );

			// $attributeValues = $attributes->get_all_values();
			// $Update
			// 	->properties( $attributes )
			// 	->terms( $attributeValues )
			// 	->term_meta( $attributeValues );
			if ( $products->count() && floatval( $this->version ) < 3 ) {
				plugin()->set_mode( 'import_temporary', $Update->set_status( 'progress' ) );
			}

			$Update->stop( array(
				"Обновлено {$Update->results['update']} категорий/терминов.",
				"Обновлено {$Update->results['meta']} мета записей.",
			) );
		}

		$offersCount = $offers->count();
		$offers      = $offers->slice( $Update->progress, $Update->offset['offer'] );

		if ( $offersCount ) {
			Transaction()->set_transaction_mode();

			$offers->fill_exists();

			// second step: import posts with post meta
			if ( 'import_relationships' !== $mode ) {
				$Update
					->update_offers( $offers )
					->update_offers_meta( $offers );

				/**
				 * Build answer message
				 */
				$filenames = $Parser->get_filenames();
				if ( ! empty( $filenames ) ) {
					$filename = current( $filenames );

					switch ( true ) {
						case 0 === strpos( $filename, 'price' ):
							$progressMessage = "{$Update->progress} из $offersCount цен обработано.";
							break;

						case 0 === strpos( $filename, 'rest' ):
							$progressMessage = "{$Update->progress} из $offersCount запасов обработано.";
							break;
					}
				}

				if ( empty( $progressMessage ) ) {
					$progressMessage = "{$Update->progress} из $offersCount предложений обработано.";
				}

				// Set mode for go away or retry
				plugin()->set_mode( $Update->progress > $offersCount ? 'import_relationships' : 'import_posts',
					$Update->set_status( 'progress' ) );

				$Update
					->stop( array(
						$progressMessage,
						$Update->results['meta'] . " произвольных записей товаров обновлено."
					) );
			} // third step: import posts relationships
			else {

				$Update
					->relationships( $offers )
					->set_status( $Update->progress < $offersCount ? 'progress' : 'success' );

				if ( 'success' == $Update->status ) {
					if ( floatval( $this->version ) < 3 ) {
						plugin()->set_mode( 'deactivate', $Update->set_status( 'progress' ) );
					}
				} else {
					// retry
					plugin()->set_mode( 'import_relationships', $Update );
				}

				$Update
					->stop( printf( '%d зависимостей %d предложений обновлено (всего %d из %d обработано).',
						$Update->results['update'],
						$offers->count(),
						$Update->progress,
						$offersCount ) );
			}
		}

		if ( 'import_posts' == $mode || 'import_relationships' == $mode ) {
			if ( $offers->count() ) {
				Transaction()->set_transaction_mode();

				$offers->fill_exists();
			}
		}

		/** Unreachable statement in theory */
		plugin()->reset_mode();
		$Update->stop();
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
			plugin()->set_mode( 'complete', new Update() );
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

		//        if ( is_debug() ) {
//            $Plugin = Plugin();
//            $file   = Request::get_file();
//            // get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable()
//            $path = $Plugin->get_exchange_dir( Request::get_type() ) . '/' . date( 'YmdH' ) . '_debug';
//            @mkdir( $path );
//            @rename( $zip_path, $path . '/' . $file['name'] . '.' . $file['ext'] );
//        }

		exit( "success\nВыгрузка данных завершена" );
	}
}
