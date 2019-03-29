<?php

namespace NikolayS93\Exchange;

if ( ! defined( 'ABSPATH' ) ) exit; // disable direct access

function check_zip()
{
    @exec("which unzip", $_, $status);
    $is_zip = @$status === 0 || class_exists('ZipArchive');
    if (!$is_zip) ex_error("The PHP extension zip is required.");
}

function get_maxsizelimit()
{
    // wp_max_upload_size()
    $file_limits = array(
        ex_filesize_to_bytes('10M'),
        ex_filesize_to_bytes(ini_get('post_max_size')),
        ex_filesize_to_bytes(ini_get('memory_limit')),
    );

    @exec("grep ^MemFree: /proc/meminfo", $output, $status);
    if (@$status === 0 && $output) {
        $output = preg_split("/\s+/", $output[0]);
        $file_limits[] = intval($output[1] * 1000 * 0.7);
    }

    if (EXCHANGE_FILE_LIMIT) $file_limits[] = ex_filesize_to_bytes(EXCHANGE_FILE_LIMIT);
    $file_limit = min($file_limits);

    return $file_limit;
}

function get_status( $num )
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

function parse_decimal($number)
{
    $number = str_replace(array(',', ' '), array('.', ''), $number);

    return (float) $number;
}
function sanitize_price( $string, $delimiter = '.' ) {
    if( ! $string ) {
        return '';
    }

    $arrPriceStr = explode($delimiter, $string);
    $price = 0;
    foreach ($arrPriceStr as $i => $priceStr) {
        if( sizeof($arrPriceStr) !== $i + 1 ){
            $price += (int)preg_replace("/\D/", '', $priceStr);
        }
        else {
            $price += (int)$priceStr / 100;
        }
    }

    return $price;
}

function is_debug_show() {
    return (!defined('WP_DEBUG_DISPLAY') && defined('WP_DEBUG') && true == WP_DEBUG) ||
    defined('WP_DEBUG_DISPLAY') && true == WP_DEBUG_DISPLAY;
}

function is_debug() {
    return ( defined('EX_DEBUG_ONLY') && TRUE === EX_DEBUG_ONLY );
}

// add_action('delete_term', 'wc1c_delete_term', 10, 4);
// function wc1c_delete_term($term_id, $tt_id, $taxonomy, $deleted_term) {
//     global $wpdb;

//     if ($taxonomy != 'product_cat' && strpos($taxonomy, 'pa_') !== 0) return;

//     $wpdb->delete($wpdb->termmeta, array('term_id' => $term_id));
//     if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();
// }

function wc1c_woocommerce_attribute_by_id($attribute_id) {
    global $wpdb;

    $cache_key = "wc1c_woocomerce_attribute_by_id-$attribute_id";
    $attribute = wp_cache_get($cache_key);
    if ($attribute === false) {
        $attribute = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d", $attribute_id), ARRAY_A);
        if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();

        if ($attribute) {
            $attribute['taxonomy'] = wc_attribute_taxonomy_name($attribute['attribute_name']);

            wp_cache_set($cache_key, $attribute);
        }
    }

    return $attribute;
}

function wc1c_delete_woocommerce_attribute($attribute_id) {
    global $wpdb;

    $attribute = wc1c_woocommerce_attribute_by_id($attribute_id);

    if (!$attribute) return false;

    delete_option("{$attribute['taxonomy']}_children");

    $terms = get_terms($attribute['taxonomy'], "hide_empty=0");
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, $attribute['taxonomy']);
    }

    $wpdb->delete("{$wpdb->prefix}woocommerce_attribute_taxonomies", compact('attribute_id'));
    if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();
}

/**
* Utilites
*/

function ex_post_id_by_meta($key, $value) {
    global $wpdb;

    if ($value === null) return;

    $cache_key = "ex_post_id_by_meta-$key-$value";
    $post_id = wp_cache_get($cache_key);
    if ($post_id === false) {
        $post_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM $wpdb->postmeta
            JOIN $wpdb->posts ON post_id = ID
            WHERE meta_key = %s AND meta_value = %s",
            $key, $value));
        ex_check_wpdb_error();

        if ($post_id) wp_cache_set($cache_key, $post_id);
    }

    return $post_id;
}

function wc1c_term_id_by_meta($key, $value) {
    global $wpdb;

    if ($value === null) return;

    $cache_key = "wc1c_term_id_by_meta-$key-$value";
    $term_id = wp_cache_get($cache_key);
    if ($term_id === false) {
        $term_id = $wpdb->get_var($wpdb->prepare("SELECT tm.term_id FROM $wpdb->termmeta tm JOIN $wpdb->terms t ON tm.term_id = t.term_id WHERE meta_key = %s AND meta_value = %s", $key, $value));
        wc1c_check_wpdb_error();

        if ($term_id) wp_cache_set($cache_key, $term_id);
    }

    return $term_id;
}


function ex_full_request_uri() {
    $uri = 'http';
    if (@$_SERVER['HTTPS'] == 'on') $uri .= 's';
    $uri .= "://{$_SERVER['SERVER_NAME']}";
    if ($_SERVER['SERVER_PORT'] != 80) $uri .= ":{$_SERVER['SERVER_PORT']}";
    if (isset($_SERVER['REQUEST_URI'])) $uri .= $_SERVER['REQUEST_URI'];

    return $uri;
}

function ex_disable_time_limit() {
    $disabled_functions = explode(',', ini_get('disable_functions'));
    if (!in_array('set_time_limit', $disabled_functions))
        @set_time_limit(0);
}

function ex_filesize_to_bytes($filesize) {
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

function get_file_limit() {
    $file_limits = array(
        ex_filesize_to_bytes('10M'),
        ex_filesize_to_bytes(ini_get('post_max_size')),
        ex_filesize_to_bytes(ini_get('memory_limit')),
    );

    @exec("grep ^MemFree: /proc/meminfo", $output, $status);
    if (@$status === 0 && $output) {
        $output = preg_split("/\s+/", $output[0]);
        $file_limits[] = intval($output[1] * 1000 * 0.7);
    }
    if (EXCHANGE_FILE_LIMIT) $file_limits[] = ex_filesize_to_bytes(EXCHANGE_FILE_LIMIT);
    $file_limit = min($file_limits);

    return $file_limit;
}


/**
* @param  Array   $paths for ex. glob("$fld/*.zip")
* @param  String  $dir   for ex. EX_DATA_DIR . '/catalog'
* @param  Boolean $rm    is remove after unpack
* @return String|true    error message | all right
*/
function ex_unzip( $paths, $dir, $rm = false ) {
// if (!$paths) sprintf("No have a paths");

// распаковывает но возвращает статус 0
// $command = sprintf("unzip -qqo -x %s -d %s", implode(' ', array_map('escapeshellarg', $paths)), escapeshellarg($dir));
// @exec($command, $_, $status);

// if (@$status !== 0) {
    foreach ($paths as $zip_path) {
        $zip = new \ZipArchive();
        $result = $zip->open($zip_path);
        if ($result !== true) return sprintf("Failed open archive %s with error code %d", $zip_path, $result);

        $zip->extractTo($dir) or ex_error(sprintf("Failed to extract from archive %s", $zip_path));
        $zip->close() or ex_error(sprintf("Failed to close archive %s", $zip_path));
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
* Transaction session
*/

function ex_xml_start_element_handler($parser, $name, $attrs) {
    global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

    $wc1c_names[] = $name;
    $wc1c_depth++;

    call_user_func("wc1c_{$wc1c_namespace}_start_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $attrs);

    static $element_number = 0;
    $element_number++;
    if ($element_number > 1000) {
        $element_number = 0;
        wp_cache_flush();
    }
}

function ex_xml_character_data_handler($parser, $data) {
    global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

    $name = $wc1c_names[$wc1c_depth];

    call_user_func("wc1c_{$wc1c_namespace}_character_data_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name, $data);
}

function ex_xml_end_element_handler($parser, $name) {
    global $wc1c_namespace, $wc1c_is_full, $wc1c_names, $wc1c_depth;

    call_user_func("wc1c_{$wc1c_namespace}_end_element_handler", $wc1c_is_full, $wc1c_names, $wc1c_depth, $name);

    array_pop($wc1c_names);
    $wc1c_depth--;
}

function ex_set_transaction_mode() {
    global $wpdb, $ex_is_transaction;

    ex_disable_time_limit();

    register_shutdown_function(__NAMESPACE__ . '\ex_transaction_shutdown_function');

    $wpdb->show_errors(false);

    $ex_is_transaction = true;
    $wpdb->query("START TRANSACTION");
    ex_check_wpdb_error();
}

function ex_transaction_shutdown_function() {
    $error = error_get_last();

    $is_commit = !isset($error['type']) || $error['type'] > E_PARSE;

    ex_wpdb_stop($is_commit);
}

function ex_wpdb_stop($is_commit = false, $no_check = false) {
    global $wpdb, $ex_is_transaction;

    if (empty($ex_is_transaction)) return;

    $ex_is_transaction = false;

    $sql_query = !$is_commit ? "ROLLBACK" : "COMMIT";
    $wpdb->query($sql_query);
    if (!$no_check) ex_check_wpdb_error();

    if (is_debug_show()) echo "\n" . strtolower($sql_query);
}



function ex_check_head_meta($path) {
    $version = null;
    $is_full = null;
    $is_moysklad = null;
    $filename = basename($path);

    $fp = @fopen($path, 'r') or ex_error(sprintf("Failed to open file %s", $filename));

    while (($buffer = fgets($fp)) !== false) {
        if( false !== $pos = strpos($buffer, " ВерсияСхемы=") ) {
            $version = floatval( substr($buffer, $pos + 25, 4) );
        }

        if( false !== strpos($buffer, " СинхронизацияТоваров=") ) {
            $is_moysklad = true;
        }

        if( strpos($buffer, " СодержитТолькоИзменения=") === false && strpos($buffer, "<СодержитТолькоИзменения>") === false ) continue;
        $is_full = strpos($buffer, " СодержитТолькоИзменения=\"false\"") !== false || strpos($buffer, "<СодержитТолькоИзменения>false<") !== false;
        break;
    }

    $meta_data = stream_get_meta_data($fp);
    $filename = basename($meta_data['uri']);

    @rewind($fp) or ex_error(sprintf("Failed to rewind on file %s", $filename));
    @fclose($fp) or ex_error(sprintf("Failed to close file %s", $filename));

    return array($is_full, $is_moysklad, $version);
}

add_action('wp_ajax_exchange_files_upload', __NAMESPACE__ . '\exchange_files_upload' );
function exchange_files_upload() {
    $file_errors = array(
        0 => "There is no error, the file uploaded with success",
        1 => "The uploaded file exceeds the upload_max_files in server settings",
        2 => "The uploaded file exceeds the MAX_FILE_SIZE from html form",
        3 => "The uploaded file uploaded only partially",
        4 => "No file was uploaded",
        6 => "Missing a temporary folder",
        7 => "Failed to write file to disk",
        8 => "A PHP extension stoped file to upload",
        9 => "File already exists.",
        10 => "File is too large. Max file size.",
        500 => "Upload Failed.",
    );

    $posted_data =  isset( $_POST ) ? $_POST : array();
    $file_data = isset( $_FILES ) ? $_FILES : array();
    $data = array_merge( $posted_data, $file_data );
    $response = array();
    $need_unpack = false;

    $upload_path = EX_DATA_DIR . 'catalog/';

    if(!file_exists($upload_path)) mkdir($upload_path);

    for ($i=0; isset( $data["file_" . $i] ) ; $i++) {
        $file = $data["file_" . $i];

/**
* Get sanitized filename
*/
$fullname = explode( '.', $file["name"] );
$ext = array_pop( $fullname );
$file_name = implode('.', array_map(array(__NAMESPACE__ . '\Utils', 'esc_cyr'), $fullname));
$file_name .= '.' . $ext;

$file_path = $upload_path . $file_name;

$response['FILE_' . $i] = array(
    'name' => $file_name,
    'tmp_name' => $file["tmp_name"],
    'size' => $file["size"],
    'error' => $file["error"],
    'path' => $file_path,
    'ext' => $ext,
);

if( file_exists($file_path) ) $file_error = 9;
if( get_file_limit() < $file_size ) $file_error = 10;

if($file["error"] > 0) {
    $response["response"] = "ERROR";
    $response["error"] = $file_errors[ $file_error ];
}
}

if( "ERROR" !== $response["response"] ) {
    for ($i=0; isset( $response['FILE_' . $i] ) ; $i++) {
        $sFile = $response['FILE_' . $i];

        if( move_uploaded_file( $sFile['tmp_name'], $sFile['path'] ) ) {
            $info = pathinfo( $sFile['path'] );
            if( !empty( $info["extension"] ) ) $sFile['ext'] = $info["extension"];

            if( 'zip' == $sFile['ext'] ) $need_unpack = true;
        }
        else {
            $response["response"] = "ERROR";
            $response["error"] = $file_errors[ 500 ];
            break;
        }
    }
}

if( $need_unpack ) {
    $zip_paths = glob("$upload_path/*.zip");

    if( !empty($zip_paths) ) {
        if( true !== ($r = ex_unzip( $zip_paths, $upload_path, $remove = true )) ) {
            $response["response"] = "ERROR";
            $response["error"] = $r;
        }
    }
}

if("ERROR" !== $response["response"]) {
    $response["response"] = "SUCCESS";

    $ParserFactory = new ParserFactory();
    $ParserFactory->clearTerms();
    $ParserFactory->clearProducts();
}

echo wp_json_encode( $response );
die();
}

/**
* [check_user_permissions description]
* @param  int|WP_User $user [description]
* @return [type]       [description]
*/
function check_user_permissions( $user )
{
    if (!user_can($user, 'shop_manager') && !user_can($user, 'administrator')) {
        ex_error("No permissions");
    }
}

function check_wp_auth() {
    global $user_id;

    if (preg_match("/ Development Server$/", $_SERVER['SERVER_SOFTWARE'])) return;

    if( is_user_logged_in() ) {
        $user = wp_get_current_user();
        if (!$user_id = $user->ID) ex_error("Not logged in");
    }
    elseif( !empty($_COOKIE['ex-auth']) ) {
        $user = wp_validate_auth_cookie($_COOKIE['ex-auth'], 'auth');
        if (!$user_id = $user) ex_error("Invalid cookie");
    }

    check_user_permissions($user_id);
}

function getTime($time = false)
{
    return $time === false? microtime(true) : microtime(true) - $time;
}

/**
* Errors
*/
function ex_error($message, $type = "Error", $no_exit = false) {
    global $ex_is_error;

    $ex_is_error = true;

    $message = "$type: $message";
    $last_char = substr($message, -1);
    if (!in_array($last_char, array('.', '!', '?'))) $message .= '.';

    error_log($message);
    echo "$message\n";

    if (is_debug_show()) {
        echo "\n";
        debug_print_backtrace();

        $info = array(
            "Request URI" => ex_full_request_uri(),
            "Server API" => PHP_SAPI,
            "Memory limit" => ini_get('memory_limit'),
            "Maximum POST size" => ini_get('post_max_size'),
            "PHP version" => PHP_VERSION,
            "WordPress version" => get_bloginfo('version'),
            "Plugin version" => print_r(Plugin::$data['Version'], 1),
        );
        echo "\n";
        foreach ($info as $info_name => $info_value) {
            echo "$info_name: $info_value\n";
        }
    }

    if (!$no_exit) {
        ex_wpdb_stop();

        exit;
    }
}

function ex_wp_error($wp_error, $only_error_code = null) {
    $messages = array();
    foreach ($wp_error->get_error_codes() as $error_code) {
        if ($only_error_code && $error_code != $only_error_code) continue;

        $wp_error_messages = implode(", ", $wp_error->get_error_messages($error_code));
        $wp_error_messages = strip_tags($wp_error_messages);
        $messages[] = sprintf("%s: %s", $error_code, $wp_error_messages);
    }

    ex_error(implode("; ", $messages), "WP Error");
}

function ex_check_wpdb_error() {
    global $wpdb;

    if (!$wpdb->last_error) return;

    ex_error(sprintf("%s for query \"%s\"", $wpdb->last_error, $wpdb->last_query), "DB Error", true);

    ex_wpdb_stop(false, true);

    exit;
}

/**
* Attribute
*/
function valid_attribute_name( $attribute_name ) {
    if ( strlen( $attribute_name ) >= 28 ) {
        return new \WP_Error( 'error', sprintf( __( 'Slug "%s" is too long (28 characters max). Shorten it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
    } elseif ( \wc_check_if_attribute_name_is_reserved( $attribute_name ) ) {
        return new \WP_Error( 'error', sprintf( __( 'Slug "%s" is not allowed because it is a reserved term. Change it, please.', 'woocommerce' ), sanitize_title( $attribute_name ) ) );
    }

    return true;
}

function proccess_add_attribute($attribute) {
    global $wpdb;
// check_admin_referer( 'woocommerce-add-new_attribute' );

    if( empty($attribute['attribute_type']) ) { $attribute['attribute_type'] = 'text'; }
    if( empty($attribute['attribute_orderby']) ) { $attribute['attribute_orderby'] = 'menu_order'; }
    if( empty($attribute['attribute_public']) ) { $attribute['attribute_public'] = 0; }

    if ( empty( $attribute['attribute_name'] ) || empty( $attribute['attribute_label'] ) ) {
        return new \WP_Error( 'error', __( 'Please, provide an attribute name and slug.', 'woocommerce' ) );
    }
    elseif ( ( $valid_attribute_name = valid_attribute_name( $attribute['attribute_name'] ) ) && is_wp_error( $valid_attribute_name ) ) {
        return $valid_attribute_name;
    }
    elseif ( taxonomy_exists( wc_attribute_taxonomy_name( $attribute['attribute_name'] ) ) ) {
        return new \WP_Error( 'error', sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'woocommerce' ), sanitize_title( $attribute['attribute_name'] ) ) );
    }

    $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );

    do_action( 'woocommerce_attribute_added', $wpdb->insert_id, $attribute );

// flush_rewrite_rules();
    delete_transient( 'wc_attribute_taxonomies' );

    return true;
}
// $string_custom_attr_values = 'S|M|L|XL';
// $arr_custom_attr_values = explode("|", $string_custom_attr_values);
// $total_variations = count($arr_custom_attr_values);
// $variation_post_id = $post_id;
// for($i = 1; $i <= $total_variations; $i++) {
//     $variation_post_id += $i;
//     $variation_post = array(
//         'post_title' => 'Variation #' . $variation_post_id . ' of ' . $item['termek'],
//         'post_name'     => 'product-' . $variation_post_id . '-variation',
//         'post_status'   => 'publish',
//         'post_parent'   => $post_id,
//         'post_type'     => 'product_variation',
//         'guid'          =>  home_url() . '/product_variation/product-' . $variation_post_id . '-variation',
//         'menu_order'    =>  $i
//  );

//     // Insert the variation post into the database
//     $variation_post_id = wp_insert_post( $variation_post );
//     update_post_meta( $variation_post_id, 'attribute_'.$custom_attribute_name, $arr_custom_attr_values[$i-1]);

//     /*Rest of the post_meta like in the base product*/
// }

// add_action('plugins_loaded', function() {
//     $attributes = array(
//         "Another custom attribute" => (string)"M|L",
//     );

//     $i = 0;
//     $product_attributes = array();
//     foreach($attributes as $key => $value) {
//         $product_attributes[sanitize_title($key)] = array (
//             'name' => wc_clean($key), // set attribute name
//             'value' => $value, // set attribute value
//             'position' => ++$i,
//             'is_visible' => 1,
//             'is_variation' => 0,
//             'is_taxonomy' => 0
//         );
//     }

//     update_post_meta(19, '_product_attributes', $product_attributes);
// });

function strict_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    if (error_reporting() === 0) return false;

    switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
        $type = "Notice";
        break;
        case E_WARNING:
        case E_USER_WARNING:
        $type = "Warning";
        break;
        case E_ERROR:
        case E_USER_ERROR:
        $type = "Fatal Error";
        break;
        default:
        $type = "Unknown Error";
        break;
    }

    $message = sprintf("%s in %s on line %d", $errstr, $errfile, $errline);
    ex_error($message, "PHP $type");
}

function strict_exception_handler($exception)
{
    $message = sprintf("%s in %s on line %d", $exception->getMessage(), $exception->getFile(), $exception->getLine());
    ex_error($message, "Exception");
}

function set_strict_mode()
{
    set_error_handler( array(__CLASS__, 'strict_error_handler') );
    set_exception_handler( array(__CLASS__, 'strict_exception_handler') );
}

function output_callback($buffer)
{
    global $ex_is_error;

    if ( !headers_sent() ) {
        $is_xml = @$_GET['mode'] == 'query';
        $content_type = !$is_xml || $ex_is_error ? 'text/plain' : 'text/xml';
        header("Content-Type: $content_type; charset=" . XML_CHARSET);
    }

    $buffer = (XML_CHARSET == 'UTF-8') ? "\xEF\xBB\xBF$buffer" : mb_convert_encoding($buffer, XML_CHARSET, 'UTF-8');

    return $buffer;
}