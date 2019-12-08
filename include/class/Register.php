<?php
/**
 * Register plugin actions
 *
 * @package Newproject.WordPress.plugin
 */

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

/**
 * Class Register
 */
class Register {

	/**
	 * Default warehouse taxonomy slug
	 */
	const WAREHOUSE_SLUG = 'warehouse';

	/**
	 * Get warehouse taxonomy slug
	 *
	 * @return string
	 */
	public static function get_warehouse_taxonomy_slug() {
		return apply_filters( Plugin::PREFIX . 'get_warehouse_taxonomy_slug', static::WAREHOUSE_SLUG );
	}

	/**
	 * Call this method before activate plugin
	 */
	public static function activate() {
		if ( ! is_plugin_active( "woocommerce/woocommerce.php" ) ) {
			add_action( 'admin_notices', function() {

				$plugin_data = get_plugin_data( PLUGIN_DIR . 'index.php' );
				$message = sprintf(__("Plugin <strong>%s</strong> requires plugin <strong>WooCommerce</strong> to be installed and activated.", 'woocommerce-1c'), $plugin_data['Name']);
				printf( '<div class="updated"><p>%s</p></div>', $message );

			} );

			return false;
		}

		static::create_taxonomy_meta_table();

		file_put_contents( get_exchange_dir() . "/.htaccess", "Deny from all" );
		file_put_contents( get_exchange_dir() . "/index.html", '' );
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

	public function post_type__warehouse() {
		$warehouseLabels = array(
			'name'              => __( 'Warehouses', Plugin::DOMAIN ),
			'singular_name'     => __( 'Warehouse', Plugin::DOMAIN ),
			'search_items'      => __( 'Search warehouse', Plugin::DOMAIN ),
			'all_items'         => __( 'All warehouses', Plugin::DOMAIN ),
			'view_item '        => __( 'View warehouse', Plugin::DOMAIN ),
			'parent_item'       => __( 'Parent warehouse', Plugin::DOMAIN ),
			'parent_item_colon' => __( 'Parent warehouse:', Plugin::DOMAIN ),
			'edit_item'         => __( 'Edit warehouse', Plugin::DOMAIN ),
			'update_item'       => __( 'Update warehouse', Plugin::DOMAIN ),
			'add_new_item'      => __( 'Add New warehouse', Plugin::DOMAIN ),
			'new_item_name'     => __( 'New warehouse', Plugin::DOMAIN ),
			'menu_name'         => __( 'Warehouses', Plugin::DOMAIN ),
		);

		register_taxonomy(
			static::WAREHOUSE_SLUG,
			array( 'product' ),
			array(
				'label'       => $warehouseLabels['name'],
				'labels'      => $warehouseLabels,
				'public'      => true,
			)
		);
	}

	/**
	 * Register new admin menu item
	 *
	 * @return Page $Page
	 */
	public function register_plugin_page() {
		$plugin = plugin();

		$page = new Page(
			$plugin->get_option_name(),
			__( '1C Exchange', Plugin::DOMAIN ),
			array(
				'parent'      => 'woocommerce',
				'menu'        => __( '1C Exchange', Plugin::DOMAIN ),
				'permissions' => $plugin->get_permissions(),
				'columns'     => 2,
			)
		);

		$page->set_content(
			function () use ( $plugin ) {
				include_plugin_file( $plugin->get_template( 'admin/template/menu-page' ) );
			}
		);

		$page->add_section(
			new Section(
				'section',
				__( 'Section', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/section' )
			)
		);

		$page->add_metabox(
			new Metabox(
				'status',
				__( 'Status', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/metabox-status' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-post',
				__( 'Товары', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/metabox-post' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-deactivate',
				__( 'Деактивация', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/metabox-deactivate' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-offer',
				__( 'Предложения', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/metabox-offer' ),
				$position = 'side',
				$priority = 'high'
			)
		);

		$page->add_metabox(
			new Metabox(
				'settings-term',
				__( 'Термины (Категории)', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/metabox-term' ),
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

		$page->set_assets(
			function () use ( $plugin ) {
				$files = get_exchange_files();

				usort( $files, function ( $a, $b ) {
					return filemtime( $a ) > filemtime( $b );
				} );

				$filenames = array_map( function ( $path ) {
					return basename( $path );
				}, $files );

				wp_enqueue_style( 'exchange-page', $plugin->get_url( '/admin/assets/exchange-page.css' ) );
				wp_enqueue_script( 'Timer', $plugin->get_url( '/admin/assets/Timer.js' ) );
				wp_enqueue_script( 'ExhangeProgress', $plugin->get_url( '/admin/assets/ExhangeProgress.js' ) );
				wp_localize_script( 'ExhangeProgress', 'ml2e', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( Plugin::DOMAIN ),
					'files'    => $filenames,
				) );
				wp_enqueue_script( 'exchange-page-js', $plugin->get_url( '/admin/assets/admin.js' ) );
				/**
				 * Upload Script
				 */
				wp_enqueue_script( 'exchange-upload-ui', $plugin->get_url( '/admin/assets/exchange-upload-ui.js' ) );
			}
		);

		return $page;
	}

	public function register_exchange_url() {
		add_filter( 'query_vars', function ( $query_vars ) {
			$query_vars[] = Plugin::DOMAIN;

			return $query_vars;
		} );

		add_action( 'init', function () {
			add_rewrite_rule( "exchange", "index.php?" . Plugin::DOMAIN . "=exchange", 'top' );
			add_rewrite_rule( "clean", "index.php?" . Plugin::DOMAIN . "=clean" );

			flush_rewrite_rules();
		}, 1000 );

		add_action( 'template_redirect', function () {
			$value = get_query_var( Plugin::DOMAIN );

			if ( empty( $value ) ) {
				return;
			}

			if ( false !== strpos( $value, '?' ) ) {
				// CGI fix.
				list( $value, $query ) = explode( '?', $value, 2 );
				parse_str( $query, $query );
				$_GET = array_merge( $_GET, $query );
			}

			if ( 'exchange' === $value ) {
				include_plugin_file( 'include/exchange.php' );
			}
			elseif ( 'clean' === $value ) {
				include_plugin_file( 'include/clean.php' );
			}
		}, - 10 );
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
