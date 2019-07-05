<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminForm\Form as Form;

$data = array(
    array(
        'id'    => 'offer_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => '2',
        'type'  => 'html',
        'value' => '<h4>Обновлять только</h4>'
    ),
    array(
        'id'    => 'offer_price',
        'type'  => 'checkbox',
        'label' => 'Стоимость',
    ),
    array(
        'id'    => 'offer_qty',
        'type'  => 'checkbox',
        'label' => 'Количество',
    ),
    array(
        'id'    => 'offer_unit',
        'type'  => 'checkbox',
        'label' => 'Ед. измерения',
    ),
    array(
        'id'    => 'offer_weight',
        'type'  => 'checkbox',
        'label' => 'Вес',
    ),
);

$form = new Form( $data, array('is_table' => false) );
$form->display();

echo '<div class="clear"></div>';
?>
