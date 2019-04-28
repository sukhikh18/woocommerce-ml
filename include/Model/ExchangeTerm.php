<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\ORM\ExchangeItemMeta;

/**
 * Works with terms, term_taxonomy, term_relationships, termmeta
 */
class ExchangeTerm implements Interfaces\ExternalCode
{
    const EXT_ID = '_ext_ID';

    use ExchangeItemMeta;

    /**
     * @sql FROM $wpdb->terms AS t
     *      INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
     *      WHERE t.term_id = %d
     */
    private $term = array();
    private $term_taxonomy = array();

    private $parent_ext;

    /**
     * @var int for easy external meta update
     */
    public $meta_id;

    static function get_structure( $key )
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
            'termmeta' => array(
                'meta_id'    => '%d',
                'term_id'    => '%d',
                'meta_key'   => '%s',
                'meta_value' => '%s',
            )
        );

        if( isset( $structure[$key] ) ) {
            return $structure[$key];
        }

        return false;
    }

    static function getExtID()
    {
        return apply_filters('ExchangeTerm::getExtID', self::EXT_ID);
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

        if( $term_id = $this->term['term_id'] ? $this->term['term_id'] : $this->term_taxonomy['term_id'] ) {
            $this->set_id( $term_id );
        }

        if( isset( $term['parent_ext'] ) ) {
            $this->parent_ext = $this->term_taxonomy['taxonomy'] . '/' . (string) $term['parent_ext'];
        }

        if( !$this->term['slug'] ) {
            $this->term['slug'] = Utils::esc_cyr($this->term['name']);
        }

        /**
         * That is the govnocode?
         */
        if( !$ext_id ) $ext_id = isset($meta[EXT_ID]) ? $meta[EXT_ID] : Utils::esc_cyr($this->term['slug']);
        $meta[EXT_ID] = $this->term_taxonomy['taxonomy'] . '/' . $ext_id;

        // $this->setExternal( $this->term_taxonomy, $ext_id );
        $this->setMeta($meta);
    }

    function getTerm()
    {
        return new \WP_Term( (object) array_merge($this->term, $this->term_taxonomy) );
    }

    function getExternal()
    {
        return $this->getMeta( $this->getExtID() );
    }

    function getParentExternal()
    {
        return $this->parent_ext;
    }

    function setExternal( $ext )
    {
        $this->setMeta( $this->getExtID(), $ext );
    }

    public function get_id()
    {
        return isset($this->term['term_id']) ? (int) $this->term['term_id'] : '';
    }

    public function set_id( $term_id )
    {
        if( is_object( $term_id ) ) {
            $this->term['term_id'] = (int) $term_id->term_id;
        }
        elseif( is_array( $term_id ) ) {
            $this->term['term_id'] = (int) $term_id['term_id'];
        }
        else {
            $this->term['term_id'] = (int) $term_id;
        }

        $this->term_taxonomy['term_id'] = $this->term['term_id'];

        /**
         * Its true?
         */
        if( !$this->term_taxonomy['term_taxonomy_id'] ) {
            $this->term_taxonomy['term_taxonomy_id'] = (int) $this->term_taxonomy['term_id'];
        }
    }

    public function get_parent_id()
    {
        return isset($this->term_taxonomy['parent']) ? (int) $this->term_taxonomy['parent'] : 0;
    }

    public function set_parent_id( $term_id )
    {
        return $this->term_taxonomy['parent'] = (int) $term_id;
    }

    public function get_name()
    {
        return isset($this->term['name']) ? (string) $this->term['name'] : '';
    }

    public function get_slug()
    {
        return isset($this->term['slug']) ? (string) $this->term['slug'] : '';
    }

    public function get_description()
    {
        return isset($this->term_taxonomy['description']) ? (string) $this->term_taxonomy['description'] : '';
    }

    public function get_count()
    {
        return isset($this->term_taxonomy['count']) ? (string) $this->term_taxonomy['count'] : '';
    }

    public function setTaxonomy( $tax )
    {
        $ext = $this->getExternal();
        if( false !== ($pos = strpos($ext, '/')) ) {
            $this->setExternal($tax . substr($ext, $pos));
        }

        $this->term_taxonomy['taxonomy'] = $tax;
    }

    function prepare()
    {
        $this->term['name'] = preg_replace("/(^[0-9\/|\-_.]+. )/", "", (string) $this->term['name']);
    }

    static public function fillExistsFromDB( &$terms ) // , $taxonomy = ''
    {
        /** @global wpdb wordpress database object */
        global $wpdb;

        /**
         * @var boolean get data for items who not has term_id
         * @todo
         */
        $orphaned_only = true;

        /** @var List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /** @var array list of objects exists from posts db */
        $_exists = array();
        $exists = array();

        foreach ($terms as $rawExternalCode => $term) {
            $_external = $term->getExternal();
            $_p_external = $term->getParentExternal();

            if( !$term->get_id() ) {
                $externals[] = "`meta_value` = '". $_external ."'";
            }

            if( $_p_external && $_external != $_p_external && !$term->get_parent_id() ) {
                $externals[] = "`meta_value` = '". $_p_external ."'";
            }
        }

        $externals = array_unique($externals);

        /**
         * Get from database
         */
        if( !empty($externals) ) {
            $exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", $externals) . ")";

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

        $needRepeat = false;
        foreach ($terms as &$term)
        {
            $ext = $term->getExternal();

            if(!empty( $exists[ $ext ] )) {
                $term->set_id( $exists[ $ext ]->term_id );
                $term->meta_id = $exists[ $ext ]->meta_id;
            }

            $parent_ext = $term->getParentExternal();
            if( $parent_ext ) {
                if(!empty( $exists[ $parent_ext ] )) {
                    $term->set_parent_id( $exists[ $parent_ext ]->term_id );
                }
                else {
                    $needRepeat = true;
                }
            }
        }

        return $needRepeat;
    }
}