<?php
/**
 * Register plugin actions
 *
 * @package woocommerce-ml
 */

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage as Admin;

/**
 * Class Register
 */
class Register {
	const EXCHANGE_QUERY_VAR = 'woocommerce-ml';
	/**
	 * Default warehouse taxonomy slug
	 */
	const WAREHOUSE_SLUG = 'Warehouse';

	const ATTRIBUTE_TAXONOMYMETA_TABLE = 'woocommerce_attribute_taxonomymeta';

	public static function install() {
		require_once PLUGIN_DIR . '/.install.php';
	}

	public static function uninstall() {
		delete_option( Plugin::get_option_name() );
	}

	/**
	 * Get warehouse taxonomy slug
	 *
	 * @return string
	 */
	public static function get_warehouse_taxonomy_slug() {
		return apply_filters( 'warehouseTaxonomySlug', self::WAREHOUSE_SLUG );
	}

	public static function register_taxonomy__warehouse() {
		$warehouse_labels = array(
			'name'              => 'Склады',
			'singular_name'     => 'Склад',
			'search_items'      => 'Search склад',
			'all_items'         => 'All склад',
			'view_item '        => 'View склад',
			'parent_item'       => 'Parent склад',
			'parent_item_colon' => 'Parent склад:',
			'edit_item'         => 'Edit склад',
			'update_item'       => 'Update склад',
			'add_new_item'      => 'Add New склад',
			'new_item_name'     => 'New склад Name',
			'menu_name'         => 'Склады',
		);

		register_taxonomy(
			static::get_warehouse_taxonomy_slug(),
			array( 'product' ),
			array(
				'label'  => $warehouse_labels['name'],
				'labels' => $warehouse_labels,
				'public' => true,
			)
		);
	}

	/**
	 * Register new admin menu item
	 *
	 * @return Page $Page
	 */
	public static function plugin_page() {
		/** @var Admin\Page */
		$Page = new Admin\Page( Plugin::get_option_name(), __( '1C Exchange', DOMAIN ), array(
			'parent'      => 'woocommerce',
			'menu'        => __( '1C Exchange', DOMAIN ),
			'permissions' => 'manage_options',
			'columns'     => 2,
			// 'validate'    => array($this, 'validate_options'),
		) );

		$Page->set_assets( function () {
			$files = Parser::get_files();
			usort( $files, function ( $a, $b ) {
				return filemtime( $a ) > filemtime( $b );
			} );

			$filenames = array_map( function ( $path ) {
				return basename( $path );
			}, $files );

			wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url( '/admin/assets/exchange-page.css' ) );
			wp_enqueue_script( 'Timer', Plugin::get_plugin_url( '/admin/assets/timer.js' ) );
			wp_enqueue_script( 'exchange-progress', Plugin::get_plugin_url( '/admin/assets/exchange-progress.js' ) );
			wp_localize_script( 'exchange-progress', 'ml2e', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( DOMAIN ),
				'files'    => $filenames,
			) );

			wp_enqueue_script( 'exchange-page-js', Plugin::get_plugin_url( '/admin/assets/admin.js' ) );

			/**
			 * Upload Script
			 */
			wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url( '/admin/assets/exchange-upload-ui.js' ) );
		} );

		$Page->set_content( function () {
			Plugin::get_admin_template( 'menu-page', false, $inc = true );
		} );

		include Plugin::get_admin_template( 'section-statistic' );

		$Page->add_section( new Admin\Section(
			'posts_info',
			__( 'Posts', DOMAIN ),
			function () {
				echo get_post_statistic();
			}
		) );

		$Page->add_section( new Admin\Section(
			'terms_info',
			__( 'Terms', DOMAIN ),
			function () {
				echo get_term_statistic();
			}
		) );

		include Plugin::get_admin_template( 'metabox-status' );
		include Plugin::get_admin_template( 'metabox-post' );
		include Plugin::get_admin_template( 'metabox-deactivate' );
		include Plugin::get_admin_template( 'metabox-offer' );
		include Plugin::get_admin_template( 'metabox-term' );
	}

	public static function log() {
		Plugin::session_start();

		Plugin::write_log( 'get', $_GET );
		Plugin::write_log( 'post', $_POST );
		Plugin::write_log( 'cookie', $_COOKIE );
		Plugin::write_log( 'session', $_SESSION, array(
			'session_name=' . session_name(),
			'session_id=' . session_id(),
		) );
	}

	public static function query_vars( $query_vars ) {
		array_push( $query_vars, static::EXCHANGE_QUERY_VAR );
		return $query_vars;
	}

	public static function rewrite_rule() {
		add_rewrite_rule( IMPORT_ACTION, 'index.php?' . static::EXCHANGE_QUERY_VAR . '=' . IMPORT_ACTION, 'top' );
		flush_rewrite_rules();
	}

	public static function template_redirect() {
		$value = get_query_var( static::EXCHANGE_QUERY_VAR );
		if ( empty( $value ) ) {
			return;
		}

		if ( IMPORT_ACTION === $value ) {
			do_action( IMPORT_ACTION );
		}
	}

	public static function ajax_query() {
		if ( ! wp_verify_nonce( $_REQUEST['exchange_nonce'], DOMAIN ) ) {
			_e( 'Error! security rules violated.' ); // 'Ошибка! нарушены правила безопасности'
			wp_die();
		}

		do_action( IMPORT_ACTION );
		wp_die();
	}

	public static function attribute_taxonomymeta_delete( $id, $attribute_name, $taxonomy ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . self::ATTRIBUTE_TAXONOMYMETA_TABLE,
			array( 'tax_id' => $id ),
			array( '%d' )
		);
	}
}