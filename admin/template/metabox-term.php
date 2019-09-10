<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminForm\Form as Form;

$cats = array(
	array(
		'id'      => 'category_mode',
		'type'    => 'select',
		'label'   => '',
		'options' => array(
			''       => 'Создавать и обновлять',
			'create' => 'Выгружать новые',
			'update' => 'Обновлять только',
			'off'    => 'Не выгружать',
		),
	),
	array(
		'id'    => 'cat_name',
		'type'  => 'checkbox',
		'label' => 'Название',
	),
	array(
		'id'    => 'cat_desc',
		'type'  => 'checkbox',
		'label' => 'Описание',
	),
	array(
		'id'    => 'skip_parent',
		'type'  => 'checkbox',
		'label' => 'Игнорировать иерархию',
	),
	// array(
	//     'id'    => 'cat_name',
	//     'type'  => 'select',
	//     'options' => array(
	//         '' => 'Не обновлять код',
	//         'update' => 'Обновлять',
	//         'translit' => 'С транслитерацией',
	//     ),
	// ),
);

$attrs = array(
	array(
		'id'      => 'attribute_mode',
		'type'    => 'select',
		'label'   => '',
		'options' => array(
			''       => 'Создавать и обновлять',
			'create' => 'Выгружать новые',
			'update' => 'Обновлять только',
			'off'    => 'Не выгружать',
		),
	),
	array(
		'id'    => 'pa_name',
		'type'  => 'checkbox',
		'label' => 'Название',
	),
	array(
		'id'    => 'pa_desc',
		'type'  => 'checkbox',
		'label' => 'Описание',
	),
	// array(
	//     'id'    => 'pa_name',
	//     'type'  => 'select',
	//     'options' => array(
	//         '' => 'Не обновлять код',
	//         'update' => 'Обновлять',
	//         'translit' => 'С транслитерацией',
	//     ),
	// ),
);

// $devs = array(
//     array(
//         'id'    => 'developer_mode',
//         'type'  => 'select',
//         'label' => '',
//         'options' => array(
//             ''       => 'Создавать и обновлять',
//             'create' => 'Выгружать новые',
//             'update' => 'Обновлять только',
//             'off'    => 'Не выгружать',
//         ),
//     ),
//     array(
//         'id'    => 'dev_name',
//         'type'  => 'checkbox',
//         'label' => 'Название',
//     ),
//     array(
//         'id'    => 'dev_desc',
//         'type'  => 'checkbox',
//         'label' => 'Описание',
//     ),
//     // array(
//     //     'id'    => 'dev_name',
//     //     'type'  => 'select',
//     //     'options' => array(
//     //         '' => 'Не обновлять код',
//     //         'update' => 'Обновлять',
//     //         'translit' => 'С транслитерацией',
//     //     ),
//     // ),
// );

$whs = array(
	array(
		'id'      => 'warehouse_mode',
		'type'    => 'select',
		'label'   => '',
		'options' => array(
			''       => 'Создавать и обновлять',
			'create' => 'Выгружать новые',
			'update' => 'Обновлять только',
			'off'    => 'Не выгружать',
		),
	),
	array(
		'id'    => 'wh_name',
		'type'  => 'checkbox',
		'label' => 'Название',
	),
	array(
		'id'    => 'wh_desc',
		'type'  => 'checkbox',
		'label' => 'Описание',
	),
	// array(
	//     'id'    => 'wh_name',
	//     'type'  => 'select',
	//     'options' => array(
	//         '' => 'Не обновлять код',
	//         'update' => 'Обновлять',
	//         'translit' => 'С транслитерацией',
	//     ),
	// ),
);

$adv = array(
	array(
		'id'      => 'post_relationship',
		'type'    => 'select',
		'label'   => 'Привязка к категории',
		'desc'    => __( 'What\'s do action if is category not exists', Plugin::DOMAIN ),
		'options' => array(
			''        => 'Try set relative category',
			'default' => 'Put to default category',
			'off'     => 'Do not update relative',
			// 'create'  => 'Create new category',
		),
	),
	array(
		'id'      => 'post_attribute',
		'type'    => 'select',
		'label'   => 'Привязка к аттрибуту',
		'desc'    => __( 'What\'s do action if is attribute not exists', Plugin::DOMAIN ),
		'options' => array(
			''     => 'Skip',
			'text' => 'Set string (not taxonomy)',
			'off'  => 'Do not exchange attr. value'
			// 'create' => 'Create new attribute',
		),
	)
);

?>
    <div class="row" style="display: flex;flex-wrap: wrap;justify-content: space-around;">
        <div class="col inside">
            <h3>Категории</h3>

			<?php
			$form = new Form( $cats, array( 'is_table' => false ) );
			$form->display();
			?>
        </div>
        <div class="col inside">
            <h3>Атрибуты</h3>

			<?php
			$form = new Form( $attrs, array( 'is_table' => false ) );
			$form->display();
			?>
        </div>
        <!-- <div class="col inside">
                <h3>Производители</h3>

                <?php
		// $form = new Form( $devs, array('is_table' => false) );
		// $form->display();
		?>
            </div> -->
        <div class="col inside">
            <h3>Склады</h3>

			<?php
			$form = new Form( $whs, array( 'is_table' => false ) );
			$form->display();
			?>
        </div>
        <div class="col inside">
            <h3>Дополнительно</h3>

			<?php
			$form = new Form( $adv, array( 'is_table' => true ) );
			$form->display();
			?>
        </div>
    </div>
	<?php

    submit_button( 'Сохранить', 'primary right', 'save_changes' );
    echo '<div class="clear"></div>';
