<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Update;

class TermModel
{
    /** @const terms structure */
    private static $structure = array(
        'term_id'    => '%d',
        'name'       => '%s',
        'slug'       => '%s',
        'term_group' => '%d',
    );

    /** @const termmeta structure */
    private static $meta_structure = array(
        'meta_id'    => '%d',
        'term_id'    => '%d',
        'meta_key'   => '%s',
        'meta_value' => '%s',
    );

    public $term;

    public $requisites;

    public $parent;

    /**
     * Get WP_Term instance (from database)
     * @param  int $term_id
     * @return WP_Term
     */
    public static function get_term( $term_id = 0 ) // : WP_Term
    {
        return WP_Term::get_instance( $term_id );
    }

    /**
     * Get term_id by termmeta external
     * @param  string $ext external code sim: XML/$code
     * @return int
     */
    public static function get_id_by_ext( $ext )
    {
        global $wpdb;

        $query  = $wpdb->prepare("
            SELECT term_id FROM {$wpdb->termmeta}
            WHERE `meta_key` = '%s' AND `meta_value` = '%s'
            LIMIT 1", EX_EXT_METAFIELD, $ext);

        $term_id = $wpdb->get_var($query);

        return (int) $term_id;
    }

    public static function get_structure()
    {
        return static::$structure;
    }

    public static function get_meta_structure()
    {
        return static::$meta_structure;
    }

    public function __construct( $external_id, $term, $type = 'XML' )
    {
        $this->term = new \stdClass();

        if( is_object($term) ) {
            foreach (get_object_vars($term) as $key => $value) {
                $this->term->$key = $value;
            }
        }

        $this->requisites[ EX_EXT_METAFIELD ] = (object) array(
            'meta_value' => strtoupper($type) . '/' . esc_attr($external_id),
        );

        $this->parent = (object) array(
            'id' => '',
            'external' => '',
        );
    }

    public function get_id( $force = false )
    {
        if( $force && empty($this->term->term_id) ) {
            if( $mime = $this->get_mime_type() ) {
                $this->term->term_id = static::get_id_by_ext( $mime );
            }
        }

        return !empty($this->term->term_id) ? (int) $this->term->term_id : false;
    }

    public function set_id( $id )
    {
        $this->term->term_id = (int) $id;
    }

    public function get_external()
    {
        if( $ext = (string) $this->requisites[ EX_EXT_METAFIELD ]->meta_value ) {
            return 0 === strpos($ext, 'XML') ? $ext : 'XML/' . $ext;
        }

        return '';
    }

    public function get_raw_external()
    {
        return substr($this->get_external(), 4);
    }

    public function get_name()
    {
        return isset($this->term->name) ? (string) $this->term->name : '';
    }

    public function get_description()
    {
        return isset($this->term->description) ? (string) $this->term->description : '';
    }

    /**
     * @param  boolean $force Get from bd if is not exists
     * @return int term_id
     */
    public function get_parent_id( $force = false )
    {
        if( $force && empty($this->parent->id) ) {
            if( $mime = $this->get_parent_external() ) {
                $this->parent->id = static::get_id_by_ext( $mime );
            }
        }

        return !empty($this->parent->id) ? (int) $this->parent->id : false;
    }

    public function set_parent_id( $id )
    {
        $this->parent->id = (int) $id;
    }

    public function get_parent_external()
    {
        if( $ext = (string) $this->parent->external ) {
            return 0 === strpos($ext, 'XML') ? $ext : 'XML/' . $ext;
        }

        return '';
    }

    public function get_parent_raw_external()
    {
        return substr($this->get_parent_external(), 4);
    }

    public function set_property( $property_key, $property_value )
    {
        $this->requisites[ $property_key ] = sanitize_text_field($property_value);
    }

    public function get_property( $property_key )
    {
        $result = isset($this->requisites[ $property_key ]) ? $this->requisites[ $property_key ]: false;

        return $result;
    }

    function prepare()
    {
    }

    function fill_meta( &$insert, &$phs )
    {
        array_push( $insert,
            $this->get_property('meta_id'),
            $this->get_id(),
            EX_EXT_METAFIELD,
            $this->get_external()
        );

        array_push($phs, Update::get_sql_placeholder( static::get_meta_structure() ));
    }
}
