<?php

namespace NikolayS93\Exchange;

use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\ExchangePost;
use NikolayS93\Exchange\Model\ExchangeOffer;
use NikolayS93\Exchange\Model\ExchangeProduct;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\ORM\Collection;
use NikolayS93\Exchange\ORM\CollectionAttributes;
use NikolayS93\Exchange\ORM\CollectionPosts;
use NikolayS93\Exchange\ORM\CollectionTerms;
use WP_REST_Server;
use WP_User;
use XMLExchange\Model\Product;

class REST_Controller {

	const STEP_UNZIP = '1';
	const STEP_TMP_DATA = '2';
	const STEP_DEACTIVATE = '3';
	const STEP_COMPLETE = '4';

	/**
	 * The namespace for the REST API routes.
	 *
	 * @var string
	 */
	public $namespace = 'exchange/v1';

	public $date_start;

	/**
	 * @var string (float value)
	 */
	public $version;
	public $step;

	public $type;
	public $mode;

	function __construct() {
		$this->date_start = plugin()->get( 'date_start', null, 'exchange' );

		$this->type    = Request::get_type();
		$this->mode    = Request::get_mode();
		$this->version = floatval( Request::get_session_arg( 'version' ) );
		$this->step    = Request::get_session_arg( 'step' );
	}

	/**
	 * @param int|\WP_User $user User ID or object.
	 *
	 * @return bool
	 */
	public function check_permissions( $user ) {
		foreach ( plugin()->get_permissions() as $permission ) {
			if ( user_can( $user, $permission ) ) {
				return true;
			}
		}

		return false;
	}

	function register_routes() {
		register_rest_route(
			$this->namespace,
			'/status/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/init/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'init' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/file/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'file' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/import/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'import' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/deactivate/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'deactivate' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/complete/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'complete' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	public function status() {
	}

	function exchange_preparing() {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=' . EXCHANGE_CHARSET );
		}

		Error::set_strict_mode();

		// CGI fix
		if ( ! $_GET && isset( $_SERVER['REQUEST_URI'] ) ) {
			$query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
			parse_str( $query, $_GET );
		}

		if ( ! $this->type || ! in_array( $this->type, array( 'catalog' ) ) ) {
			Error()->add_message( 'Type no support' );
		}

		if ( ! $this->mode ) {
			Error()->add_message( 'Mode no support' );
		} elseif( 'checkauth' !== $this->mode ) {
			$user_id = $this->get_current_user_id();

			if ( is_wp_error( $user_id ) ) {
				Error()->add_message( $user_id );
			}

			if ( ! $this->check_permissions( $user_id ) ) {
				Error()->add_message(sprintf( 'Permissions failed (UID: %d)', $user_id ));
			}
		}
	}

	/**
	 * Route 1c modes
	 *
	 * @url http://v8.1c.ru/edi/edi_stnd/131/
	 */
	function exchange() {
		switch ( $this->mode ) {
			case 'checkauth':
				$this->checkauth();
				break;
			case 'init':
				$this->init();
				break;
			case 'file':
				$this->file();
				break;
			case 'import':
				$this->import( new Parser(), new Update() );
				break;
			case 'deactivate':
				$this->deactivate();
				break;
			case 'complete':
				$this->complete();
				break;
		}

		Error()->add_message( 'Mode no support' );
	}

	/**
	 * @return \WP_Error|Int
	 *
	 * Запускается перед каждым запросом для проверки авторизации пользователя.
	 */
	private function get_current_user_id() {
		if ( is_user_logged_in() ) {
			/** @var $user WP_User */
			$user         = wp_get_current_user();
			if( ! $user->ID ) {
				return new \WP_Error( 'AUTH_ERROR', __( 'User not logged in', Plugin::DOMAIN ) );
			}
		} elseif ( ! empty( $_SESSION[ EXCHANGE_COOKIE_NAME ] ) ) {
			/** @var $user Int */
			if( ! $user = wp_validate_auth_cookie( $_SESSION[ EXCHANGE_COOKIE_NAME ], 'auth' ) ) {
				return new \WP_Error( 'AUTH_ERROR', __( 'Invalid cookie', Plugin::DOMAIN ) );
			}
		}
		else {
			return new \WP_Error('AUTH_ERROR', __( 'User not identified', Plugin::DOMAIN ));;
		}

		return is_a($user, 'WP_User') ? $user->ID : $user;
	}

	/**
	 * A. Начало сеанса (Авторизация)
	 * Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
	 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=checkauth.
	 * A. Начало сеанса
	 * http://<сайт>/<путь> /1c_exchange.php?type=sale&mode=checkauth.
	 *
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
				Error()->add_message( 'No authentication credentials' );
			}

			$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
		} else {
			$user = wp_get_current_user();
		}

		if ( is_wp_error( $user ) ) {
			Error()->add_message( $user );
			exit( "failure\n" );
		}

		if ( ! $this->check_permissions( $user ) ) {
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
		$_SESSION[ EXCHANGE_COOKIE_NAME ] = $auth_cookie;

		// exit( "success\n" . EXCHANGE_COOKIE_NAME . "\n$auth_cookie" );
		exit( implode("\n", array(
			'success',
			session_name(),
			session_id(),
			'timestamp=' . time()
		)) . "\n" );
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
	public function init() {
		$is_zip = check_zip_extension();
		if ( is_wp_error( $is_zip ) ) {
			error()->add_message( $is_zip );
		}

		// Start exchange session as first request.
		if ( ! $this->date_start ) {
			global $wpdb;

			$table_name = $wpdb->get_blog_prefix() . EXCHANGE_TMP_TABLENAME;
			$wpdb->query( "TRUNCATE TABLE $table_name" );

			plugin()->set( 'date_start', current_time( 'mysql' ), 'exchange' );
			Request::set_session_args( array(
				'step'       => '',
				'version'    => ! empty( $_GET['version'] ) ? sanitize_text_field( $_GET['version'] ) : '2.99',
			) );
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
	 *
	 * @print string 'success'
	 */
	public function file( $requested = 'php://input' ) {
		$file = Request::get_file();
		// get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable()
		$path_dir = Plugin()->get_exchange_dir( $this->type );
		$path     = $path_dir . '/' . $file['name'] . '.' . $file['ext'];

		$from     = fopen( $requested, 'r' );
		$resource = fopen( $path, 'a' );
		// @todo Do you want to get ВерсияСхемы or ДатаФормирования?
		stream_copy_to_stream( $from, $resource );
		fclose( $from );
		fclose( $resource );

		unzip( $path, $path_dir );

		if ( 'catalog' == $this->type ) {
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
	 *
	 * @feature Отсеевает товары без торгового предложения средствами временной таблицы
	 * @param Parser $Parser
	 * @param Update $Update
	 *
	 * @print 'progress|success|failure'
	 */
	public function import( $Parser, $Update ) {
		// Get current exhcange file.
		$file = Plugin()->get_exchange_file( Request::get_file() );
		check_readble( $file, $error_else = true );

		// Fill parser collection.
		$Parser->parse_all( $file );
		/** @var CollectionPosts $products */
		$products = $Parser->get_products();
		/** @var CollectionTerms $categories */
		$categories = $Parser->get_categories()->fill_exists();
		/** @var CollectionAttributes $attributes */
		$attributes = $Parser->get_properties()->fill_exists();
		/** @var CollectionPosts $offers */
		$offers = $Parser->get_offers();
		/** @var CollectionTerms $warehouses */
		$warehouses = $Parser->get_warehouses()->fill_exists();

		$terms_count = $categories->count() + $attributes->count() + $warehouses->count();
		$products_count = $products->count();
		$offers_count   = $offers->count();


		if( static::STEP_TMP_DATA !== $this->step && $terms_count > 0 ) {
			// Update terms.
			Transaction()->set_transaction_mode();



			// $Update->terms( $categories )->term_meta( $categories );
			// $Update->terms( $warehouses )->term_meta( $warehouses );

			// $Update->properties( $attributes );
			// $attribute_terms = $attributes->get_terms();
			// $Update
			// 	->terms( $attribute_terms )
			// 	->term_meta( $attribute_terms );
//			$Update->properties( $attributes );
//			$attributes->walk( function( $attribute ) use ($Update) {
//				/** @var Attribute $attribute */
//				$terms = $attribute->get_values();
//				$Update->terms( $terms )->term_meta( $terms );
//			} );

			$step = '';
			if ( $products_count || $offers_count ) {
				$Update->set_status( 'progress' );
				$step = static::STEP_TMP_DATA;
			}

			Request::set_session_args( array('step' => $step) );
			exit( "{$Update->status}\n" . implode( ' -- ', array(
				"{$Update->results['create']} terms created.",
				"{$Update->results['update']} categories/terms updated.",
				"{$Update->results['meta']} meta updated.",
			) ) );
		}

		if( $products_count ) {
			// Update temporary data.
			Transaction()->set_transaction_mode();

			$products
				->fill_exists()
				->fill_exists_terms( $Parser )
				->walk( function ( $product ) {
					/** @var $product ExchangeProduct */
					$product->write_temporary_data();
				} );
			// @todo deactivate products on CommerceML2
		}

		if ( $offers_count ) {
			// Import offers
			Transaction()->set_transaction_mode();

			$offers
				->fill_exists_terms( $Parser )
				->walk( function ( $offer ) use ( &$offers ) {
					$external = $offer->get_external();
					$merged   = false;

					if ( false !== ( $pos = strpos( $external, '#' ) ) ) {
						if ( $global_offer = $offers->offsetGet( substr( $external, 0, $pos ) ) ) {
							$global_offer->merge( $offer );
							$offers->remove( $offer );
							$merged = true;
						}
					}

					if( $merged ) {
						$global_offer->write_temporary_data();
					}
					else {
						$offer->write_temporary_data();
					}
				} );
		}

		Request::set_session_args( array('step' => '') );
		exit( "{$Update->status}\nUpdate temporary table complete." );
	}

	/**
	 * E. Деактивация данных
	 * http://<сайт>/<путь>/1c_exchange.php?type=catalog&mode=deactivate
	 *
	 * @print 'progress|success|failure'
	 * @note We need always update post_modified for true deactivate
	 * @since CommerceML 3.0
	 */
	public function deactivate() {
		global $wpdb;

		$table = Register::get_exchange_table_name();

		$wpdb->delete( $table, array(
			'product_id' => 0,
			'qty'        => 0
		) );

		$wpdb->delete( $table, array(
			'product_id' => 0,
			'price'      => 0
		) );

		// Why do not work?
		// require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		// dbDelta( "DELETE FROM `{$table}` WHERE
		// 	`product_id` = 0 AND (qty <= 0 OR price <= 0);" );
		// $wpdb->query( "DELETE FROM `{$table}` WHERE
		// 	`product_id` = 0 AND (qty <= 0 OR price <= 0);" );

		$is_full = false;
		if ( $is_full ) {
			// delete diff.
			// $all_products_id = wp_list_pluck( $wpdb->get_results( "
			// 	SELECT ID FROM $wpdb->posts p
			// 	WHERE p.post_type = 'product'
			// " ), 'ID' );
		}

		exit( "success\n" );
	}

	/**
	 * F. Завершающее событие загрузки данных
	 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=complete
	 *
	 * @since CommerceML 3.0
	 */
	public function complete() {
		global $wpdb;

		$table = Register::get_exchange_table_name();
		$offset = 100;
		$temporary_items = $wpdb->get_results( "
			SELECT * FROM $table
			LIMIT $offset
		" );

		Transaction()->set_transaction_mode();

		$offset = 100;

		$items_count     = count( $temporary_items );
		$temporary_items = array_slice( $temporary_items, 0, $offset );

		foreach ( $temporary_items as $item ) {
			$product = new ExchangeProduct( array(
				'ID'           => $item->product_id,
				'post_content' => $item->description,
				'post_title'   => $item->name,
				'post_name'    => $item->slug,
			), $item->code, array_merge( unserialize( $item->meta ), array(
				'_price' => $item->price,
				'_stock' => $item->qty,
			) ) );

			$categories = unserialize( $item->cats );
			foreach ( $categories as $category_code => $category_id ) {
				$category = new Category( array(
					'term_id'  => $category_id,
					'taxonomy' => 'product_cat'
				), $category_code );
				$product->add_category( $category );
			}

			$attributes = unserialize( $item->attributes );

			if ( $item->delete ) {
				$product->delete();
			} else {
				$product->insert();
			}

			$wpdb->delete( $table, array( 'code' => $item->code ) );
		}

		if ( $items_count > $offset ) {
			exit( "progress\n" );
		}

		exit( "success\n" );
	}
}
