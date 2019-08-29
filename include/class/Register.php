<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

class Register {
	const WAREHOUSE_TAXONOMY = 'warehouse';

	function get_warehouse_labels() {
		$warehouseLabels = array(
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

		return apply_filters( 'getWarehouseLabels', $warehouseLabels );
	}

	function register_warehouses() {
		$warehouseLabels = self::get_warehouse_labels();

		register_taxonomy(
			apply_filters( 'warehouseTaxonomySlug', self::WAREHOUSE_TAXONOMY ),
			array( 'product' ),
			array(
				'label'       => $warehouseLabels['name'],
				'labels'      => $warehouseLabels,
				'description' => '',
				'public'      => true,
			)
		);
	}

	function delete_attribute_taxonomy_meta( $id, $attribute_name, $taxonomy ) {
		global $wpdb;

		$is_deleted = $wpdb->delete(
			$wpdb->prefix . 'woocommerce_attribute_taxonomymeta',
			array( 'tax_id' => $id ),
			array( '%d' )
		);
	}

	function add_product_external_code_field() {
		$mime = explode( '/', get_post_mime_type( get_the_ID() ) );

		$XML = array(
			'type'          => 'text',
			'id'            => 'EXT_ID',
			'label'         => 'Внешний код',
			'wrapper_class' => 'show_if_simple',
			// 'desc_tip'    => 'true',
			// 'description' => 'Разрешить продажи от этого количества',
		);

		if ( $mime[0] == 'XML' && isset( $mime[1] ) ) {
			$XML['value'] = $mime[1];
		}

		woocommerce_wp_text_input( $XML );
		woocommerce_wp_text_input( array(
			'type'          => 'text',
			'id'            => '_unit',
			'label'         => 'Единица измерения',
			'wrapper_class' => 'show_if_simple',
		) );
	}

	function sanitize_product_external_code_field( $post_id ) {
		global $wpdb;

		if ( isset( $_POST['XML_ID'] ) ) {
			$wpdb->update( $wpdb->posts,
				array( 'post_mime_type' => 'XML/' . $_POST['XML_ID'] ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		if ( isset( $_POST['_unit'] ) ) {
			update_post_meta( $post_id, '_unit', sanitize_text_field( $_POST['_unit'] ) );
		}
	}

	/**
	 * Register new admin menu item
	 *
	 * @return $Page NikolayS93\WPAdminPage\Page
	 */
	public function register_plugin_page() {
		/** @var Admin\Page */
		$Page = new Admin\Page(
			Plugin::get_option_name(),
			__( '1C Exchange', Plugin::DOMAIN ),
			array(
				'parent'      => 'woocommerce',
				'menu'        => __( '1C Exchange', Plugin::DOMAIN ),
				'permissions' => 'manage_options',
				'columns'     => 2,
				// 'validate'    => array($this, 'validate_options'),
			)
		);

		$Page->set_assets( function () {
			$files = Parser::getFiles();
			usort( $files, function ( $a, $b ) {
				return filemtime( $a ) > filemtime( $b );
			} );

			$filenames = array_map( function ( $path ) {
				return basename( $path );
			}, $files );

			wp_enqueue_style( 'exchange-page', Plugin::get_plugin_url( '/admin/assets/exchange-page.css' ) );
			wp_enqueue_script( 'Timer', Plugin::get_plugin_url( '/admin/assets/Timer.js' ) );
			wp_enqueue_script( 'ExhangeProgress', Plugin::get_plugin_url( '/admin/assets/ExhangeProgress.js' ) );
			wp_localize_script( 'ExhangeProgress', 'ml2e', array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( Plugin::DOMAIN ),
//				'debug_only' => Utils::is_debug(),
				'files'      => $filenames,
			) );

			wp_enqueue_script( 'exchange-page-js', Plugin::get_plugin_url( '/admin/assets/admin.js' ) );

			/**
			 * Upload Script
			 */
			wp_enqueue_script( 'exchange-upload-ui', Plugin::get_plugin_url( '/admin/assets/exchange-upload-ui.js' ) );
		} );

		$Page->set_content( function () {
			Plugin::get_template( 'admin/template/menu-page', false, $inc = true );
		} );

		include Plugin::get_template( 'admin/template/section-statistic' );

		$Page->add_section( new Section(
			'posts-info',
			__( 'Posts', Plugin::DOMAIN ),
			function () {
				echo get_post_statistic();
			}
		) );

		$Page->add_section( new Section(
			'terms-info',
			__( 'Terms', Plugin::DOMAIN ),
			function () {
				echo get_term_statistic();
			}
		) );

		$Page->add_metabox( new Metabox(
			'status',
			__( 'Status', Plugin::DOMAIN ),
			function () {
				include Plugin::get_template( 'admin/template/metabox-status' );
			}
		) );

		$Page->add_metabox( new Metabox(
			'settings-post',
			__( 'Товары', Plugin::DOMAIN ),
			function () {
				include Plugin::get_template( 'admin/template/metabox-post' );
			}
		) );

		$Page->add_metabox( new Metabox(
			'settings-deactivate',
			__( 'Деактивация', Plugin::DOMAIN ),
			function () {
				include Plugin::get_template( 'admin/template/metabox-deactivate' );
			}
		) );

		$Page->add_metabox( new Metabox(
			'settings-offer',
			__( 'Предложения', Plugin::DOMAIN ),
			function () {
				include Plugin::get_template( 'admin/template/metabox-offer' );
			}
		) );

		$Page->add_metabox( new Metabox(
			'settings-term',
			__( 'Термины (Категории)', Plugin::DOMAIN ),
			function () {
				include Plugin::get_template( 'admin/template/metabox-term' );
			},
			'normal'
		) );

		$Page->add_metabox( new Admin\Metabox(
			'upload-box',
			__('Upload New Files', Plugin::DOMAIN),
			function() {
				Plugin::get_template('admin/template/uploadbox', false, $inc = true);
			}
		) );

		return $Page;
	}
}
