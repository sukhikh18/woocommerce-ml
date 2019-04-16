<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\ORM\Collection;

function getTaxonomyByExternal( $raw_ext_code )
{
    global $wpdb;

    $rsResult = $wpdb->get_results( $wpdb->prepare("
        SELECT wat.*, watm.* FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id
        WHERE watm.meta_value = %d
        LIMIT 1
        ", $raw_ext_code) );

    if( $res ) {
        $res = current($rsResult);
        $obResult = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $obResult;
}

function getAttributesMap()
{
    global $wpdb;

    $arResult = array();
    $rsResult = $wpdb->get_results( "
        SELECT wat.*, watm.*, watm.meta_value as ext FROM {$wpdb->prefix}woocommerce_attribute_taxonomies AS wat
        INNER JOIN {$wpdb->prefix}woocommerce_attribute_taxonomymeta AS watm ON wat.attribute_id = watm.tax_id" );

    echo "<pre>";
    var_dump( $rsResult );
    echo "</pre>";
    die();

    foreach ($rsResult as $res)
    {
        $arResult[ $res->meta_value ] = new ExchangeAttribute( $res, $res->meta_value );
    }

    return $arResult;
}

/**
 * Works with woocommerce_attribute_taxonomies
 */
class ExchangeAttribute implements Interfaces\ExternalCode
{
    const EXT_ID = '_ext_ID';
    static function getExtID()
    {
        return apply_filters('ExchangeTerm::getExtID', self::EXT_ID);
    }

    /** @var need? */
    private $id;

    private $attribute_name;
    private $attribute_label;
    private $attribute_type = 'select';
    private $attribute_orderby = 'menu_order';
    private $attribute_public = 1;

    private $ext;

    /**
     * @var array List of ExchangeTerm
     */
    private $terms;
    // private $taxonomymeta; ?

    function __construct( $args = array(), $ext = '' )
    {
        foreach (get_object_vars( (object) $args ) as $k => $arg)
        {
            if( property_exists($this, $k) ) $this->$k = $arg;
        }

        $this->attribute_name = wc_attribute_taxonomy_name($this->attribute_name);
        if( strlen($this->attribute_name) >= 28 ) {
            $this->attribute_name = wc_attribute_taxonomy_name(Utils::esc_cyr($this->attribute_label));
        }

        if( $ext ) $this->ext = $ext;

        $this->terms = new Collection();
    }

    function addTerm( $term )
    {
        $term->setTaxonomy( $this->attribute_name );

        $this->terms->add($term);
    }

    /**
     * Object params to array
     * @return array
     */
    public function fetch()
    {
        $attribute =  array(
            'attribute_name'    => str_replace('pa_', '', $this->attribute_name),
            'attribute_label'   => $this->attribute_label,
            'attribute_type'    => $this->attribute_type,
            'attribute_orderby' => $this->attribute_orderby,
            'attribute_public'  => $this->attribute_public,
        );

        return $attribute;
    }

    public function getSlug()
    {
        return $this->attribute_name;
    }

    public function getTerms()
    {
        return $this->terms;
    }

    public function get_id()
    {
        return (int) $this->id;
    }

    public function set_id( $id )
    {
        $this->id = $id;
    }

    function getExternal()
    {
        return $this->ext;
    }

    function setExternal($ext)
    {
        $this->ext = (String) $ext;
    }

    static public function fillExistsFromDB( &$obAttributeTaxonomies ) // , $taxonomy = ''
    {
        /** @global wpdb wordpress database object */
        global $wpdb;

        /** @var boolean get data for items who not has term_id */
        // $orphaned_only = true;

        /** @var List of external code items list in database attribute context (%s='%s') */
        // $externals = array();
        $termExternals = array();

        foreach ($obAttributeTaxonomies as $obAttributeTaxonomy)
        {
            /**
             * Get taxonomy (attribute)
             * var_dump( $obAttributeTaxonomy );
             */
            /**
             * Get terms (attribute values)
             * @var ExchangeTerm $term
             */
            foreach ($obAttributeTaxonomy->getTerms() as $obExchangeTerm)
            {
                $termExternals[] = "`meta_value` = '". $obExchangeTerm->getExternal() ."'";
            }
        }

        // foreach ($terms as $rawExternalCode => $term) {
        //     $_external = $term->getExternal();
        //     $_p_external = $term->getParentExternal();

        //     if( !$term->get_id() ) {
        //         $externals[] = "`meta_value` = '". $_external ."'";
        //     }

        //     if( $_p_external && $_external != $_p_external && !$term->get_parent_id() ) {
        //         $externals[] = "`meta_value` = '". $_p_external ."'";
        //     }
        // }

        /**
         * Get from database
         * @var array list of objects exists from posts db
         */
        $exists  = array();
        $_exists = array();
        if( !empty($termExternals) ) {
            $exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", $termExternals) . ")";

            $_exists = $wpdb->get_results( $exists_query );
        }

        /**
         * Resort for convenience
         */
        foreach($_exists as $exist)
        {
            $exists[ $exist->meta_value ] = $exist;
        }
        unset($_exists);

        foreach ($obAttributeTaxonomies as &$obAttributeTaxonomy)
        {
            /**
             * Get taxonomy (attribute)
             * var_dump( $obAttributeTaxonomy );
             */
            /**
             * Get terms (attribute values)
             * @var ExchangeTerm $term
             */
            foreach ($obAttributeTaxonomy->getTerms() as &$obExchangeTerm)
            {
                $ext = $obExchangeTerm->getExternal();

                if(!empty( $exists[ $ext ] )) {
                    $obExchangeTerm->set_id( $exists[ $ext ]->term_id );
                    $obExchangeTerm->meta_id = $exists[ $ext ]->meta_id;
                }
            }
        }
    }
}