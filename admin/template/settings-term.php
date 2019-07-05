<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminForm\Form as Form;

$attrs = array(
    array(
        'id'    => 'attribute_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => 'pa_name',
        'type'  => 'select',
        'options' => array(
            '' => 'Не обновлять код',
            'update' => 'Обновлять',
            'translit' => 'С транслитерацией',
        ),
    ),
    array(
        'id'    => 'pa_title',
        'type'  => 'checkbox',
        'label' => 'Имя',
    ),
);

$cats = array(
    array(
        'id'    => 'category_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => 'cat_name',
        'type'  => 'select',
        'options' => array(
            '' => 'Не обновлять код',
            'update' => 'Обновлять',
            'translit' => 'С транслитерацией',
        ),
    ),
    array(
        'id'    => 'cat_title',
        'type'  => 'checkbox',
        'label' => 'Имя',
    ),
);

$devs = array(
    array(
        'id'    => 'developer_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => 'dev_name',
        'type'  => 'select',
        'options' => array(
            '' => 'Не обновлять код',
            'update' => 'Обновлять',
            'translit' => 'С транслитерацией',
        ),
    ),
    array(
        'id'    => 'dev_title',
        'type'  => 'checkbox',
        'label' => 'Имя',
    ),
);

$whs = array(
    array(
        'id'    => 'warehouse_mode',
        'type'  => 'select',
        'label' => '',
        'options' => array(
            ''       => 'Добавлять/Обновлять',
            'update' => 'Только обновлять',
            'off'    => 'Не трогать',
        ),
    ),
    array(
        'id'    => 'wh_name',
        'type'  => 'select',
        'options' => array(
            '' => 'Не обновлять код',
            'update' => 'Обновлять',
            'translit' => 'С транслитерацией',
        ),
    ),
    array(
        'id'    => 'wh_title',
        'type'  => 'checkbox',
        'label' => 'Имя',
    ),
);

$adv = array(
    array(
        'id' => 'post_relationship',
        'type' => 'select',
        'label' => 'Привязка к категории',
        'options' => array(
            ''        => 'Create new category',
            'default' => 'Put to default category',
            'disable' => 'Do not update relative',
        ),
        'desc' => __('What\'s do action if is category not exists', DOMAIN)
    ),
    array(
        'id' => 'post_attribute',
        'type' => 'select',
        'label' => 'Привязка к аттрибуту',
        'options' => array(
            ''        => 'Create new attribute',
            'string'  => 'Set string (not taxonomy)',
        ),
        'desc' => __('What\'s do action if is attribute not exists', DOMAIN)
    )
);

?>
<div class="row" style="display: flex;flex-wrap: wrap;">
    <div class="col inside">
        <h3>Атрибуты</h3>

        <?php
        $form = new Form( $attrs, array('is_table' => false) );
        $form->display();
        ?>
    </div>
    <div class="col inside">
        <h3>Категории</h3>

        <?php
        $form = new Form( $cats, array('is_table' => false) );
        $form->display();
        ?>
    </div>
    <div class="col inside">
        <h3>Производители</h3>

        <?php
        $form = new Form( $devs, array('is_table' => false) );
        $form->display();
        ?>
    </div>
    <div class="col inside">
        <h3>Склады</h3>

        <?php
        $form = new Form( $whs, array('is_table' => false) );
        $form->display();
        ?>
    </div>
    <div class="col inside">
        <h3>Дополнительно</h3>

        <?php
        $form = new Form( $adv, array('is_table' => true) );
        $form->display();
        ?>
    </div>
</div>


<?php
submit_button( 'Сохранить', 'primary right', 'save_changes' );
echo '<div class="clear"></div>';
?>
