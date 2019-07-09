<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

function admin_page() {

    /** @var Admin\Page */
    $Page = new Admin\Page( Plugin::get_option_name(), __('1C Exchange', DOMAIN), array(
        'parent'      => 'woocommerce',
        'menu'        => __('1C Exchange', DOMAIN),
        // 'validate'    => array($this, 'validate_options'),
        'permissions' => 'manage_options',
        'columns'     => 2,
    ) );

    $Page->set_assets( function() {
        $files = Parser::getFiles();
        usort($files, function($a, $b) {
            return filemtime($a) > filemtime($b);
        });

        $filenames = array_map(function($path) {
            return basename($path);
        }, $files);

        wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url('/admin/assets/exchange-page.css') );
        wp_enqueue_script( 'Timer', Plugin::get_plugin_url('/admin/assets/Timer.js') );
        wp_enqueue_script( 'ExhangeProgress', Plugin::get_plugin_url('/admin/assets/ExhangeProgress.js') );
        wp_localize_script('ExhangeProgress', 'ml2e', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( DOMAIN ),
            'debug_only' => Utils::is_debug(),
            'files' => $filenames,
        ) );

        wp_enqueue_script( 'exchange-page-js', Plugin::get_plugin_url('/admin/assets/admin.js') );

        /**
         * Upload Script
         */
        wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url('/admin/assets/exchange-upload-ui.js') );
    } );

    $Page->set_content( function() {
        Plugin::get_admin_template('menu-page', false, $inc = true);
    } );

    include Plugin::get_admin_template('section-statistic');
    include Plugin::get_admin_template('section-posts');
    include Plugin::get_admin_template('section-terms');
    include Plugin::get_admin_template('metabox-status');
    include Plugin::get_admin_template('metabox-settings-post');
    include Plugin::get_admin_template('metabox-settings-offer');
    include Plugin::get_admin_template('metabox-settings-term');
    // include Plugin::get_admin_template('metabox-upload');
}