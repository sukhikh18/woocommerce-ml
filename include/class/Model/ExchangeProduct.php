<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Parser;
use NikolayS93\Exchange\Plugin;
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

        if( 'off' === ($post_attribute = Plugin::get('post_attribute')) ) return;

        /**
         * @var $property Relationship
         */
        foreach ($this->properties as $property)
        {
            $taxonomy = $property->getTaxonomy();
            $is_visible = 1;

            if( $property->getExternal() && !$property->getValue() ) {
                if( 'text' != $post_attribute ) continue;

                $is_visible = 0;

                if( empty($allProperties) ) {
                    $Parser = Parser::getInstance();
                    $allProperties = $Parser->getProperties();
                }

                foreach ($allProperties as $_property)
                {
                    if( $_property->getSlug() == $taxonomy && ($_terms = $_property->getTerms()) ) {
                        if( isset($_terms[ $property->getExternal() ]) ) {
                            $_term = $_terms[ $property->getExternal() ];

                            if( $_term instanceof ExchangeTerm  ) {
                                $taxonomy = $_property->getName();
                                $property->setValue( $_term->get_name() );
                                break;
                            }
                        }
                    }
                }
            }

            if(
                ($external = $property->getExternal()) &&
                $property->getValue() &&
                term_exists( (int) $property->getValue(), $taxonomy )
            ) {
                $arAttributes[ $taxonomy ] = array(
                    'name'         => $taxonomy,
                    'value'        => '',
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
                    'is_visible'   => $is_visible,
                    'is_variation' => 0,
                    'is_taxonomy'  => 0,
                );
            }
        }

        update_post_meta($this->get_id(), '_product_attributes', $arAttributes);
    }

    private function updateObjectTerm($product_id, $terms, $taxonomy, $append = true )
    {
        $result = array();

        if( 'product_cat' == $taxonomy ) {
            $default_term_id = get_option( 'default_' . $taxonomy );

            if( 'off' === ($post_relationship = Utils::get('post_relationship')) ) {
                return 0;
            }

            elseif( 'default' == $post_relationship ) {
                $object_terms = wp_get_object_terms( $product_id, $taxonomy, array('fields' => 'ids') );

                if( is_wp_error( $object_terms ) || empty( $object_terms ) ) {
                    $result = wp_set_object_terms( $product_id, (int) $default_term_id, $taxonomy );
                }
            }

            $is_object_in_term = is_object_in_term( $product_id, $taxonomy, 'uncategorized' );
            $append = $is_object_in_term && !is_wp_error( $is_object_in_term ) ? false : $append;
        }

        if( empty($result) ) $result = wp_set_object_terms( $product_id, array_map('intval', $terms), $taxonomy, $append );

        if( is_wp_error($result) ) {
            Utils::addLog( $result, array(
                'product_id' => $product_id,
                'taxonomy'   => $taxonomy,
                'terms'     => $terms,
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

        if( !$this->isNew() ) return $count;

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
        $post_attribute = Plugin::get('post_attribute');

        $taxs = array();

        foreach ($this->properties as $Relationship)
        {
            if( 'off' === $post_attribute ) continue;

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
