<?php

namespace NikolayS93\Exchange;

if ( ! defined( 'ABSPATH' ) ) exit; // disable direct access

class Utils
{
    static $is_transaction = false;

    /**
     * Check consts
     */
    static function save_get_request( $k )
    {
        $value = false;

        if( isset($_REQUEST[ $k ]) ) {
            $value = sanitize_text_field($_REQUEST[ $k ]);
        }

        return apply_filters('get_request__' . $k, $value);
    }

    static function get_filename()
    {
        return static::save_get_request('filename');
    }

    static function get_type()
    {
        return static::save_get_request('type');
    }

    static function get_mode()
    {
        $mode = static::save_get_request('mode');


        if( !in_array($mode, array('checkauth', 'init')) && $ownMode = Plugin::get('mode') ) {
            $mode = $ownMode;
        }

        return $mode;
    }

    static function is_debug_show() {
        return (!defined('WP_DEBUG_DISPLAY') && defined('WP_DEBUG') && true == WP_DEBUG) ||
        defined('WP_DEBUG_DISPLAY') && true == WP_DEBUG_DISPLAY;
    }

    static function is_debug() {
        return ( defined('EX_DEBUG_ONLY') && TRUE === EX_DEBUG_ONLY );
    }

    static function get_full_request_uri() {
        $uri = 'http';
        if (@$_SERVER['HTTPS'] == 'on') $uri .= 's';
        $uri .= "://{$_SERVER['SERVER_NAME']}";
        if ($_SERVER['SERVER_PORT'] != 80) $uri .= ":{$_SERVER['SERVER_PORT']}";
        if (isset($_SERVER['REQUEST_URI'])) $uri .= $_SERVER['REQUEST_URI'];

        return $uri;
    }

    /**
     * Escapes string
     */
    static function esc_cyr($s, $context = 'url')
    {
        if( 'url' == $context ) {
            $s = strip_tags( (string) $s);
            $s = str_replace(array("\n", "\r"), " ", $s);
            $s = preg_replace("/\s+/", ' ', $s);
        }

        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
        if( 'url' == $context ) {
            $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
            $s = str_replace(" ", "-", $s);
        }
        return $s;
    }

    // static function parse_decimal($number)
    // {
    //     $number = str_replace(array(',', ' '), array('.', ''), $number);

    //     return (float) $number;
    // }

    // static function sanitize_price( $string, $delimiter = '.' )
    // {
    //     $price = 0;

    //     if( $string ) {
    //         $arrPriceStr = explode($delimiter, $string);
    //         foreach ($arrPriceStr as $i => $priceStr)
    //         {
    //             if( sizeof($arrPriceStr) !== $i + 1 ) {
    //                 $price += (int)preg_replace("/\D/", '', $priceStr);
    //             }
    //             else {
    //                 $price += (int)$priceStr / 100;
    //             }
    //         }
    //     }

    //     return $price;
    // }

    /**
     * @todo
     */
    static function addLog( $err, $thing = false )
    {
        if( is_wp_error( $err ) ) {
            $err = $err->get_error_code() . ': ' . $err->get_error_message();
        }

        if( $thing ) {
            var_dump( $thing );
            echo "<br><br>";
        }

        static::error($err);
    }

    static function get_status( $num )
    {
        $statuses = array(
            0 => 'Not initialized',
            1 => 'Started',
            2 => 'imported',
            3 => 'Cached',
            4 => 'Terms updated',
            5 => 'Products updated',
            6 => 'Relationships updated',
        );

        return isset($statuses[ $num ]) ? $statuses[ $num ] : false;
    }

    /**
     * Zip functions
     */
    static function check_zip()
    {
        @exec("which unzip", $_, $status);
        $is_zip = @$status === 0 || class_exists('ZipArchive');
        if (!$is_zip) static::error("The PHP extension zip is required.");
    }

    static function filesize_to_bytes($filesize) {
        switch (substr($filesize, -1)) {
            case 'G':
            case 'g':
            return (int) $filesize * 1000000000;
            case 'M':
            case 'm':
            return (int) $filesize * 1000000;
            case 'K':
            case 'k':
            return (int) $filesize * 1000;
            default:
            return $filesize;
        }
    }

    static function get_filesize_limit()
    {
        // wp_max_upload_size()
        $file_limits = array(
            static::filesize_to_bytes('10M'),
            static::filesize_to_bytes(ini_get('post_max_size')),
            static::filesize_to_bytes(ini_get('memory_limit')),
        );

        @exec("grep ^MemFree: /proc/meminfo", $output, $status);
        if (@$status === 0 && $output) {
            $output = preg_split("/\s+/", $output[0]);
            $file_limits[] = intval($output[1] * 1000 * 0.7);
        }

        if (FILE_LIMIT) $file_limits[] = static::filesize_to_bytes(FILE_LIMIT);
        $file_limit = min($file_limits);

        return $file_limit;
    }

    /**
    * @param  Array   $paths for ex. glob("$fld/*.zip")
    * @param  String  $dir   for ex. EX_DATA_DIR . '/catalog'
    * @param  Boolean $rm    is remove after unpack
    * @return String|true    error message | all right
    */
    static function unzip( $paths, $dir, $rm = false ) {
        // if (!$paths) sprintf("No have a paths");

        // распаковывает но возвращает статус 0
        // $command = sprintf("unzip -qqo -x %s -d %s", implode(' ', array_map('escapeshellarg', $paths)), escapeshellarg($dir));
        // @exec($command, $_, $status);

        // if (@$status !== 0) {
        foreach ($paths as $zip_path) {
            $zip = new \ZipArchive();
            $result = $zip->open($zip_path);
            if ($result !== true) return sprintf("Failed open archive %s with error code %d", $zip_path, $result);

            $zip->extractTo($dir) or static::error(sprintf("Failed to extract from archive %s", $zip_path));
            $zip->close() or static::error(sprintf("Failed to close archive %s", $zip_path));
        }

        if( $rm ) {
            $remove_errors = array();

            foreach ($paths as $zip_path) {
                if( !@unlink($zip_path) ) $remove_errors[] = sprintf("Failed to unlink file %s", $zip_path);
            }

            if( !empty($remove_errors) ) {
                return implode("\n", $remove_errors);
            }
        }

        return true;
        // }
    }

    /**
    * errors
    */
    static function error($message, $type = "Error", $no_exit = false) {
        global $ex_is_error;

        $ex_is_error = true;

        // failure\n think about
        $message = "$type: $message";
        $last_char = substr($message, -1);
        if (!in_array($last_char, array('.', '!', '?'))) $message .= '.';

        error_log($message);
        echo "$message\n";

        if ( static::is_debug_show() ) {
            echo "\n";
            debug_print_backtrace();

            $arInfo = array(
                "Request URI" => static::get_full_request_uri(),
                "Server API" => PHP_SAPI,
                "Memory limit" => ini_get('memory_limit'),
                "Maximum POST size" => ini_get('post_max_size'),
                "PHP version" => PHP_VERSION,
                "WordPress version" => get_bloginfo('version'),
                "Plugin version" => Plugin::get_plugin_data('Version'),
            );
            echo "\n";
            foreach ($arInfo as $info_name => $info_value)
            {
                echo "$info_name: $info_value\n";
            }
        }

        if ( !$no_exit ) {
            static::wpdb_stop();
            exit;
        }
    }

    static function wp_error($wp_error, $only_error_code = null) {
        $messages = array();
        foreach ($wp_error->get_error_codes() as $error_code) {
            if ($only_error_code && $error_code != $only_error_code) continue;

            $wp_error_messages = implode(", ", $wp_error->get_error_messages($error_code));
            $wp_error_messages = strip_tags($wp_error_messages);
            $messages[] = sprintf("%s: %s", $error_code, $wp_error_messages);
        }

        static::error(implode("; ", $messages), "WP Error");
    }

    static function check_wpdb_error()
    {
        global $wpdb;

        if (!$wpdb->last_error) return;

        static::error(sprintf("%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query), "DB Error", true);

        static::wpdb_stop(false, true);

        exit;
    }

    /**
     * Set modes
     */
    static function disable_time_limit()
    {
        $disabled_functions = explode(',', ini_get('disable_functions'));
        if (!in_array('set_time_limit', $disabled_functions)) @set_time_limit(0);
    }

    static function is_transaction()
    {
        return static::$is_transaction;
    }

    static function set_transaction_mode()
    {
        global $wpdb;

        static::disable_time_limit();

        register_shutdown_function(__NAMESPACE__ . '\transaction_shutdown_function');

        $wpdb->show_errors(false);
        $wpdb->query("START TRANSACTION");

        static::$is_transaction = true;
        static::check_wpdb_error();
    }

    static function wpdb_stop($is_commit = false, $no_check = false)
    {
        global $wpdb, $ex_is_transaction;

        if ( !static::$is_transaction ) return;
        static::$is_transaction = false;

        $sql_query = !$is_commit ? "ROLLBACK" : "COMMIT";
        $wpdb->query($sql_query);
        if (!$no_check) Utils::check_wpdb_error();

        if (Utils::is_debug_show()) echo "\n" . strtolower($sql_query);
    }

    static function start_exchange_session() {
        set_error_handler( __NAMESPACE__ . '\strict_error_handler' );
        set_exception_handler( __NAMESPACE__ . '\strict_exception_handler' );

        ob_start( __NAMESPACE__ . '\output_callback' );
    }

    /**
     * User validation
     */
    /**
     * [check_user_permissions description]
     * @param  int|WP_User $user [description]
     * @return [type]       [description]
     */
    static function check_user_permissions( $user )
    {
        if (!user_can($user, 'shop_manager') && !user_can($user, 'administrator')) {
            static::error("No {$user} user permissions");
        }
    }

    static function check_wp_auth()
    {
        global $user_id;

        if (preg_match("/ Development Server$/", $_SERVER['SERVER_SOFTWARE'])) return;

        if( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if (!$user_id = $user->ID) static::error("Not logged in");
        }
        elseif( !empty($_COOKIE[ COOKIENAME ]) ) {
            $user = wp_validate_auth_cookie($_COOKIE[ COOKIENAME ], 'auth');
            if (!$user_id = $user) static::error("Invalid cookie");
        }

        static::check_user_permissions($user_id);
    }

    static function getTime($time = false)
    {
        return $time === false? microtime(true) : microtime(true) - $time;
    }

    static function setMode( $mode, $args = array() )
    {
        $args = wp_parse_args( $args, array(
            'mode' => $mode,
            'progress' => 0,
        ) );

        Plugin::set( $args );
    }
}

// add_action('delete_term', 'wc1c_delete_term', 10, 4);
// function wc1c_delete_term($term_id, $tt_id, $taxonomy, $deleted_term) {
//     global $wpdb;

//     if ($taxonomy != 'product_cat' && strpos($taxonomy, 'pa_') !== 0) return;

//     $wpdb->delete($wpdb->termmeta, array('term_id' => $term_id));
//     if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();
// }

// function wc1c_woocommerce_attribute_by_id($attribute_id) {
//     global $wpdb;

//     $cache_key = "wc1c_woocomerce_attribute_by_id-$attribute_id";
//     $attribute = wp_cache_get($cache_key);
//     if ($attribute === false) {
//         $attribute = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d", $attribute_id), ARRAY_A);
//         if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();

//         if ($attribute) {
//             $attribute['taxonomy'] = wc_attribute_taxonomy_name($attribute['attribute_name']);

//             wp_cache_set($cache_key, $attribute);
//         }
//     }

//     return $attribute;
// }

// function wc1c_delete_woocommerce_attribute($attribute_id) {
//     global $wpdb;

//     $attribute = wc1c_woocommerce_attribute_by_id($attribute_id);

//     if (!$attribute) return false;

//     delete_option("{$attribute['taxonomy']}_children");

//     $terms = get_terms($attribute['taxonomy'], "hide_empty=0");
//     foreach ($terms as $term) {
//         wp_delete_term($term->term_id, $attribute['taxonomy']);
//     }

//     $wpdb->delete("{$wpdb->prefix}woocommerce_attribute_taxonomies", compact('attribute_id'));
//     if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();
// }

/**
* Utilites
*/

// function ex_post_id_by_meta($key, $value) {
//     global $wpdb;

//     if ($value === null) return;

//     $cache_key = "ex_post_id_by_meta-$key-$value";
//     $post_id = wp_cache_get($cache_key);
//     if ($post_id === false) {
//         $post_id = $wpdb->get_var($wpdb->prepare("
//             SELECT post_id FROM $wpdb->postmeta
//             JOIN $wpdb->posts ON post_id = ID
//             WHERE meta_key = %s AND meta_value = %s",
//             $key, $value));
//         ex_check_wpdb_error();

//         if ($post_id) wp_cache_set($cache_key, $post_id);
//     }

//     return $post_id;
// }

// function wc1c_term_id_by_meta($key, $value) {
//     global $wpdb;

//     if ($value === null) return;

//     $cache_key = "wc1c_term_id_by_meta-$key-$value";
//     $term_id = wp_cache_get($cache_key);
//     if ($term_id === false) {
//         $term_id = $wpdb->get_var($wpdb->prepare("SELECT tm.term_id FROM $wpdb->termmeta tm JOIN $wpdb->terms t ON tm.term_id = t.term_id WHERE meta_key = %s AND meta_value = %s", $key, $value));
//         wc1c_check_wpdb_error();

//         if ($term_id) wp_cache_set($cache_key, $term_id);
//     }

//     return $term_id;
// }


/**
* Transaction session
*/

// function ex_xml_start_element_handler($parser, $name, $attrs) {
//     global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

//     $wc1c_names[] = $name;
//     $wc1c_depth++;

//     call_user_func("wc1c_{$wc1c_namespace}_start_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $attrs);

//     static $element_number = 0;
//     $element_number++;
//     if ($element_number > 1000) {
//         $element_number = 0;
//         wp_cache_flush();
//     }
// }

// function ex_xml_character_data_handler($parser, $data) {
//     global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

//     $name = $wc1c_names[$wc1c_depth];

//     call_user_func("wc1c_{$wc1c_namespace}_character_data_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $data);
// }

// function ex_xml_end_element_handler($parser, $name) {
//     global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

//     call_user_func("wc1c_{$wc1c_namespace}_end_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name);

//     array_pop($wc1c_names);
//     $wc1c_depth--;
// }

// function ex_check_head_meta($path) {
//     $version = null;
//     $is_full = null;
//     $is_moysklad = null;
//     $filename = basename($path);

//     $fp = @fopen($path, 'r') or static::error(sprintf("Failed to open file %s", $filename));

//     while (($buffer = fgets($fp)) !== false) {
//         if( false !== $pos = strpos($buffer, " ВерсияСхемы=") ) {
//             $version = floatval( substr($buffer, $pos + 25, 4) );
//         }

//         if( false !== strpos($buffer, " СинхронизацияТоваров=") ) {
//             $is_moysklad = true;
//         }

//         if( strpos($buffer, " СодержитТолькоИзменения=") === false && strpos($buffer, "<СодержитТолькоИзменения>") === false ) continue;
//         $is_full = strpos($buffer, " СодержитТолькоИзменения=\"false\"") !== false || strpos($buffer, "<СодержитТолькоИзменения>false<") !== false;
//         break;
//     }

//     $meta_data = stream_get_meta_data($fp);
//     $filename = basename($meta_data['uri']);

//     @rewind($fp) or static::error(sprintf("Failed to rewind on file %s", $filename));
//     @fclose($fp) or static::error(sprintf("Failed to close file %s", $filename));

//     return array($is_full, $is_moysklad, $version);
// }

// add_action('wp_ajax_exchange_files_upload', __NAMESPACE__ . '\exchange_files_upload' );
// function exchange_files_upload() {
//     $file_errors = array(
//         0 => "There is no error, the file uploaded with success",
//         1 => "The uploaded file exceeds the upload_max_files in server settings",
//         2 => "The uploaded file exceeds the MAX_FILE_SIZE from html form",
//         3 => "The uploaded file uploaded only partially",
//         4 => "No file was uploaded",
//         6 => "Missing a temporary folder",
//         7 => "Failed to write file to disk",
//         8 => "A PHP extension stoped file to upload",
//         9 => "File already exists.",
//         10 => "File is too large. Max file size.",
//         500 => "Upload Failed.",
//     );

//     $posted_data =  isset( $_POST ) ? $_POST : array();
//     $file_data = isset( $_FILES ) ? $_FILES : array();
//     $data = array_merge( $posted_data, $file_data );
//     $response = array();
//     $need_unpack = false;

//     $upload_path = EX_DATA_DIR . 'catalog/';

//     if(!file_exists($upload_path)) mkdir($upload_path);

//     for ($i=0; isset( $data["file_" . $i] ) ; $i++) {
//         $file = $data["file_" . $i];

//         /**
//         * Get sanitized filename
//         */
//         $fullname = explode( '.', $file["name"] );
//         $ext = array_pop( $fullname );
//         $file_name = implode('.', array_map(array(__NAMESPACE__ . '\Utils', 'esc_cyr'), $fullname));
//         $file_name .= '.' . $ext;

//         $file_path = $upload_path . $file_name;

//         $response['FILE_' . $i] = array(
//             'name' => $file_name,
//             'tmp_name' => $file["tmp_name"],
//             'size' => $file["size"],
//             'error' => $file["error"],
//             'path' => $file_path,
//             'ext' => $ext,
//         );

//         if( file_exists($file_path) ) $file_error = 9;
//         if( get_file_limit() < $file_size ) $file_error = 10;

//         if($file["error"] > 0) {
//             $response["response"] = "ERROR";
//             $response["error"] = $file_errors[ $file_error ];
//         }
//     }

//     if( "ERROR" !== $response["response"] ) {
//         for ($i=0; isset( $response['FILE_' . $i] ) ; $i++) {
//             $sFile = $response['FILE_' . $i];

//             if( move_uploaded_file( $sFile['tmp_name'], $sFile['path'] ) ) {
//                 $info = pathinfo( $sFile['path'] );
//                 if( !empty( $info["extension"] ) ) $sFile['ext'] = $info["extension"];

//                 if( 'zip' == $sFile['ext'] ) $need_unpack = true;
//             }
//             else {
//                 $response["response"] = "ERROR";
//                 $response["error"] = $file_errors[ 500 ];
//                 break;
//             }
//         }
//     }

//     if( $need_unpack ) {
//         $zip_paths = glob("$upload_path/*.zip");

//         if( !empty($zip_paths) ) {
//             if( true !== ($r = ex_unzip( $zip_paths, $upload_path, $remove = true )) ) {
//                 $response["response"] = "ERROR";
//                 $response["error"] = $r;
//             }
//         }
//     }

//     if("ERROR" !== $response["response"]) {
//         $response["response"] = "SUCCESS";

//         $ParserFactory = new ParserFactory();
//         $ParserFactory->clearTerms();
//         $ParserFactory->clearProducts();
//     }

//     echo wp_json_encode( $response );
//     die();
// }
