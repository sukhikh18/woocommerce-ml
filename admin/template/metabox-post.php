<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminForm\Form as Form;
use NikolayS93\WPAdminPage\Metabox;

$Page->add_metabox( new Metabox(
	'settings-post',
	__( 'Товары', DOMAIN ),
	function () {
		$data = array(
			array(
				'id'      => 'post_mode',
				'type'    => 'select',
				'label'   => '',
				'options' => array(
					''       => 'Создавать и обновлять',
					'update' => 'Только обновлять',
					'off'    => 'Не обновлять',
				),
			),
			// array(
			//     'id'    => 'post_name',
			//     'type'  => 'select',
			//     'options' => array(
			//         '' => 'Не обновлять код',
			//         'update' => 'Обновлять',
			//         'translit' => 'С транслитерацией',
			//     ),
			//     'desc' => 'Код (slug) используется для формирования URL'
			// ),
			array(
				'id'    => 'skip_post',
				'type'  => 'html',
				'value' => '<h4>Не обновлять:</h4>'
			),
			array(
				'id'    => 'skip_post_author',
				'type'  => 'checkbox',
				'label' => 'Автора',
			),
			array(
				'id'    => 'skip_post_title',
				'type'  => 'checkbox',
				'label' => 'Имя',
			),
			array(
				'id'    => 'skip_post_content',
				'type'  => 'checkbox',
				'label' => 'Описание',
			),
			array(
				'id'    => 'skip_post_excerpt',
				'type'  => 'checkbox',
				'label' => 'Краткое описание',
			),
			array(
				'id'    => 'skip_post_meta_value',
				'type'  => 'checkbox',
				'label' => 'Свойства',
				'desc'  => 'В том числе возможно ед. изм., артикул, налоговая ставка',
			),
		);

		$form = new Form( $data, array( 'is_table' => false ) );
		$form->display();

		echo '<div class="clear"></div>';
	}
) );

