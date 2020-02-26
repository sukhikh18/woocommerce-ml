<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // disable direct access


/**
 * abstract
 */
class Plugin {
	/**
	 * @var array Commented data about plugin in root file
	 */
	protected static $data;

	static function uninstall() {
		delete_option( static::get_option_name() );
	}

	static function activate() {
		add_option( static::get_option_name(), array() );

		/**
		 * Create empty folder for (temporary) exhange data
		 */
		if ( ! is_dir( static::get_exchange_data_dir() ) ) {
			mkdir( static::get_exchange_data_dir() );
		}
		// flush_rewrite_rules();

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// $index_table_names = array(
		//     // $wpdb->postmeta,
		//     $wpdb->termmeta,
		//     $wpdb->usermeta,
		// );

		// $index_name = 'ex_meta_key_meta_value';

		// // XML/05e26d70-01e4-11dc-a411-00055d80a2d1#218a1598-044b-11dc-a414-00055d80a2d1
		// foreach ($index_table_names as $index_table_name) {
		//     $result = $wpdb->get_var("SHOW INDEX FROM $index_table_name WHERE Key_name = '$index_name';");
		//     if ($result) continue;

		//     $wpdb->query("ALTER TABLE $index_table_name ADD INDEX $index_name (meta_key, meta_value(78))");
		// }

		/**
		 * Maybe insert posts mime_type INDEX if is not exists
		 */
		$postmimeIndexName = 'id_post_mime_type';
		$result            = $wpdb->get_var( "SHOW INDEX FROM $wpdb->posts WHERE Key_name = '$postmimeIndexName';" );
		if ( ! $result ) {
			$wpdb->query( "ALTER TABLE $wpdb->posts ADD INDEX $postmimeIndexName (ID, post_mime_type(78))" );
		}

		/**
		 * Maybe create taxonomymeta table
		 */
		$taxonomymeta = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$taxonomymeta'" ) != $taxonomymeta ) {
			/** Required for dbDelta */
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			dbDelta( "CREATE TABLE {$taxonomymeta} (
                `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `tax_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) NULL,
                `meta_value` longtext NULL
            ) {$charset_collate};" );

			$wpdb->query( "
                ALTER TABLE {$taxonomymeta}
                    ADD INDEX `tax_id` (`tax_id`),
                    ADD INDEX `meta_key` (`meta_key`(191));" );
		}
	}

	/**
	 * Get data about this plugin
	 *
	 * @param string|null $arg array key (null for all data)
	 *
	 * @return mixed
	 */
	public static function get_plugin_data( $arg = null ) {
		/** Fill if is empty */
		if ( empty( static::$data ) ) {
			static::$data = get_plugin_data( PLUGIN_FILE );
			load_plugin_textdomain( static::$data['TextDomain'], false, basename( PLUGIN_DIR ) . '/languages/' );
		}

		/** Get by key */
		if ( $arg ) {
			return isset( static::$data[ $arg ] ) ? static::$data[ $arg ] : null;
		}

		/** Get all */
		return static::$data;
	}

	/**
	 * Get option name for a options in the Wordpress database
	 */
	public static function get_option_name( $context = 'admin' ) {
		$option_name = DOMAIN;
		if ( 'admin' == $context ) {
			$option_name .= '_adm';
		}

		return apply_filters( "get_{DOMAIN}_option_name", $option_name, $context );
	}

	/**
	 * Получает url (адресную строку) до плагина
	 *
	 * @param string $path путь должен начинаться с / (по аналогии с __DIR__)
	 *
	 * @return string
	 */
	public static function get_plugin_url( $path = '' ) {
		$url = plugins_url( basename( PLUGIN_DIR ) ) . $path;

		return apply_filters( "get_{DOMAIN}_plugin_url", $url, $path );
	}

	/**
	 * [get_template description]
	 *
	 * @param  [type]  $template [description]
	 * @param boolean $slug [description]
	 * @param array $data @todo
	 *
	 * @return string|false
	 */
	public static function get_template( $template, $slug = false, $data = array() ) {
		/**
		 * @note think about strripos
		 */
		if ( false !== strripos( $template, '.' ) ) {
			@list( $template, $ext ) = explode( '.', $template );
		} else {
			$ext = 'php';
		}

		$paths = array();

		if ( $slug ) {
			$paths[] = PLUGIN_DIR . "/$template-$slug.$ext";
		}
		$paths[] = PLUGIN_DIR . "/$template.$ext";

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * [get_admin_template description]
	 *
	 * @param string $tpl [description]
	 * @param array $data [description]
	 * @param boolean $include [description]
	 *
	 * @return string
	 */
	public static function get_admin_template( $tpl = '', $data = array(), $include = false ) {
		$filename = static::get_template( 'admin/template/' . $tpl, false, $data );

		if ( $data ) {
			extract( $data );
		}

		if ( $filename && $include && file_exists( $filename ) ) {
			include $filename;
		}

		return $filename;
	}

	/**
	 * Получает параметр из опции плагина
	 *
	 * @param string $prop_name Ключ опции плагина или null (вернуть опцию целиком)
	 * @param mixed $default Что возвращать, если параметр не найден
	 *
	 * @return mixed
	 * @todo Добавить фильтр
	 *
	 */
	public static function get( $prop_name = null, $default = false, $context = 'admin' ) {
		$option_name = static::get_option_name( $context );

		/**
		 * Получает настройку из кэша или из базы данных
		 * @link https://codex.wordpress.org/Справочник_по_функциям/get_option
		 * @var mixed
		 */
		$option = get_option( $option_name, $default );
		$option = apply_filters( "get_{DOMAIN}_option", $option );

		if ( ! $prop_name || 'all' == $prop_name ) {
			return ! empty( $option ) ? $option : $default;
		}

		return isset( $option[ $prop_name ] ) ? $option[ $prop_name ] : $default;
	}

	/**
	 * Установит параметр в опцию плагина
	 *
	 * @param mixed $prop_name Ключ опции плагина || array(параметр => значение)
	 * @param string $value значение (если $prop_name не массив)
	 * @param string $context
	 *
	 * @return bool             Совершились ли обновления @see update_option()
	 * @todo Подумать, может стоит сделать $autoload через фильтр, а не параметр
	 *
	 */
	public static function set( $prop_name, $value = '', $context = 'admin' ) {
		if ( ! $prop_name ) {
			return;
		}
		if ( $value && ! (string) $prop_name ) {
			return;
		}
		if ( ! is_array( $prop_name ) ) {
			$prop_name = array( (string) $prop_name => $value );
		}

		$option = static::get( null, false, $context );

		foreach ( $prop_name as $prop_key => $prop_value ) {
			$option[ $prop_key ] = $prop_value;
		}

		if ( ! empty( $option ) ) {
			$option_name = static::get_option_name( $context );
			$autoload    = null;
			if ( 'admin' == $context ) {
				$autoload = 'no';
			}

			return update_option( $option_name, $option, $autoload );
		}

		return false;
	}

	/********************************************** Plugin Customization **********************************************/

	static function get_exchange_data_dir() {
		$wp_upload_dir = wp_upload_dir();

		return apply_filters( "get_exchange_data_dir", $wp_upload_dir['basedir'] . "/1c-exchange/" );
	}

	static function is_debug_show() {
		return ( ! defined( 'WP_DEBUG_DISPLAY' ) && defined( 'WP_DEBUG' ) && true == WP_DEBUG ) ||
		       defined( 'WP_DEBUG_DISPLAY' ) && true == WP_DEBUG_DISPLAY;
	}

	static function getTime( $time = false ) {
		return $time === false ? microtime( true ) : microtime( true ) - $time;
	}

	protected static function get_file_array() {
		$filepath  = (string) save_get_request( 'filename' );
		$path      = wp_parse_url( $filepath, PHP_URL_PATH );
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		$filename  = pathinfo( $path, PATHINFO_FILENAME );

		return array(
			'~path' => $filepath,
			'~name' => $filename,
			'ext'   => $extension,
		);
	}

	static function get_filename() {
		$file               = static::get_file_array();
		$allowed_extensions = array( 'xml', 'zip' );

		$file['path'] = ltrim( $file['~path'], "./\\" );
		$file['name'] = ltrim( $file['~name'], "./\\" );

		if ( ! $file['name'] ) {
			Plugin::error( "Filename is empty" );
		}

		if ( ! in_array( $file['ext'], $allowed_extensions, true ) ) {
			Error()->add_message( 'Тип файла противоречит политике безопасности.' );
		}

		return $file['name'] . '.' . $file['ext'];
	}

	static function get_type() {
		return save_get_request( 'type' );
	}

	static function get_mode() {
		$mode = save_get_request( 'mode' );

		if ( 'import' === $mode ) {
			$ownMode = Plugin::get( 'mode', 'false', 'status' );
			if ( $ownMode && 'false' !== $ownMode ) {
				$mode = $ownMode;
			}
		}

		return $mode;
	}

	static function set_mode( $mode, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'mode'     => $mode,
			'progress' => 0,
		) );

		Plugin::set( $args, null, 'status' );
	}

	/**
	 * errors
	 */
	static function error( $message, $type = "Error", $no_exit = false ) {
		global $ex_is_error;

		$ex_is_error = true;

		// failure\n think about
		$message   = "$type: $message";
		$last_char = substr( $message, - 1 );
		if ( ! in_array( $last_char, array( '.', '!', '?' ) ) ) {
			$message .= '.';
		}

		static::write_log( PLUGIN_DIR . "/logs/errors.log", str_replace( "\n", ', ', $message ) );
		echo "$message\n";

		if ( static::is_debug_show() ) {
			echo "\n";
			debug_print_backtrace();

			$arInfo = array(
				"Request URI"       => get_full_request_uri(),
				"Server API"        => PHP_SAPI,
				"Memory limit"      => ini_get( 'memory_limit' ),
				"Maximum POST size" => ini_get( 'post_max_size' ),
				"PHP version"       => PHP_VERSION,
				"WordPress version" => get_bloginfo( 'version' ),
				"Plugin version"    => Plugin::get_plugin_data( 'Version' ),
			);
			echo "\n";
			foreach ( $arInfo as $info_name => $info_value ) {
				echo "$info_name: $info_value\n";
			}
		}

		if ( ! $no_exit ) {
			static::wpdb_stop();
			exit;
		}
	}

	static function wp_error( $wp_error, $only_error_code = null ) {
		$messages = array();
		foreach ( $wp_error->get_error_codes() as $error_code ) {
			if ( $only_error_code && $error_code != $only_error_code ) {
				continue;
			}

			$wp_error_messages = implode( ", ", $wp_error->get_error_messages( $error_code ) );
			$wp_error_messages = strip_tags( $wp_error_messages );
			$messages[]        = sprintf( "%s: %s", $error_code, $wp_error_messages );
		}

		static::error( implode( "; ", $messages ), "WP Error" );
	}

	static function check_wpdb_error() {
		global $wpdb;

		if ( ! $wpdb->last_error ) {
			return;
		}

		static::error( sprintf( "%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query ), "DB Error", true );

		static::wpdb_stop( false, true );

		exit;
	}

	/**
	 * @param Array $paths for ex. glob("$fld/*.zip")
	 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
	 * @param Boolean $rm is remove after unpack
	 *
	 * @return String|true    error message | all right
	 */
	static function unzip( $paths, $dir, $rm = false ) {
		if ( ! is_array( $paths ) ) {
			$paths = array( $paths );
		}

		// распаковывает но возвращает статус 0
		// $command = sprintf("unzip -qqo -x %s -d %s", implode(' ', array_map('escapeshellarg', $paths)), escapeshellarg($dir));
		// @exec($command, $_, $status);

		// if (@$status !== 0) {
		foreach ( $paths as $zip_path ) {
			$zip    = new \ZipArchive();
			$result = $zip->open( $zip_path );
			if ( $result !== true ) {
				return sprintf( "Failed open archive %s with error code %d", $zip_path, $result );
			}

			$zip->extractTo( $dir ) or static::error( sprintf( "Failed to extract from archive %s", $zip_path ) );
			$zip->close() or static::error( sprintf( "Failed to close archive %s", $zip_path ) );
		}

		if ( $rm ) {
			$remove_errors = array();

			foreach ( $paths as $zip_path ) {
				if ( ! @unlink( $zip_path ) ) {
					$remove_errors[] = sprintf( "Failed to unlink file %s", $zip_path );
				}
			}

			if ( ! empty( $remove_errors ) ) {
				return implode( "\n", $remove_errors );
			}
		}

		return true;
		// }
	}

	static $is_transaction = false;

	static function is_transaction() {
		return static::$is_transaction;
	}

	static function set_transaction_mode() {
		global $wpdb;

		disable_time_limit();

		register_shutdown_function( __NAMESPACE__ . '\transaction_shutdown_function' );

		$wpdb->show_errors( false );
		$wpdb->query( "START TRANSACTION" );

		static::$is_transaction = true;
		static::check_wpdb_error();
	}

	static function wpdb_stop( $is_commit = false, $no_check = false ) {
		global $wpdb, $ex_is_transaction;

		if ( ! static::$is_transaction ) {
			return;
		}
		static::$is_transaction = false;

		$sql_query = ! $is_commit ? "ROLLBACK" : "COMMIT";
		$wpdb->query( $sql_query );
		if ( ! $no_check ) {
			Utils::check_wpdb_error();
		}

		if ( Utils::is_debug_show() ) {
			echo "\n" . strtolower( $sql_query );
		}
	}

	static function start_exchange_session() {
		set_error_handler( __NAMESPACE__ . '\strict_error_handler' );
		set_exception_handler( __NAMESPACE__ . '\strict_exception_handler' );

		ob_start( __NAMESPACE__ . '\output_callback' );
	}

	/**
	 * User validation
	 * [check_user_permissions description]
	 *
	 * @param int|WP_User $user [description]
	 *
	 * @return [type]       [description]
	 */
	static function check_user_permissions( $user ) {
		if ( ! user_can( $user, 'shop_manager' ) && ! user_can( $user, 'administrator' ) ) {
			static::error( "No {$user} user permissions" );
		}
	}

	static function get_summary_meta( $filename ) {
		$version = 0;
		$is_full = null;
		// $is_moysklad = null;

		$fp = @fopen( $filename, 'r' ) or static::error( sprintf( "Failed to open file %s", $filename ) );

		while ( ( $buffer = fgets( $fp ) ) !== false ) {
			if ( false !== $pos = strpos( $buffer, " ВерсияСхемы=" ) ) {
				$version = substr( $buffer, $pos + 14, 4 );
			}

			// if( false !== strpos($buffer, " СинхронизацияТоваров=") ) {
			//     $is_moysklad = true;
			// }

			if ( strpos( $buffer, " СодержитТолькоИзменения=" ) === false && strpos( $buffer,
					"<СодержитТолькоИзменения>" ) === false ) {
				continue;
			}
			$is_full = strpos( $buffer, " СодержитТолькоИзменения=\"false\"" ) !== false || strpos( $buffer,
					"<СодержитТолькоИзменения>false<" ) !== false;
			break;
		}

		@rewind( $fp ) or static::error( sprintf( "Failed to rewind on file %s", $filename ) );
		@fclose( $fp ) or static::error( sprintf( "Failed to close file %s", $filename ) );

		$result = array(
			'version' => (float) $version,
			'is_full' => (bool) $is_full,
			// 'is_moysklad' => $is_moysklad,
		);

		return $result;
	}

	static function write_log( $file, $args, $advanced = array() ) {
		if ( empty( $args ) ) {
			return;
		}

		if ( is_array( $args ) ) {
			$arRes = array();
			foreach ( $args as $key => $value ) {
				$arRes[] = "$key=$value";
			}

			$args = implode( ', ', $arRes );
		}

		$fw = fopen( $file, "a" );
		fwrite( $fw, '[' . date( 'd.M.Y H:i:s' ) . "] " . $args . implode( ', ', $advanced ) . "\r\n" );
		fclose( $fw );
	}

	public static function exit( $message ) {
		static::write_log( PLUGIN_DIR . "/logs/results.log", str_replace( "\n", ', ', $message ) );
		exit( $message );
	}

	public static function session_start() {
		if ( ! is_session_started() ) {
			session_start();
		}
	}

	public static function get_session_arg( $key, $def = '' ) {
		return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : $def;
	}
}

// Back compat
class Utils extends Plugin {
}