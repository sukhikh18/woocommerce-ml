<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

class Register {

	/**
	 * Call this method before activate plugin
	 */
	public static function activate() {
		self::set_mime_type_indexes();
		self::create_taxonomy_meta_table();
	}

	/**
	 * Call this method before disable plugin
	 */
	public static function deactivate() {
	}

	/**
	 * Call this method before delete plugin
	 */
	public static function uninstall() {
	}

	/**
	 * Register new admin menu item
	 *
	 * @return Page $Page
	 */
	public function register_plugin_page() {
		$Plugin = Plugin();

		$Page = new Page(
			$Plugin->get_option_name(),
			__( '1C Exchange', Plugin::DOMAIN ),
			array(
				'parent'      => 'woocommerce',
				'menu'        => __( '1C Exchange', Plugin::DOMAIN ),
				'permissions' => 'manage_options',
				'columns'     => 2,
				// 'validate'    => array($this, 'validate_options'),
			)
		);

		$Page->set_content( function () use ($Plugin) {
			if ( $template = $Plugin->get_template( 'admin/template/menu-page' ) ) {
				include $template;
			}
		} );

		if ( $template = $Plugin->get_template( 'admin/template/section' ) ) {
			$Page->add_section( new Section(
				'section',
				__( 'Section', Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $Plugin->get_template( 'admin/template/metabox-status' ) ) {
			$Page->add_metabox( new Metabox(
				'status',
				__( 'Status', Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $Plugin->get_template( 'admin/template/metabox-post' ) ) {
			$Page->add_metabox( new Metabox(
				'settings-post',
				__( 'Товары', Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $Plugin->get_template( 'admin/template/metabox-deactivate' ) ) {
			$Page->add_metabox( new Metabox(
				'settings-deactivate',
				__( 'Деактивация', Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $Plugin->get_template( 'admin/template/metabox-offer' ) ) {
			$Page->add_metabox( new Metabox(
				'settings-offer',
				__( 'Предложения', Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $Plugin->get_template( 'admin/template/metabox-term' ) ) {
			$Page->add_metabox( new Metabox(
				'settings-term',
				__( 'Термины (Категории)', Plugin::DOMAIN ),
				$template,
				'normal'
			) );
		}

//		$Page->add_metabox( new Metabox(
//			'upload-box',
//			__('Upload New Files', Plugin::DOMAIN),
//			function() {
//				Plugin::get_template('admin/template/uploadbox', false, $inc = true);
//			}
//		) );

		$Page->set_assets( function () use ($Plugin) {
			$files = $Plugin->get_exchange_files();

			usort( $files, function ( $a, $b ) {
				return filemtime( $a ) > filemtime( $b );
			} );

			$filenames = array_map( function ( $path ) {
				return basename( $path );
			}, $files );

			wp_enqueue_style( 'exchange-page', $Plugin->get_url( '/admin/assets/exchange-page.css' ) );
			wp_enqueue_script( 'Timer', $Plugin->get_url( '/admin/assets/Timer.js' ) );
			wp_enqueue_script( 'ExhangeProgress', $Plugin->get_url( '/admin/assets/ExhangeProgress.js' ) );
			wp_localize_script( 'ExhangeProgress', 'ml2e', array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Plugin::DOMAIN ),
//				'debug_only' => Utils::is_debug(),
				'files'      => $filenames,
			) );
			wp_enqueue_script( 'exchange-page-js', $Plugin->get_url( '/admin/assets/admin.js' ) );
			/**
			 * Upload Script
			 */
			wp_enqueue_script( 'exchange-upload-ui', $Plugin->get_url( '/admin/assets/exchange-upload-ui.js' ) );
		} );

		return $Page;
	}

	private static function set_mime_type_indexes() {
		global $wpdb;

		/**
		 * Maybe insert posts mime_type INDEX if is not exists
		 */
		$postMimeIndexName = 'id_post_mime_type';
		$result            = $wpdb->get_var( "SHOW INDEX FROM $wpdb->posts WHERE Key_name = '$postMimeIndexName';" );
		if ( ! $result ) {
			return $wpdb->query( "ALTER TABLE $wpdb->posts
				ADD INDEX $postMimeIndexName (ID, post_mime_type(78))" );
		}

		return false;
	}

	private static function create_taxonomy_meta_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * Maybe create taxonomy meta table
		 */
		$taxonomymeta = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$taxonomymeta'" ) != $taxonomymeta ) {
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
}