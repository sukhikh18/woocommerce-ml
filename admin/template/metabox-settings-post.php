<?php

namespace NikolayS93\Exchange;

use NikolayS93\WPAdminPage\Metabox;
use NikolayS93\WPAdminForm\Form as Form;

$Page->add_metabox( new Metabox(
    'settings-post',
    __('Товары', DOMAIN),
    function() {
        $data = array(
            array(
                'id'    => 'post_mode',
                'type'  => 'select',
                'label' => '',
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
            // array(
            //     'id'    => 'skip_post_attribute_value',
            //     'type'  => 'checkbox',
            //     'label' => 'Значения атрибутов',
            //     // 'desc'  => 'Обратите внимание на пункт "Привязка к атрибуту"',
            // ),
            // array(
            //     'id' => 'post_lost',
            //     'type' => 'select',
            //     'label' => '<h4>If product is lost</h4>',
            //     'options' => array(
            //         ''        => 'deactivate on the site',
            //         'drop'    => 'Drop from the database',
            //         'unstock' => 'Set unstock status',
            //     ),
            //     // 'desc' => __('What\'s do action if is product not exists', DOMAIN)
            // ),
        );

        $form = new Form( $data, array('is_table' => false) );
        $form->display();

        echo '<div class="clear"></div>';
    }
) );

