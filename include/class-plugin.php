<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

if ( ! defined( 'ABSPATH' ) ) exit; // disable direct access

class Plugin
{
    use Creational\Singleton;

    /**
     * @var array Commented data on this file top
     */
    protected $data;

    /**
     * @var array Field on wo_option for this plugin
     */
    protected $options;

    function __init()
    {
        $wp_upload_dir = wp_upload_dir();

        /**
         * Define required plugin data
         */
        if(!defined(__NAMESPACE__ . '\DOMAIN')) define(__NAMESPACE__ . '\DOMAIN', static::get_plugin_data('TextDomain'));
        if(!defined(__NAMESPACE__ . '\EX_DATA_DIR')) define(__NAMESPACE__ . '\EX_DATA_DIR', $wp_upload_dir['basedir'] . "/1c-exchange/");
        if(!defined(__NAMESPACE__ . '\EXCHANGE_FILE_LIMIT')) define(__NAMESPACE__ . '\EXCHANGE_FILE_LIMIT', null);
        if(!defined(__NAMESPACE__ . '\EXCHANGE_FILE_LIMIT')) define(__NAMESPACE__ . '\EXCHANGE_FILE_LIMIT', null);
        if(!defined(__NAMESPACE__ . '\XML_CHARSET') ) define(__NAMESPACE__ . '\XML_CHARSET', 'UTF-8');
        if(!defined('NikolayS93\Exchange\Model\EXT_ID')) define('NikolayS93\Exchange\Model\EXT_ID', '_ext_ID');

        if (!defined('EX_SUPPRESS_NOTICES')) define('EX_SUPPRESS_NOTICES', false);
        if (!defined('EX_DISABLE_VARIATIONS')) define('EX_DISABLE_VARIATIONS', false);
        if (!defined('EX_TIMESTAMP')) define('EX_TIMESTAMP', time());
        if (!defined('WC1C_CURRENCY')) define('WC1C_CURRENCY', null);
        if (!defined('EX_EXT_METAFIELD')) define('EX_EXT_METAFIELD', 'EXT_ID');

        if( !defined(__NAMESPACE__ . '\TYPE') ) define(__NAMESPACE__ . '\TYPE', !empty($_REQUEST['type'])
            ? sanitize_text_field($_REQUEST['type']) : '');
        if( !defined(__NAMESPACE__ . '\MODE') ) define(__NAMESPACE__ . '\MODE', !empty($_REQUEST['mode'])
            ? sanitize_text_field($_REQUEST['mode']) : '');
        if( !defined(__NAMESPACE__ . 'FILENAME') )define(__NAMESPACE__ . '\FILENAME', !empty($_REQUEST['filename'])
            ? sanitize_text_field($_REQUEST['filename']) : '');

        load_plugin_textdomain( DOMAIN, false, basename(PLUGIN_DIR) . '/languages/' );
    }

    function addMenuPage( $pagename = '', $args = array() )
    {
        $args = wp_parse_args( $args, array(
            'parent'      => false,
            'menu'        => __('New plugin', DOMAIN),
            // 'validate'    => array($this, 'validate_options'),
            'permissions' => 'manage_options',
            'columns'     => 2,
        ) );

        $Page = new Admin\Page( static::get_option_name(), $pagename, $args );

        return $Page;
    }

    static function uninstall() { delete_option( static::get_option_name() ); }
    static function activate()
    {
        add_option( static::get_option_name(), array() );
        if( !is_dir(EX_DATA_DIR) ) mkdir(EX_DATA_DIR);
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
        $result = $wpdb->get_var("SHOW INDEX FROM $wpdb->posts WHERE Key_name = '$postmimeIndexName';");
        if (!$result) {
            $wpdb->query("ALTER TABLE $wpdb->posts ADD INDEX $postmimeIndexName (ID, post_mime_type(78))");
        }

        /**
         * Maybe create taxonomymeta table
         */
        $taxonomymeta = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';

        if( $wpdb->get_var("SHOW TABLES LIKE '$taxonomymeta'") != $taxonomymeta ) {
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

    public static function get_plugin_data( $arg = '' )
    {
        $Plugin = static::getInstance();
        if( !$Plugin->data ) {
            $Plugin->data = get_plugin_data(PLUGIN_FILE);
        }

        if( $arg ) {
            return isset( $Plugin->data[ $arg ] ) ? $Plugin->data[ $arg ] : '';
        }

        return $Plugin->data;
    }

    /**
     * Get option name for a options in the Wordpress database
     */
    public static function get_option_name()
    {
        return apply_filters("get_{DOMAIN}_option_name", DOMAIN);
    }

        /**
     * Получает url (адресную строку) до плагина
     * @param  string $path путь должен начинаться с / (по аналогии с __DIR__)
     * @return string
     */

    public static function get_plugin_url( $path = '' )
    {
        $url = plugins_url( basename(PLUGIN_DIR) ) . $path;

        return apply_filters( "get_{DOMAIN}_plugin_url", $url, $path );
    }

    /**
     * [get_template description]
     * @param  [type]  $template [description]
     * @param  boolean $slug     [description]
     * @param  array   $data     @todo
     * @return string            [description]
     */
    public static function get_template( $template, $slug = false, $data = array() )
    {
        $filename = '';

        if ($slug) $templates[] = PLUGIN_DIR . '/' . $template . '-' . $slug;
        $templates[] = PLUGIN_DIR . '/' . $template;

        foreach ($templates as $template)
        {
            if( ($filename = $template . '.php') && file_exists($filename) ) {
                break;
            }
            elseif( ($filename = $template) && file_exists($filename) ) {
                break;
            }
        }

        return $filename;
    }

    /**
     * [get_admin_template description]
     * @param  string  $tpl     [description]
     * @param  array   $data    [description]
     * @param  boolean $include [description]
     * @return string
     */
    public static function get_admin_template( $tpl = '', $data = array(), $include = false )
    {
        $filename = static::get_template('admin/template/' . $tpl, false, $data);

        if( $data ) extract($data);

        if( $filename && $include ) {
            include $filename;
        }

        return $filename;
    }

    /**
     * Получает параметр из опции плагина
     * @todo Добавить фильтр
     *
     * @param  string  $prop_name Ключ опции плагина или 'all' (вернуть опцию целиком)
     * @param  mixed   $default   Что возвращать, если параметр не найден
     * @return mixed
     */
    public function get( $prop_name, $default = false )
    {
        $option = $this->get_option();
        if( 'all' === $prop_name ) {
            if( is_array($option) && count($option) ) {
                return $option;
            }

            return $default;
        }

        return isset( $option[ $prop_name ] ) ? $option[ $prop_name ] : $default;
    }

    /**
     * Установит параметр в опцию плагина
     * @todo Подумать, может стоит сделать $autoload через фильтр, а не параметр
     *
     * @param mixed  $prop_name Ключ опции плагина || array(параметр => значение)
     * @param string $value     значение (если $prop_name не массив)
     * @param string $autoload  Подгружать опцию автоматически @see update_option()
     * @return bool             Совершились ли обновления @see update_option()
     */
    public function set( $prop_name, $value = '', $autoload = null )
    {
        $option = $this->get_option();
        if( ! is_array($prop_name) ) $prop_name = array($prop_name => $value);

        foreach ($prop_name as $prop_key => $prop_value) {
            $option[ $prop_key ] = $prop_value;
        }

        return update_option( static::get_option_name(), $option, $autoload );
    }

    /**
     * Получает настройку из parent::$options || из кэша || из базы данных
     * @param  mixed  $default Что вернуть если опции не существует
     * @return mixed
     */
    private function get_option( $default = array() )
    {
        if( ! $this->options ) {
            $this->options = get_option( static::get_option_name(), $default );
        }

        return apply_filters( "get_{DOMAIN}_option", $this->options );
    }
}
