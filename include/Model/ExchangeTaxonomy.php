<?php

/**
 * @todo @warning @required @note @sql
 */
// CREATE TABLE `woocommerce_attribute_taxonomymeta` (
//     `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
//     `tax_id` bigint(20) unsigned NOT NULL DEFAULT '0',
//     `meta_key` varchar(255) NULL,
//     `meta_value` longtext NULL
// );

// ALTER TABLE `woocommerce_attribute_taxonomymeta`
// ADD INDEX `tax_id` (`tax_id`),
// ADD INDEX `meta_key` (`meta_key`(191));

/**
 * Works with woocommerce_attribute_taxonomies
 */
class ExchangeTaxonomy
{
    private $name;
    private $slug;
    private $type = 'select';
    private $orderby = 'menu_order';
    private $public = 1;

    private $ext;

    /**
     * @var array List of ExchangeTerm
     */
    private $terms;
    // private $taxonomymeta; ?

    function __construct( $args = array(), $ext )
    {
        foreach ($args as $k => $arg)
        {
            if( property_exists($this, $k) ) $this->$k = $arg;
        }

        if( !$this->slug ) $this->slug = wc_attribute_taxonomy_name(strtolower($this->name));

        $this->ext = $ext;
        $this->terms = new Collection();
    }

    function addTerm( $term )
    {
        $this->terms->add($term);
    }

    /**
     * Object params to array
     * @return array
     */
    public function fetch()
    {
        $res = array();
        foreach (get_object_vars($this) as $key => $value) {
            $res[ $key ] = $value;
        }

        return $res;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getTerms()
    {
        return $this->terms;
    }
}