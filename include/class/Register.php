<?php

namespace NikolayS93\PluginName;

use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

class Register {

	/**
	 * Call this method before activate plugin
	 */
	public static function activate() {
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
			__( 'New Plugin name Title', Plugin::DOMAIN ),
			array(
				'parent'      => '', // for ex. woocommerce
				'menu'        => __( 'Example', Plugin::DOMAIN ),
				'permissions' => $Plugin->get_permissions(),
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

		if ( $template = $Plugin->get_template( 'admin/template/metabox' ) ) {
			$Page->add_metabox( new Metabox(
				'metabox',
				__( 'MetaBox', Plugin::DOMAIN ),
				$template,
				$position = 'side',
				$priority = 'high'
			) );
		}

		$Page->set_assets( function () use ($Plugin) {
		} );

		return $Page;
	}
}