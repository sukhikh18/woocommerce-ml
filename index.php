<?php

/*
 * Plugin Name: 1c4wp
 * Plugin URI: https://github.com/nikolays93
 * Description: 1c exchange prototype
 * Version: 0.0.1
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

require_once PLUGIN_DIR . '/include/Creational/Singleton.php';
require_once PLUGIN_DIR . '/include/class-plugin.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\__init', 10 );
function __init() {

    $Plugin = Plugin::getInstance();

    /**
     * include required files
     */
    $autoload = PLUGIN_DIR . '/vendor/autoload.php';
    if( file_exists($autoload) ) include $autoload;

    require_once PLUGIN_DIR . '/include/utils.php';

    require_once PLUGIN_DIR . '/include/ORM/Collection.php';
    require_once PLUGIN_DIR . '/include/ORM/ExchangeItemMeta.php';

    require_once PLUGIN_DIR . '/include/Model/Interfaces/ExternalCode.php';

    require_once PLUGIN_DIR . '/include/Model/Relationship.php';
    require_once PLUGIN_DIR . '/include/Model/ExchangePost.php';
    require_once PLUGIN_DIR . '/include/Model/ExchangeProduct.php';
    require_once PLUGIN_DIR . '/include/Model/ExchangeOffer.php';
    require_once PLUGIN_DIR . '/include/Model/ExchangeAttribute.php';
    require_once PLUGIN_DIR . '/include/Model/ExchangeTerm.php';

    require_once PLUGIN_DIR . '/include/class-parser.php';
    require_once PLUGIN_DIR . '/include/class-update.php';

    require_once PLUGIN_DIR . '/include/register.php';
    require_once PLUGIN_DIR . '/include/exchange.php';


    /** @var Admin\Page */
    $Page = $Plugin->addMenuPage(__('1C Exchange', DOMAIN), array(
        'parent' => 'woocommerce',
        'menu' => __('1C Exchange', DOMAIN),
    ));

    $Page->set_assets( function() {
        wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url('/admin/assets/exchange-page.css') );
        wp_enqueue_script( 'exchange-requests', Plugin::get_plugin_url('/admin/assets/exchange-requests.js') );
        wp_localize_script('exchange-requests', DOMAIN, array(
            'debug_only' => is_debug(),
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