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

    // $Page->add_metabox( new Admin\Metabox(
    //     'uploadbox',
    //     __('Upload New Files', DOMAIN),
    //     function() {
    //         Plugin::get_admin_template('uploadbox', false, $inc = true);
    //     }
    // ) );

    $Page->add_section( new Admin\Section(
        'reportbox',
        __('Report', DOMAIN),
        function() {
            ?>
            <p class="submit" style="margin-top: -65px;"><input type="button" name="get_statistic" id="get_statistic" class="button button-primary right" value="Обновить статистику"></p>
            <!-- <div class="postbox" style="margin-bottom: 0;">
                <h2 class="hndle" style="cursor: pointer;"><span>Статистика</span></h2>
                <div class="inside">
                    -->
                    <div id="statistic_table"> 
                    <?php
                    statisticTable( true );
                    ?>
                    </div><!--

                    <p class="submit"><input type="button" name="get_statistic" id="get_statistic" class="button button-primary right" value="Обновить"></p>
                </div>
            </div> -->
            <?php
        }
    ) );

    $Page->add_section( new Admin\Section(
        'postsinfo',
        __('Posts', DOMAIN),
        function() {
            Plugin::get_admin_template('posts', false, $inc = true);
        }
    ) );

    $Page->add_section( new Admin\Section(
        'termsinfo',
        __('Terms', DOMAIN),
        function() {
            Plugin::get_admin_template('terms', false, $inc = true);
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
        'settings-post',
        __('Товары', DOMAIN),
        function() {
            Plugin::get_admin_template('settings-post', false, true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'settings-offer',
        __('Предложения', DOMAIN),
        function() {
            Plugin::get_admin_template('settings-offer', false, true);
        }
    ) );

    $Page->add_metabox( new Admin\Metabox(
        'settings-term',
        __('Термины (Категории)', DOMAIN),
        function() {
            Plugin::get_admin_template('settings-term', false, true);
        },
        'normal'
    ) );
}