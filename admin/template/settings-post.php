<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminForm\Form as Form;

// $terms = get_terms( array(
//     'taxonomy'   => 'product_cat',
//     'hide_empty' => false,
//     'orderby'    => 'id',
//     'order'      => 'DESC', // !@#$ magic?
// ) );

// $termsList = array('К своей (или создать)');
// foreach ($terms as $term) {
//     $termsList[ $term->term_id ] = $term->name;
// }

// $data = array(
//     array(
//         'id'    => 'new_relations',
//         'type'  => 'select',
//         'label' => 'Основаная категория',
//         'options' => $termsList,
//         'desc'  => 'К какой категории привязывать новые товары',
//     ),
// );

$data = array(
    array(
        'id'    => 'post_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => 'post_name',
        'type'  => 'select',
        'options' => array(
            '' => 'Не обновлять код',
            'update' => 'Обновлять',
            'translit' => 'С транслитерацией',
        ),
        'desc' => 'Код (slug) используется для формирования URL'
    ),
    array(
        'id'    => '1',
        'type'  => 'html',
        'value' => '<h4>Обновлять только</h4>'
    ),
    array(
        'id'    => 'post_author',
        'type'  => 'checkbox',
        'label' => 'Автора',
    ),
    array(
        'id'    => 'post_title',
        'type'  => 'checkbox',
        'label' => 'Имя',
    ),
    array(
        'id'    => 'post_content',
        'type'  => 'checkbox',
        'label' => 'Контент',
    ),
    array(
        'id'    => 'post_excerpt',
        'type'  => 'checkbox',
        'label' => 'Цитату',
    ),
    array(
        'id'    => 'post_meta_value',
        'type'  => 'checkbox',
        'label' => 'Свойства',
    ),
    array(
        'id'    => 'post_attribute_value',
        'type'  => 'checkbox',
        'label' => 'Значения аттрибутов',
    ),
);

$form = new Form( $data, array('is_table' => false) );
$form->display();

echo '<div class="clear"></div>';
?>
