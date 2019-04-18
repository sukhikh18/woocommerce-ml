<?php

/*
 * Plugin Name: 1c4wp
 * Plugin URI: https://github.com/nikolays93
 * Description: 1c exchange prototype
 * Version: 0.2
 * Author: NikolayS93
 * Author URI: https://vk.com/nikolays_93
 * Author EMAIL: NikolayS93@ya.ru
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 1c4wp
 * Domain Path: /languages/
 */

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

// define('EX_DEBUG_ONLY', TRUE);

if ( !defined( 'ABSPATH' ) ) exit('You shall not pass');
if (version_compare(PHP_VERSION, '5.4') < 0) {
    throw new \Exception('Plugin requires PHP 5.4 or above');
}

if( !defined(__NAMESPACE__ . '\PLUGIN_DIR') ) define(__NAMESPACE__ . '\PLUGIN_DIR', __DIR__);
if( !defined(__NAMESPACE__ . '\PLUGIN_FILE') ) define(__NAMESPACE__ . '\PLUGIN_FILE', __FILE__);

require_once ABSPATH . "wp-admin/includes/plugin.php";
require_once PLUGIN_DIR . '/vendor/autoload.php';

/**
 * Uniq prefix
 */
if(!defined(__NAMESPACE__ . '\DOMAIN')) define(__NAMESPACE__ . '\DOMAIN', Plugin::get_plugin_data('TextDomain'));

/**
 * Server can get max size
 */
if(!defined(__NAMESPACE__ . '\FILE_LIMIT')) define(__NAMESPACE__ . '\FILE_LIMIT', null);

/**
 * Work in charset
 */
if(!defined(__NAMESPACE__ . '\XML_CHARSET') ) define(__NAMESPACE__ . '\XML_CHARSET', 'UTF-8');

/**
 * Notice type
 * @todo check this
 */
if (!defined(__NAMESPACE__ . '\SUPPRESS_NOTICES')) define(__NAMESPACE__ . '\SUPPRESS_NOTICES', false);

/**
 * Simple products only
 * @todo check this
 */
if (!defined(__NAMESPACE__ . '\DISABLE_VARIATIONS')) define(__NAMESPACE__ . '\DISABLE_VARIATIONS', false);

/**
 * Current timestamp
 */
if (!defined(__NAMESPACE__ . '\TIMESTAMP')) define(__NAMESPACE__ . '\TIMESTAMP', time());

/**
 * Auth cookie name
 */
if (!defined(__NAMESPACE__ . '\COOKIENAME')) define(__NAMESPACE__ . '\COOKIENAME', 'ex-auth');

/**
 * Woocommerce currency for single price type
 * @todo move to function
 */
if (!defined(__NAMESPACE__ . '\CURRENCY')) define(__NAMESPACE__ . '\CURRENCY', null);
if(!defined('NikolayS93\Exchange\Model\EXT_ID')) define('NikolayS93\Exchange\Model\EXT_ID', '_ext_ID');
if (!defined('EX_EXT_METAFIELD')) define('EX_EXT_METAFIELD', 'EXT_ID');

add_action( 'plugins_loaded', __NAMESPACE__ . '\__init', 10 );
function __init() {

    // $Plugin = Plugin::getInstance();

    // require_once PLUGIN_DIR . '/include/class-parser.php';
    // require_once PLUGIN_DIR . '/include/class-update.php';

    require_once PLUGIN_DIR . '/include/register.php';


    /** @var Admin\Page */
    $Page = new Admin\Page( Plugin::get_option_name(), __('1C Exchange', DOMAIN), array(
        'parent'      => 'woocommerce',
        'menu'        => __('1C Exchange', DOMAIN),
        // 'validate'    => array($this, 'validate_options'),
        'permissions' => 'manage_options',
        'columns'     => 2,
    ) );

    $Page->set_assets( function() {
        wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url('/admin/assets/exchange-page.css') );
        wp_enqueue_script( 'exchange-requests', Plugin::get_plugin_url('/admin/assets/exchange-requests.js') );
        wp_localize_script('exchange-requests', DOMAIN, array(
            'debug_only' => Utils::is_debug(),
            'exchange_url' => site_url('/exchange/'),
        ) );

        /**
         * Upload Script
         */
        wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url('/admin/assets/exchange-upload-ui.js') );
    } );

    $Page->set_content( function() {
        Plugin::get_admin_template('menu-page', false, $inc = true);
    } );

    $Page->add_section( new Admin\Section(
        'reportbox',
        __('Report', DOMAIN),
        function() {
            Plugin::get_admin_template('section', false, $inc = true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'statusbox',
        __('Status', DOMAIN),
        function() {
            Plugin::get_admin_template('statusbox', false, $inc = true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'uploadbox',
        __('Upload New Files', DOMAIN),
        function() {
            Plugin::get_admin_template('uploadbox', false, $inc = true);
        }
    ) );
}

// add_filter( '1c4wp_update_term', __NAMESPACE__ . '\update_term_filter', $priority = 10, $accepted_args = 1 );
function update_term_filter( $arTerm ) {
    /**
     * @todo fixit
     * Update only parents (Need for second query)
     */
    $res['panret'] = $arTerm['parent'];

    return $res;
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
// register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );

// $_SERVER['PHP_AUTH_USER'] = 'wordpress';
// $_SERVER['PHP_AUTH_PW'] = 'q1w2';
// // class tax_rates
//
// $ProductCat   = new ExchangeTerm();
// $Property     = new ExchangeTerm();
// /** @var ExchangeTerm Need parse values */
// $Developer    = new ExchangeTerm();
// $Warehouse    = new ExchangeTerm();
//
// $Product      = new ExchangePost();
// $Offer        = new ExchangePost();

function getTaxonomyByExternal( $raw_ext_code )
{
    global $wpdb;

    $rsResult = $wpdb->get_results( $wpdb->prepare("
        SELECT wat.*, watm.* FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id
        WHERE watm.meta_value = %d
        LIMIT 1
        ", $raw_ext_code) );

    if( $res ) {
        $res = current($rsResult);
        $obResult = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $obResult;
}

function getAttributesMap()
{
    global $wpdb;

    $arResult = array();
    $rsResult = $wpdb->get_results( "
        SELECT wat.*, watm.*, watm.meta_value as ext FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id" );

    echo "<pre>";
    var_dump( $rsResult );
    echo "</pre>";
    die();

    foreach ($rsResult as $res)
    {
        $arResult[ $res->meta_value ] = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $arResult;
}