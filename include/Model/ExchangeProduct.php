<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\ExchangeTerm;
use NikolayS93\Exchange\Model\ExchangeTaxonomy;

class ExchangeProduct extends ExchangePost
{
    /**
     * "Product_cat" type wordpress terms
     * @var Array
     */
    public $product_cat = array();

    /**
     * Product properties with link by term (has taxonomy/term)
     * @var Array
     */
    public $properties = array();

    /**
     * Single term. Link to developer (prev. created)
     * @var String
     */
    public $developer = array();

    function updateAttributes()
    {
        /**
         * Set attribute properties
         */
        $arAttributes = array();
        foreach ($this->properties as $property)
        {
            $taxonomy = $property->getTaxonomy();
            // list($taxonomy) = explode('/', $property->getExternal());
            $arAttributes[ $taxonomy ] = array(
                'name'         => $taxonomy,
                'value'        => '',
                'position'     => 1,
                'is_visible'   => 1,
                'is_variation' => 0,
                'is_taxonomy'  => 1
            );
        }

        update_post_meta($this->get_id(), '_product_attributes', $arAttributes);
    }

    function updateObjectTerms()
    {
        $product_id = $this->get_id();
        if( empty($product_id) ) return;

        $arRelationshipIds = array();
        foreach (array_merge($this->product_cat, $this->warehouse, $this->developer, $this->properties) as $obRelationship)
        {
            if( $relID = $obRelationship->get_id() ) {
                $taxonomy = $obRelationship->getTaxonomy();

                if( empty($arRelationshipIds[ $taxonomy ]) ) {
                    $arRelationshipIds[ $taxonomy ] = array();
                }

                $arRelationshipIds[ $taxonomy ][] = $relID;
            }
        }

        foreach ($arRelationshipIds as $taxonomy => $values)
        {
            if( !empty($taxonomy) && !empty($values) ) {
                /**
                 * Добавляем терминов товару
                 * $append = true - иначе, рискуем удалить связи с акциями,
                 * новинками и т.д. как правило не созданные в 1с
                 */
                wp_set_object_terms( $product_id, $values, $taxonomy, $append = true );
            }
        }
    }
}
