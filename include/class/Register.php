<?php

namespace NikolayS93\PluginName;

use NikolayS93\WPAdminPage\Page;
use NikolayS93\WPAdminPage\Section;
use NikolayS93\WPAdminPage\Metabox;

class Register {
	/**
	 * @var Plugin
	 */
	private $Plugin;

	function __construct( Plugin $Plugin ) {
		$this->Plugin = $Plugin;
	}

	/**
	 * Register new admin menu item
	 *
	 * @return Page $Page
	 */
	public function register_plugin_page() {

		$Page = new Page(
			$this->Plugin->get_option_name(),
			__( 'New Plugin name Title', $this->Plugin::DOMAIN ),
			array(
				'parent'      => '', // for ex. woocommerce
				'menu'        => __( 'Example', $this->Plugin::DOMAIN ),
				'permissions' => $this->Plugin->get_permissions(),
				'columns'     => 2,
				// 'validate'    => array($this, 'validate_options'),
			)
		);

		$Page->set_content( function () {
			if ( $template = $this->Plugin->get_template( 'admin/template/menu-page' ) ) {
				include $template;
			}
		} );

		if ( $template = $this->Plugin->get_template( 'admin/template/section' ) ) {
			$Page->add_section( new Section(
				'section',
				__( 'Section', $this->Plugin::DOMAIN ),
				$template
			) );
		}

		if ( $template = $this->Plugin->get_template( 'admin/template/metabox' ) ) {
			$Page->add_metabox( new Metabox(
				'metabox',
				__( 'MetaBox', $this->Plugin::DOMAIN ),
				$template,
				$position = 'side',
				$priority = 'high'
			) );
		}

		$Page->set_assets( function () {
		} );

		return $Page;
	}
}