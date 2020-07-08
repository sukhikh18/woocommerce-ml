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
	if ( ! $type = Plugin::get_type() ) {
		Plugin::error( "No type" );
	}

	if ( ! $mode = Plugin::get_mode() ) {
		Plugin::error( "No mode" );
	}

	if ( 'catalog' != $type ) {
		Plugin::error( "Type no support" );
	}

	/**
	 * CGI fix
	 */
	if ( ! $_GET && isset( $_SERVER['REQUEST_URI'] ) ) {
		$query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
		parse_str( $query, $_GET );
	}

	Plugin::session_start();

	/**
	 * CommerceML protocol version
	 * @var string (float value)
	 */
	$version = Plugin::get_session_arg( 'ver' );
	if ( ! $version && ! empty( $_GET['version'] ) ) {
		$version = Plugin::set_session_arg( 'version', floatval( str_replace( ',', '.', $_GET['version'] ) ) );
	}

	if ( 'checkauth' !== $mode ) {
		global $user_id;

		if ( is_user_logged_in() ) {
			/** @var $_user WP_User */
			$_user = wp_get_current_user();

			if ( ! $user_id = (int) $_user->ID ) {
				Plugin::error( "Unsigned user id" );
			}
		} elseif ( $auth_cookie = Plugin::get_session_arg( COOKIENAME ) ) {
			if ( ! $user_id = wp_validate_auth_cookie( $auth_cookie, 'auth' ) ) {
				Plugin::error( "Invalid cookie" );
			}
		}

		Plugin::check_user_permissions( $user_id );
	}

	switch ( $mode ) {
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
		case 'checkauth':
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
				Plugin::error( "No authentication credentials" );
			}

			$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
			if ( is_wp_error( $user ) ) {
				Plugin::wp_error( $user );
			}

			Plugin::check_user_permissions( $user );

			$expiration             = TIMESTAMP + apply_filters( 'auth_cookie_expiration', DAY_IN_SECONDS, $user->ID,
					false );
			$auth_cookie = Plugin::set_session_arg( COOKIENAME, wp_generate_auth_cookie( $user->ID, $expiration ) );

			Plugin::exit( implode( "\n", array(
					'success',
					session_name(),
					session_id(),
					'timestamp=' . time()
				) ) . "\n" );
			break;

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
			if ( ! $start = get_option( 'exchange_start-date', '' ) ) {
				/**
				 * Set start wp date sql format
				 */
				update_option( 'exchange_start-date', current_time( 'mysql' ) );

				Plugin::set_mode( '' );
			}

			Plugin::exit( "zip=yes\nfile_limit=" . get_filesize_limit() );
			break;

		/**
		 * C. Получение файла обмена с сайта
		 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=query.
		 */
		case 'query':
			Plugin::exit( 'success' );
			break;

		/**
		 * C. Выгрузка на сайт файлов обмена
		 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=file&filename=<имя файла>
		 * D. Отправка файла обмена на сайт
		 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=file&filename=<имя файла>
		 *
		 * Загрузка CommerceML2 файла или его части в виде POST. (Пишем поток в файл и распаковываем его)
		 * @return success
		 */
		case 'file':
			$filename  = Plugin::get_filename();
			$requested = "php://input";
			$dir       = Parser::get_dir( Plugin::get_type() );
			$file      = $dir . '/' . $filename;

			$from     = fopen( $requested, 'r' );
			$resource = fopen( $file, 'a' );
			// @todo Do you want to get ВерсияСхемы or ДатаФормирования?
			stream_copy_to_stream( $from, $resource );
			fclose( $from );
			fclose( $resource );

			$r = Plugin::unzip( $file, $dir, false );
			if ( true !== $r ) {
				Plugin::error( 'Unzip archive error. ' . print_r( $r, 1 ) );
			}

			if ( 'catalog' == $type ) {
				Plugin::exit( "success\nФайл принят." );
			}
			break;

		/**
		 * D. Пошаговая загрузка данных
		 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=import&filename=<имя файла>
		 * @return 'progress|success|failure'
		 */
		case 'import':
		case 'relationships':
			$filename = Plugin::get_filename();
			$step = Plugin::get_session_arg( 'step', 0 );
			$progress = Plugin::get_session_arg( 'progress', 0 );

			/**
			 * Parse
			 */
			$Parser = Parser::get_instance();
			$Parser->__parse( $filename );
			$Parser->__fill_exists();

			/** @var Collection $products */
			$products      = $Parser->get_products();
			/** @var Collection $offers */
			$offers        = $Parser->get_offers();
			/** @var Collection $categories */
			$categories = $Parser->get_categories();
			/** @var Collection $properties */
			$properties = $Parser->get_properties();
			/** @var Collection $developers */
			$developers = $Parser->get_developers();
			/** @var Collection $warehouses */
			$warehouses = $Parser->get_warehouses();

			$attributeValues = array();
			foreach ( $properties as $property ) {
				/** Collection to simple array */
				foreach ( $property->get_terms() as $term ) {
					$attributeValues[] = $term;
				}
			}

			/** @var Int $productsCount */
			$productsCount = count( $products );
			/** @var Int $offersCount */
			$offersCount   = count( $offers );
			/** @var Int $termsCount */
			$termsCount = count( $categories ) + count( $properties ) + count( $developers ) + count( $warehouses ) +
				count( $attributeValues );

			if( $step < 1 && $termsCount ) {
				Update::terms( $categories );
				Update::termmeta( $categories );

				Update::terms( $developers );
				Update::termmeta( $developers );

				Update::terms( $warehouses );
				Update::termmeta( $warehouses );

				Update::properties( $properties );

				Update::terms( $attributeValues );
				Update::termmeta( $attributeValues );

				Plugin::set_session_arg( 'step', 1 );
				Plugin::exit( "progress\nОбновление терминов завершено." );
			}

			if( $step < 2 ) {
				// Recursive update products.
				if( $productsCount > $progress ) {
					$offset = apply_filters( 'exchange_posts_import_offset', 500, $productsCount, $filename );

					// Slice products who offset better.
					$products = array_slice( $products, $progress, $offset );

					// Count products will be updated.
					$progress = Plugin::set_session_arg( 'progress', $progress + sizeof( $products ) );

					$results = Update::posts( $products );
					$resultsMeta = Update::postmeta( $products, $results );

					$status = array( "$progress из $productsCount записей товаров обработано." );

					// Do not retry! Go next.
					if ( $progress >= $productsCount ) {
						Plugin::delete_session_arg( 'progress', 0 );
						Plugin::set_session_arg( 'step', $step + 1 );

						$status[] = Plugin::extract_session_arg( 'create' ) . " товаров добавлено.";
						$status[] = Plugin::extract_session_arg( 'update' ) . " товаров обновлено.";
						$status[] = Plugin::extract_session_arg( 'meta' ) . " произвольных записей товаров обновлено.";
					}

					Plugin::exit( "progress\n" . implode( "\n\t -- ", $status ) );
				}
				// Recursive update offers.
				if ( $offersCount > $progress ) {
					$offset = apply_filters( 'exchange_posts_offers_offset', 1000, $offersCount, $filename );

					// Slice offers who offset better.
					$offers = array_slice( $offers, $progress, $offset );

					// Count offers who will be updated.
					$progress = Plugin::set_session_arg( 'progress', $progress + sizeof( $offers ) );

					Update::offers( $offers );
					Update::offerPostMetas( $offers );

					$status = array( "$progress из $offersCount предложений обработано." );

					// Do not retry! Go next.
					if ( $progress >= $offersCount ) {
						Plugin::delete_session_arg( 'progress', 0 );
						Plugin::set_session_arg( 'step', $step + 1 );

						$status[] = Plugin::extract_session_arg( 'create' ) . " предложений добавлено.";
						$status[] = Plugin::extract_session_arg( 'update' ) . " предложений обновлено.";
						$status[] = Plugin::extract_session_arg( 'meta' ) . " произвольных записей предложений обновлено.";
					}

					if ( 0 === strpos( $filename, 'price' ) ) {
						Plugin::exit("progress\n$progress из $offersCount цен обработано.");
					} elseif ( 0 === strpos( $filename, 'rest' ) ) {
						Plugin::exit("progress\n$progress из $offersCount запасов обработано.");
					}

					Plugin::exit( "progress\n" . implode( "\n\t -- ", $status ) );
				}
			}

			if( $step < 3 ) {
				$msg = 'Обновление зависимостей завершено.';

				if ( $productsCount > $progress ) {
					$offset         = apply_filters( 'exchange_products_relationships_offset', 500, $productsCount,
						$filename );
					$products       = array_slice( $products, $progress, $offset );
					$sizeOfProducts = sizeof( $products );

					$progress = Plugin::set_session_arg( 'progress', $progress + $sizeOfProducts );

					Update::relationships( $products );

					// Plugin::get_session_arg( 'update' )
					// Plugin::get_session_arg( 'meta' )
					// $summary = " (всего $progress из $productsCount обработано)";
					$msg = "$sizeOfProducts зависимостей товаров обновлено.";

					// Do not retry! Go next.
					if ( $progress >= $productsCount ) {
						Plugin::delete_session_arg( 'step' );
						Plugin::delete_session_arg( 'progress' );

						Plugin::exit("success\n$msg");
					}

					Plugin::exit( "progress\n$msg" );
				}

				if ( $offersCount > $progress ) {
					$offset       = apply_filters( 'exchange_offers_relationships_offset', 500, $offersCount,
						$filename );
					$offers       = array_slice( $offers, $progress, $offset );
					$sizeOfOffers = sizeof( $offers );

					$progress = Plugin::set_session_arg( 'progress', $progress + $sizeOfOffers );

					Update::relationships( $offers );

					// Plugin::get_session_arg( 'update' )
					// Plugin::get_session_arg( 'meta' )
					// $summary = "(всего {$progress} из $offersCount обработано)";
					$msg     = "$sizeOfOffers зависимостей предложений обновлено.";

					/** Require retry */
					if ( $progress < $offersCount ) {
						Plugin::exit( "progress\n$msg" );
					}

					if ( $version < 3 ) {
						Plugin::set_mode( 'deactivate' );
						Plugin::exit( "progress\n$msg" );
					}

					Plugin::delete_session_arg( 'step' );
					Plugin::delete_session_arg( 'progress' );
					Plugin::delete_session_arg( 'update' );
					Plugin::delete_session_arg( 'meta' );

					Plugin::exit( "success\n$msg" );
				}
			}

			Plugin::exit( "success" ); // \n$mode
			break;

		/**
		 * E. Деактивация данных
		 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=deactivate
		 * @return 'progress|success|failure'
		 * @note We need always update post_modified for true deactivate
		 * @since  3.0
		 */
		case 'deactivate':
			/**
			 * Чистим и пересчитываем количество записей в терминах
			 */
			if ( ! $start_date = get_option( 'exchange_start-date', '' ) ) {
				Plugin::set_mode( 'complete' );
				Plugin::exit( "success\nDeactivate not required." );
			}

			/**
			 * move .xml files from exchange folder
			 */
			$path_dir = Parser::get_dir();
			$files    = Parser::get_files();

			if ( ! empty( $files ) ) {
				reset( $files );

				/**
				 * Meta data from any finded file
				 * @var array { version: float, is_full: bool }
				 */
				$summary = Plugin::get_summary_meta( current( $files ) );

				foreach ( $files as $file ) {
					// @unlink($file);
					$pathname = $path_dir . '/_debug_' . date( 'Ymd' ) . '/';
					@mkdir( $pathname );
					@rename( $file, $pathname . ltrim( basename( $file ), "./\\" ) );
				}

				/**
				 * Need deactivate deposits products
				 * $summary['version'] < 3 && $version < 3 &&
				 */
				if ( true === $summary['is_full'] ) {
					$post_lost = Plugin::get( 'post_lost' );

					if ( ! $post_lost ) {
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
					} elseif ( 'delete' == $post_lost ) {
						// delete query
					}
				}
			}

			/**
			 * Restore products with good offer when status is panding.
			 */
			$wpdb->query( "
				UPDATE $wpdb->posts p SET p.post_status = 'publish'
					WHERE p.post_type = 'product'
						AND p.post_status = 'pending'
						AND p.post_modified > '$start_date'
						AND 0 < ( SELECT meta_value FROM $wpdb->postmeta WHERE post_id = p.ID AND meta_key = '_price' )
						AND 0 < ( SELECT meta_value FROM $wpdb->postmeta WHERE post_id = p.ID AND meta_key = '_stock' )
			" );

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
				Plugin::exit( "progress\n$msg" );
			}

			Plugin::exit( "success\n$msg" );
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

			Plugin::exit( "success\nВыгрузка данных завершена" );
			break;

		case 'success':
			Plugin::exit( "success\n" );
			break;

		default:
			Plugin::error( "Unknown mode" );
			break;
	}
}