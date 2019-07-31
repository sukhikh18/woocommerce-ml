<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Metabox;
use NikolayS93\WPAdminForm\Form as Form;

$Page->add_metabox( new Metabox(
	'settings-deactivate',
	__( 'Деактивация', DOMAIN ),
	function () {
		$data = array(
			array(
				'id'      => 'post_lost',
				'type'    => 'select',
				'desc'    => __( 'What\'s do action if product is not exists in full exchange', DOMAIN ),
				// 'label' => '<h4>If product is lost</h4>',
				'options' => array(
					''           => 'Set unstock status',
					'deactivate' => 'Deactivate on this site',
					// 'delete'       => 'Drop from the database',
				),
			),
			array(
				'id'      => 'price_lost',
				'type'    => 'select',
				'desc'    => __( 'What\'s do action if product is not has price after exchange', DOMAIN ),
				// 'label' => '<h4>If product is lost</h4>',
				'options' => array(
					''        => 'Deactivate on this site',
					'unstock' => 'Set unstock status',
					// 'delete' => 'Drop from the database',
				),
			),
		);

		$form = new Form( $data, array( 'is_table' => false ) );
		$form->display();

		echo '<div class="clear"></div>';
	}
) );

