<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;
use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

function admin_page() {

		// $plugin = plugin();
		$plugin = null;

		$page = new Page(
			// $plugin->get_option_name(),
			Plugin::get_option_name(),
			__( '1C Exchange', Plugin::DOMAIN ),
			array(
				'parent'      => 'woocommerce', // for ex. woocommerce.
				'menu'        => __( '1C Exchange', Plugin::DOMAIN ),
				'permissions' => 'manage_options',
				'columns'     => 2,
			)
		);

		$page->set_assets(
			function () use ( $plugin ) {
				$files = Parser::getFiles();
				// $files = $plugin->get_exchange_files();

				usort( $files, function ( $a, $b ) {
					return filemtime( $a ) > filemtime( $b );
				} );

				$filenames = array_map( function ( $path ) {
					return basename( $path );
				}, $files );

				// wp_enqueue_style( 'exchange-page', $plugin->get_url( '/admin/assets/exchange-page.css' ) );
				// wp_enqueue_script( 'Timer', $plugin->get_url( '/admin/assets/Timer.js' ) );
				// wp_enqueue_script( 'ExhangeProgress', $plugin->get_url( '/admin/assets/ExhangeProgress.js' ) );
				wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url( '/admin/assets/exchange-page.css' ) );
				wp_enqueue_script( 'Timer', Plugin::get_plugin_url( '/admin/assets/Timer.js' ) );
				wp_enqueue_script( 'ExhangeProgress', Plugin::get_plugin_url( '/admin/assets/ExhangeProgress.js' ) );
				wp_localize_script( 'ExhangeProgress', 'ml2e', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( Plugin::DOMAIN ),
//				'debug_only' => Utils::is_debug(),
					'files'    => $filenames,
				) );

				// wp_enqueue_script( 'exchange-page-js', $plugin->get_url( '/admin/assets/admin.js' ) );
				wp_enqueue_script( 'exchange-page-js', Plugin::get_plugin_url( '/admin/assets/admin.js' ) );
				/**
				 * Upload Script
				 */
				// wp_enqueue_script( 'exchange-upload-ui', $plugin->get_url( '/admin/assets/exchange-upload-ui.js' ) );
				wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url( '/admin/assets/exchange-upload-ui.js' ) );
			}
		);

		$page->set_content(
			function () use ( $plugin ) {
				// include_plugin_file( $plugin->get_template( 'admin/template/menu-page' ) );
				Plugin::get_admin_template( 'menu-page', false, $inc = true );
			}
		);

		$page->add_section(
			new Section(
				'Sumary info',
				__( 'Section', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/section-statistic' )
				Plugin::get_admin_template( 'section-statistic' )
			)
		);

		$page->add_section(
			new Section(
				'postsinfo',
				__( 'Posts', Plugin::DOMAIN ),
				function () {
					get_post_statistic(); // new Parser()
				}
			)
		);

		$page->add_section(
			new Section(
				'termsinfo',
				__( 'Terms', Plugin::DOMAIN ),
				function () {
					get_term_statistic(); // new Parser()
				}
			)
		);

		$page->add_metabox(
			new Metabox(
				'status',
				__( 'Status', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/metabox-status' ),
				Plugin::get_admin_template( 'metabox-status' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-post',
				__( 'Товары', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/metabox-post' ),
				Plugin::get_admin_template( 'metabox-post' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-deactivate',
				__( 'Деактивация', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/metabox-deactivate' ),
				Plugin::get_admin_template( 'metabox-deactivate' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-offer',
				__( 'Предложения', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/metabox-offer' ),
				Plugin::get_admin_template( 'metabox-offer' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-term',
				__( 'Термины (Категории)', Plugin::DOMAIN ),
				// $plugin->get_template( 'admin/template/metabox-term' ),
				Plugin::get_admin_template( 'metabox-term' ),
				$position = 'normal',
				$priority = 'high'
			)
		);

		//		$Page->add_metabox( new Metabox(
		//			'upload-box',
		//			__('Upload New Files', Plugin::DOMAIN),
		//			function() {
		//				Plugin::get_template('admin/template/uploadbox', false, $inc = true);
		//			}
		//		) );
}