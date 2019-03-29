<?php

/**
 * Works with terms, term_taxonomy, term_relationships, termmeta
 */
class ExchangeTerm
{
    use ExchangeItemMeta;

    /**
     * @sql FROM $wpdb->terms AS t
     *      INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
     *      WHERE t.term_id = %d
     */
    private $term = array();
    private $term_taxonomy = array();

    private $parent_ext;

    static function get_structure()
    {
        $structure = array(
            'terms' => array(
                'term_id'    => '%d',
                'name'       => '%s',
                'slug'       => '%s',
                'term_group' => '%d',
            ),
            'term_taxonomy' => array(
                'term_taxonomy_id' => '%d',
                'term_id'          => '%d',
                'taxonomy'         => '%s',
                'description'      => '%s',
                'parent'           => '%d',
                'count'            => '%d',
            ),
        );

        return $structure;
    }

    static function get_metastructure()
    {
        $structure = array('termmeta' => array(
            'meta_id'    => '%d',
            'term_id'    => '%d',
            'meta_key'   => '%s',
            'meta_value' => '%s',
        ));

        return $structure;
    }

    function __construct( Array $term, $ext_id = '', $meta = array() )
    {
        $this->term = shortcode_atts( array(
            'term_id'    => 0,
            'name'       => '',
            'slug'       => '',
            'term_group' => '',
        ), $term );

        $this->term_taxonomy = shortcode_atts( array(
            'term_taxonomy_id' => 0,
            'term_id'          => 0,
            'taxonomy'         => '',
            'description'      => '', // 1c 8.2 not has a cat description?
            'parent'           => 0,
            /** @note Need update after set relationships */
            'count'            => 0,
        ), $term );

        if( isset( $term['parent_ext'] ) ) {
            $this->parent_ext = $term_tax['taxonomy'] . '/' . (string) $term['parent_ext'];
        }

        if( !$this->term['slug'] ) {
            $this->term['slug'] = strtolower($this->term['name']);
        }

        /**
         * Its true?
         */
        if( !$this->term_taxonomy['term_taxonomy_id'] ) {
            $this->term_taxonomy['term_taxonomy_id'] = $this->term_taxonomy['term_id'];
        }

        /**
         * That is the govnocode?
         */
        if( !$ext_id ) $ext_id = isset($meta[EXT_ID]) ? $meta[EXT_ID]: '';
        $meta[EXT_ID] = $term_tax['taxonomy'] . '/' . $ext_id;

        $this->setExternal( $this->term_taxonomy, $ext_id );
        $this->setMeta($meta);
    }

    function getTerm()
    {
        return new WP_Term( array_merge($this->term, $this->term_taxonomy) );
    }

    function getExternal()
    {
        return $this->getMeta( EXT_ID );
    }

    public function get_id()
    {
        return isset($this->term->term_id) ? (int) $this->term->term_id : '';
    }

    public function get_name()
    {
        return isset($this->term->name) ? (string) $this->term->name : '';
    }

    public function get_slug()
    {
        return isset($this->term->slug) ? (string) $this->term->slug : '';
    }

    public function get_description()
    {
        return isset($this->term_taxonomy->description) ? (string) $this->term_taxonomy->description : '';
    }

    public function get_parent()
    {
        return isset($this->term_taxonomy->parent) ? (string) $this->term_taxonomy->parent : '';
    }

    public function get_count()
    {
        return isset($this->term_taxonomy->count) ? (string) $this->term_taxonomy->count : '';
    }

    function setPostRelation( ExchangePost $post )
    {
    }
}