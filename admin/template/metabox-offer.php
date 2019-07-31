<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Metabox;
use NikolayS93\WPAdminForm\Form as Form;

$Page->add_metabox( new Metabox(
	'settings-offer',
	__( 'Предложения', DOMAIN ),
	function () {
		$data = array(
			// array(
			//     'id'    => 'offer_mode',
			//     'type'  => 'select',
			//     'label' => '',
			//     'options' => array(
			//         ''       => 'Добавлять/Обновлять',
			//         'update' => 'Только обновлять',
			//         'off'    => 'Не трогать',
			//     ),
			// ),
			// array(
			//     'id'    => 'skip_offer',
			//     'type'  => 'html',
			//     'value' => '<h4>Не обновлять:</h4>'
			// ),
			array(
				'id'      => 'offer_price',
				'type'    => 'select',
				'label'   => 'Cтоимость',
				'options' => array(
					''    => 'Выгружать',
					'off' => 'Не выгружать',
					// 'update' => 'Только новым',
				),
			),
			array(
				'id'      => 'offer_qty',
				'type'    => 'select',
				'label'   => 'Количество',
				'options' => array(
					''    => 'Выгружать',
					'off' => 'Не выгружать',
					// 'update' => 'Только новым',
				),
			),
			// array(
			//     'id'    => 'offer_unit',
			//     'type'  => 'select',
			//     'label' => 'Ед. измерения',
			//     'options' => array(
			//         ''       => 'Выгружать',
			//         'update' => 'Только новым',
			//         'off'    => 'Не выгружать',
			//     ),
			// ),
			// array(
			//     'id'    => 'offer_weight',
			//     'type'  => 'select',
			//     'label' => 'Вес',
			//     'options' => array(
			//         ''       => 'Выгружать',
			//         // 'update' => 'Только новым',
			//         'off'    => 'Не выгружать',
			//     ),
			// ),
		);

		$form = new Form( $data, array( 'is_table' => true ) );
		$form->display();

		echo '<div class="clear"></div>';
	}
) );
