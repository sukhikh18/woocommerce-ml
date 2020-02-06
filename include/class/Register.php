<?php
/**
 * Register plugin actions
 *
 * @package Woocommerce.1c.Exchanger
 */

namespace NikolayS93\Exchanger;

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
	const WAREHOUSE_SLUG = 'Warehouse';

	/**
	 * Get warehouse taxonomy slug
	 *
	 * @return string
	 */
	public static function get_warehouse_taxonomy_slug() {
		return self::WAREHOUSE_SLUG;
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
				'parent'      => 'woocommerce', // for ex. woocommerce.
				'menu'        => __( '1C Exchange', Plugin::DOMAIN ),
				'permissions' => 'manage_options',
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
				'Sumary info',
				__( 'Section', Plugin::DOMAIN ),
				$plugin->get_template( 'admin/template/section-statistic' )
			)
		);

		$page->add_section(
			new Section(
				'postsinfo',
				__( 'Posts', Plugin::DOMAIN ),
				function () {
					get_post_statistic( new Parser() );
				}
			)
		);

		$page->add_section(
			new Section(
				'termsinfo',
				__( 'Terms', Plugin::DOMAIN ),
				function () {
					get_term_statistic( new Parser() );
				}
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
				$files = $plugin->get_exchange_files();

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
//				'debug_only' => Utils::is_debug(),
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
				list( $value, $query ) = explode( '?', $value, 2 );
				parse_str( $query, $query );
				$_GET = array_merge( $_GET, $query );
			}

			if ( $value == 'exchange' ) {
				$REST = new REST_Controller();
				$REST->exchange();
			}
			// elseif ($value == 'clean') {
			//     // require_once PLUGIN_DIR . "/include/clean.php";
			//     exit;
			// }
		}, - 10 );
	}

	static function get_exchange_table_name() {
		global $wpdb;

		return $wpdb->get_blog_prefix() . EXCHANGE_TMP_TABLENAME;
	}
}
