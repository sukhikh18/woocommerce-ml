<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
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

        /**
         * @var Relationship $property
         */
        foreach ($this->properties as $property)
        {

            $taxonomy = $property->getTaxonomy();

            if( $external = $property->getExternal() ) {
                $arAttributes[ $taxonomy ] = array(
                    'name'         => $taxonomy,
                    'value'        => '', // $property->getValue()
                    'position'     => 1,
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 1,
                );
            }
            else {
                $arAttributes[ $taxonomy ] = array(
                    'name'         => $taxonomy,
                    'value'        => $property->getValue(),
                    'position'     => 1,
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 0,
                );
            }
        }

        update_post_meta($this->get_id(), '_product_attributes', $arAttributes);
    }

    private function updateObjectTerm($product_id, $values, $taxonomy, $append = true )
    {
        $result = wp_set_object_terms( $product_id, $values, $taxonomy, $append );

        if( is_wp_error($result) ) {
            Utils::addLog( $result, array(
                'product_id' => $product_id,
                'taxonomy'   => $taxonomy,
                'values'     => $values,
            ) );
        }
        else {
            return sizeof( $result );
        }

        return 0;
    }

    /**
     * @note Do not merge data for KISS
     */
    function updateObjectTerms()
    {
        $count = 0;
        $product_id = $this->get_id();
        if( empty($product_id) ) return $count;

        /**
         * Update product's cats
         */
        $terms = array();
        foreach ($this->product_cat as $Relationship)
        {
            if( $term_id = $Relationship->getValue() ) $terms[] = $term_id;
        }

        $count += $this->updateObjectTerm($product_id, $terms, 'product_cat'); // , 0 < $count

        /**
         * Update product's war-s
         */
        $terms = array();
        foreach ($this->warehouse as $Relationship)
        {
            if( $term_id = $Relationship->getValue() ) $terms[] = $term_id;
        }

        $count += $this->updateObjectTerm($product_id, $terms, apply_filters( 'warehouseTaxonomySlug', \NikolayS93\Exchange\DEFAULT_WAREHOUSE_TAX_SLUG ));

        /**
         * Update product's developers
         */
        $terms = array();
        foreach ($this->developer as $Relationship)
        {
            if( $term_id = $Relationship->getValue() ) $terms[] = $term_id;
        }

        $count += $this->updateObjectTerm($product_id, $terms, apply_filters( 'developerTaxonomySlug', \NikolayS93\Exchange\DEFAULT_DEVELOPER_TAX_SLUG ));

        /**
         * Update product's properties
         */
        $taxs = array();
        foreach ($this->properties as $Relationship)
        {
            if( $tax = $Relationship->getTaxonomy() ) {
                if( !isset( $taxs[ $tax ] ) ) $taxs[ $tax ] = array();
                if( $term_id = $Relationship->getValue() ) $taxs[ $tax ][] = $term_id;
            }
        }

        foreach ($taxs as $tax => $terms)
        {
            $count += $this->updateObjectTerm($product_id, $terms, $tax);
        }

        return $count;
    }
}
