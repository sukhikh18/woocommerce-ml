<?php

namespace NikolayS93\Exchange;

if( !function_exists('save_get_request') ) {
    /**
     * Get requested data
     */
    function save_get_request() {
        $value = false;

        if( isset($_REQUEST[ $k ]) ) {
            $value = sanitize_text_field($_REQUEST[ $k ]);
        }

        return apply_filters('get_request__' . $k, $value);
    }
}

if( !function_exists('get_full_request_uri') ) {
    function get_full_request_uri() {
        $uri = 'http';
        if (@$_SERVER['HTTPS'] == 'on') $uri .= 's';
        $uri .= "://{$_SERVER['SERVER_NAME']}";
        if ($_SERVER['SERVER_PORT'] != 80) $uri .= ":{$_SERVER['SERVER_PORT']}";
        if (isset($_SERVER['REQUEST_URI'])) $uri .= $_SERVER['REQUEST_URI'];

        return $uri;
    }
}

if( !function_exists('check_zip_extension') ) {
    /**
     * Zip functions required
     */
    function check_zip_extension() {
        @exec("which unzip", $_, $status);
        $is_zip = @$status === 0 || class_exists('ZipArchive');
        if (!$is_zip) Plugin::error("The PHP extension zip is required.");
    }
}

if( !function_exists('filesize_to_bytes') ) {
    function filesize_to_bytes($filesize) {
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
}

if( !function_exists('get_filesize_limit') ) {
    function get_filesize_limit() {
        // wp_max_upload_size()
        $file_limits = array(
            filesize_to_bytes('10M'),
            filesize_to_bytes(ini_get('post_max_size')),
            filesize_to_bytes(ini_get('memory_limit')),
        );

        @exec("grep ^MemFree: /proc/meminfo", $output, $status);
        if (@$status === 0 && $output) {
            $output = preg_split("/\s+/", $output[0]);
            $file_limits[] = intval($output[1] * 1000 * 0.7);
        }

        if (FILE_LIMIT) $file_limits[] = filesize_to_bytes(FILE_LIMIT);
        $file_limit = min($file_limits);

        return $file_limit;
    }
}

if( !function_exists('disable_time_limit') ) {
    function disable_time_limit() {
        $disabled_functions = explode(',', ini_get('disable_functions'));
        if (!in_array('set_time_limit', $disabled_functions)) @set_time_limit(0);
    }
}

if( !function_exists('check_wp_auth') ) {
    function check_wp_auth() {
        global $user_id;

        if (preg_match("/ Development Server$/", $_SERVER['SERVER_SOFTWARE'])) return;

        if( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if (!$user_id = $user->ID) Plugin::error("Not logged in");
        }
        elseif( !empty($_COOKIE[ COOKIENAME ]) ) {
            $user = wp_validate_auth_cookie($_COOKIE[ COOKIENAME ], 'auth');
            if (!$user_id = $user) Plugin::error("Invalid cookie");
        }

        Plugin::check_user_permissions($user_id);
    }
}

if( !function_exists('esc_cyr') ) {
    /**
     * Escape cyrilic chars
     */
    function esc_cyr($s, $context = 'url') {
        if( 'url' == $context ) {
            $s = strip_tags( (string) $s);
            $s = str_replace(array("\n", "\r"), " ", $s);
            $s = preg_replace("/\s+/", ' ', $s);
        }

        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = strtr($s, array(
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e',
            'ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch',
            'ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''
        ) );

        if( 'url' == $context ) {
            $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
            $s = str_replace(" ", "-", $s);
        }

        return $s;
    }
}

/**
 * Waste?
 */

// if( !function_exists('getTaxonomyByExternal') ) {
//     function getTaxonomyByExternal( $raw_ext_code ) {
//         global $wpdb;

//         $rsResult = $wpdb->get_results( $wpdb->prepare("
//             SELECT wat.*, watm.* FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
//             INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id
//             WHERE watm.meta_value = %d
//             LIMIT 1
//             ", $raw_ext_code) );

//         if( $rsResult ) {
//             $res = current($rsResult);
//             $obResult = new ExchangeAttribute( $res, $res->meta_value );
//         }

//         return $obResult;
//     }
// }

// if( !function_exists('getAttributesMap') ) {
//     function getAttributesMap() {
//         global $wpdb;

//         $arResult = array();
//         $rsResult = $wpdb->get_results( "
//             SELECT wat.*, watm.*, watm.meta_value as ext FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
//             INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id" );

//         die();

//         foreach ($rsResult as $res)
//         {
//             $arResult[ $res->meta_value ] = new ExchangeAttribute( $res, $res->meta_value );
//         }

//         return $arResult;
//     }
// }

// if( !function_exists('parse_decimal') ) {
//     function parse_decimal($number) {
//         $number = str_replace(array(',', ' '), array('.', ''), $number);

//         return (float) $number;
//     }
// }

// if( !function_exists('sanitize_price') ) {
//     function sanitize_price($string, $delimiter = '.') {
//         $price = 0;

//         if( $string ) {
//             $arrPriceStr = explode($delimiter, $string);
//             foreach ($arrPriceStr as $i => $priceStr)
//             {
//                 if( sizeof($arrPriceStr) !== $i + 1 ) {
//                     $price += (int)preg_replace("/\D/", '', $priceStr);
//                 }
//                 else {
//                     $price += (int)$priceStr / 100;
//                 }
//             }
//         }

//         return $price;
//     }
// }

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
