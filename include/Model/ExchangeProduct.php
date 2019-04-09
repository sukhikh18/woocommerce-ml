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

    public $warehouse = array();

    /**
     * Single term. Link to developer (prev. created)
     * @var String
     */
    public $developer = array();

    function getAllRelativeExternals( $orphaned_only = false )
    {
        $arExternals = array();
        $arRelationships = array_merge($this->product_cat, $this->warehouse, $this->developer, $this->properties);

        foreach ($arRelationships as $arRelationship)
        {
            if( $orphaned_only && $arRelationship->get_id() ) {
                continue;
            }

            $arExternals[] = $arRelationship->getExternal();
        }

        return $arExternals;
    }

    function fillRelatives()
    {
        /** @global wpdb $wpdb built in wordpress db object */
        global $wpdb;

        $arExternals = $this->getAllRelativeExternals();
        foreach ($arExternals as $strExternal)
        {
            $arSqlExternals[] = "`meta_value` = '{$strExternal}'";
        }

        $ardbTerms = array();
        if( !empty($arSqlExternals) ) {
            $exsists_terms_query = "
                SELECT term_id, meta_key, meta_value
                FROM $wpdb->termmeta
                WHERE meta_key = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", array_unique($arSqlExternals)) . ")";

            $ardbTerms = $wpdb->get_results( $exsists_terms_query );

            $arTerms = array();
            foreach ($ardbTerms as $ardbTerm) {
                $arTerms[ $ardbTerm->meta_value ] = $ardbTerm->term_id;
            }
        }

        foreach (array( $this->product_cat, $this->warehouse, $this->developer, $this->properties ) as &$arVariable)
        {
            foreach ($arVariable as &$variable)
            {
                if( !empty( $arTerms[ $variable->getExternal() ] ) ) $variable->set_id( $arTerms[ $variable->getExternal() ] );
            }
        }
    }

    function updateAttributes()
    {
        /**
         * Set attribute properties
         */
        $arAttributes = array();
        foreach ($this->properties as $property)
        {
            list($taxonomy) = explode('/', $property->getExternal());
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
            if( $obRelationship->get_id() ) {
                list($taxonomy) = explode('/', $obRelationship->getExternal());

                if( empty($arRelationshipIds[ $taxonomy ]) ) {
                    $arRelationshipIds[ $taxonomy ] = array();
                }

                $arRelationshipIds[ $taxonomy ][] = $obRelationship->get_id();
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
