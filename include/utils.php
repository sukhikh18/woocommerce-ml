<?php
/**
 * Allowed modes from GET
 *
 * @return array
 */
namespace NikolayS93\Exchange;

function get_allowed_modes() {
    $allowed = apply_filters(
        Plugin::PREFIX . 'get_allowed_modes',
        array(
            'checkauth',
            'init',
            'file',
            'import',
            'import_posts',
            'import_relations',
            'deactivate',
            'complete',
        )
    );

    return (array) $allowed;
}

function get_allowed_types() {
    return array( 'catalog' );
}

function get_upload_dir() {
    $wp_upload_dir = wp_upload_dir();

    return apply_filters( Plugin::PREFIX . "get_upload_dir",
        $wp_upload_dir['basedir'], $wp_upload_dir );
}

/**
 * @param string $dir
 *
 * @return bool is make try
 */
function try_make_dir( $dir = '' ) {
    if ( ! is_dir( $dir ) ) {
        @mkdir( $dir, 0777, true ) or Error()->add_message( printf(
            __( "Sorry but %s not has write permissions", Plugin::DOMAIN ),
            $dir
        ), "Error" );

        return true;
    }

    return false;
}

function check_writable( $dir ) {
    if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
        Error()->add_message( printf(
            __( "Sorry but %s not found. Direcory is writable?", Plugin::DOMAIN ),
            $dir
        ) );
    }
}

function get_exchange_dir( $namespace = null ) {
    $path = get_upload_dir() . "/1c-exchange/" . $namespace;
    $dir = trailingslashit( apply_filters( Plugin::PREFIX . "get_exchange_dir", $path, $namespace ) );

    try_make_dir( $dir );
    check_writable( $dir );

    return realpath( $dir );
}

function get_exchange_file( $filepath, $namespace = 'catalog' ) {
    if ( ! empty( $filepath['path'] ) ) {
        $filepath = $filepath['path'];
    }

    $path = get_exchange_dir( $namespace ) . '/' . $filepath;
    if( file_is_readable( $path ) ) {
        $file = new \SplFileObject( $path );

        if ( 'xml' == strtolower( $file->getExtension() ) ) {
            return $file->getPathname();
        }
    }

    return false;
}

function get_exchange_files( $filename = null, $namespace = 'catalog' ) {
    $arResult = array();

    // Get all folder objects
    $dir = get_exchange_dir( $namespace );

    $objects = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( $dir ),
        \RecursiveIteratorIterator::SELF_FIRST
    );

    /**
     * Check objects name
     */
    foreach ( $objects as $path => $object ) {

        if ( ! empty( $filename ) ) {
            /**
             * Filename start with search string
             */
            if ( 0 === strpos( $object->getBasename(), $filename ) ) {
                $arResult[] = $path;
            }
        } else {
            /**
             * Get all xml files
             */
            $arResult[] = $path;
        }
    }

    return $arResult;
}

/**
 * @param int|\WP_User $user User ID or object.
 *
 * @return bool
 */
function has_permissions( $user ) {
    $permissions = plugin()->get_permissions();
    if( is_string( $permissions ) ) {
        $permissions = array( $permissions );
    }

    if( is_array( $permissions ) ) {
        foreach ( $permissions as $permission ) {
            if ( user_can( $user, $permission ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return \WP_Error|\WP_User
 */
function check_current_user() {
    global $user_id;

    if ( is_user_logged_in() ) {
        $user         = wp_get_current_user();
        $user_id      = $user->ID;
        $method_error = __( 'User not logged in', Plugin::DOMAIN );
    } elseif ( ! empty( $_COOKIE[ EXCHANGE_COOKIE_NAME ] ) ) {
        $user         = wp_validate_auth_cookie( $_COOKIE[ EXCHANGE_COOKIE_NAME ], 'auth' );
        $user_id      = $user->ID;
        $method_error = __( 'Invalid cookie', Plugin::DOMAIN );
    } else {
        $user_id      = 0;
        $method_error = __( 'User not identified', Plugin::DOMAIN );
    }

    if ( ! $user_id ) {
        $user = new \WP_Error( 'AUTH_ERROR', $method_error );
    }

    if ( ! has_permissions( $user_id ) ) {
        $user = new \WP_Error(
            'AUTH_ERROR',
            sprintf(
                'User %s has not permissions',
                get_user_meta( $user_id, 'nickname', true )
            )
        );
    }

    return $user;
}

function get_message_by_filename( $file_name = '' ) {
    switch ( true ) {
        case 0 === strpos( $file_name, 'price' ):
            return '%s из %s цен обработано.';

        case 0 === strpos( $file_name, 'rest' ):
            return '%s из %s запасов обработано.';

        default:
            return '%s из %s предложений обработано.';
    }
}

function mb_ucfirst( $string, $enc = 'UTF-8' ) {
    if ( function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) ) {
        return mb_strtoupper( mb_substr( $string, 0, 1, $enc ), $enc ) .
               mb_substr( $string, 1, mb_strlen( $string, $enc ), $enc );
    }

    return ucfirst( $string );
}

function get_time( $time = false ) {
    return $time === false ? microtime( true ) : microtime( true ) - $time;
}

function is_debug() {
    return ( defined( 'WP_DEBUG_SHOW' ) && WP_DEBUG_SHOW ) ||
        ( ! defined( 'WP_DEBUG_SHOW' ) && defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Zip functions required
 */
function check_zip_extension() {
    // @exec( "which unzip", $_, $status );
    // ! 0 === @$status

    if ( ! class_exists( 'ZipArchive' ) ) {
        return new \WP_Error( 'ZIP_ABSENT', 'The PHP extension zip is required.' );
    }

    return true;
}

/**
 * @param array|string $paths for ex. glob("$fld/*.zip")
 * @param String $dir for ex. EX_DATA_DIR . '/catalog'
 * @param Boolean $rm is remove after unpack
 *
 * @return String|true    error message | all right
 */
function unzip( $zip_path, $dir, $nondelete = false ) {
    $zip    = new \ZipArchive();
    $result = $zip->open( $zip_path );
    if ( $result !== true ) {
        return sprintf( 'Failed open archive %s with error code %d', $zip_path, $result );
    }

    $zip->extractTo( $dir ) or Error()->add_message( sprintf( 'Failed to extract from archive %s', $zip_path ) );
    $zip->close() or Error()->add_message( sprintf( 'Failed to close archive %s', $zip_path ) );

    if ( ! $nondelete ) {
        unlink( $zip_path );
    }

    return true;
}

function esc_external( $ext ) {
    $pos = stripos( $ext, '/' );
    if ( false !== $pos ) {
        $ext = substr( $ext, $pos );
    }

    return $ext;
}

/**
 * Escape cyrillic chars
 */
function esc_cyr( $s, $context = 'url' ) {
    if ( 'url' === $context ) {
        $s = wp_strip_all_tags( (string) $s );
        $s = str_replace( array( "\n", "\r" ), ' ', $s );
        $s = preg_replace( '/\s+/', ' ', $s );
    }

    $s = trim( $s );
    $s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
    $s = strtr(
        $s,
        array(
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ы' => 'y',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'ъ' => '',
            'ь' => '',
        )
    );

    if ( 'url' === $context ) {
        $s = preg_replace( '/[^0-9a-z-_ ]/i', '', $s );
        $s = str_replace( ' ', '-', $s );
    }

    return $s;
}

function check_mode( $id, $setting ) {
    switch ( $setting ) {
        case 'off':
            return false;

        case 'create':
            return ! $id;

        case 'update':
            return (bool) $id;
    }

    return true;
}
