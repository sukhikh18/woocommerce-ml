<?php


namespace NikolayS93\Exchange\Model\Abstracts;


use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use function NikolayS93\Exchange\esc_cyr;
use function NikolayS93\Exchange\esc_external;

abstract class Term {

    use ItemMeta;

    protected $term;
    protected $term_taxonomy;

    /**
     * @todo clear this
     */
    function __construct( $term, $external = '', $meta = array() ) {
        $term = wp_parse_args( $term, array(
            'term_id'    => 0,
            'slug'       => '',
            'name'       => '',
            'term_group' => '',

            'term_taxonomy_id' => 0,
            'taxonomy'         => '',
            'description'      => '', // 1c 8.2 not has a cat description?
            'parent'           => 0,
            'count'            => 0,

            'external'   => '',
            'parent_ext' => '',
        ) );

        $this->set_id( $term['term_id'] );
        $this->set_name( $term['name'] );
        $this->set_slug( $term['slug'] );
        $this->set_taxonomy( $this->get_taxonomy_name() );
        $this->set_description( $term['description'] );

        $this->set_external( $external ? $external : $term['external'] );
        if ( $this instanceof HasParent ) {
            $this->set_parent_external( $term['parent_ext'] );
        }

        $this->set_meta( $meta );
    }

    public function esc_id( $term_id ) {
        if ( $term_id instanceof \WP_Term ) {
            return (int) $term_id->term_id;
        } elseif ( is_array( $term_id ) ) {
            return (int) $term_id['term_id'];
        } else {
            return (int) $term_id;
        }
    }

    public function set_id( $term_id ) {
        $this->term['term_id'] =
        $this->term_taxonomy['term_id'] =
        $this->term_taxonomy['term_taxonomy_id'] = // @todo check its true?
            $this->esc_id( $term_id );
    }

    public function set_name( $name ) {
        if ( $name ) {
            $this->term['name'] = preg_replace( "/(^[\0-9/|_.*]+\. )/", "", (string) $name );
        }
    }

    public function set_slug( $slug ) {
        if ( empty( $slug ) && isset( $this->term['name'] ) ) {
            $slug = $this->term['name'];
        }

        $this->term['slug'] = sanitize_title( esc_cyr( (string) $slug, false ) );
    }

    public function set_taxonomy( $tax ) {
        $this->term_taxonomy['taxonomy'] = $tax;
    }

    abstract function get_taxonomy_name();

    public function set_description( $desc ) {
        return $this->term_taxonomy['description'] = (string) $desc;
    }

    function set_external( $ext ) {
        if ( empty( $ext ) ) {
            $ext = esc_cyr( $this->term['slug'] );
        }

        $this->set_meta( static::get_external_key(), $ext );
    }

    static function get_external_key() {
        return apply_filters( 'ExchangeTerm::get_external_key', EXCHANGE_EXTERNAL_CODE_KEY );
    }

    static function get_structure( $key ) {
        $structure = array(
            'terms'         => array(
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
            'term_meta'     => array(
                'meta_id'    => '%d',
                'term_id'    => '%d',
                'meta_key'   => '%s',
                'meta_value' => '%s',
            )
        );

        if ( isset( $structure[ $key ] ) ) {
            return $structure[ $key ];
        }

        return false;
    }

    static public function fillExistsFromDB( &$terms ) // , $taxonomy = ''
    {
        /** @global \wpdb $wpdb wordpress database object */
        global $wpdb;

        /**
         * @var boolean get data for items who not has term_id
         * @todo
         */
        $orphaned_only = true;

        /** @var array $externals List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /** @var array list of objects exists from posts db */
        $_exists = array();
        $exists  = array();

        /**
         * @var  $rawExternalCode
         * @var  $term
         */
        foreach ( $terms as $rawExternalCode => $term ) {
            $_external   = $term->get_external();
            $_p_external = $term->get_parent_external();

            if ( ! $term->get_id() ) {
                $externals[] = "`meta_value` = '" . $_external . "'";
            }

            if ( $_p_external && $_external != $_p_external && ! $term->get_parent_id() ) {
                $externals[] = "`meta_value` = '" . $_p_external . "'";
            }
        }

        $externals = array_unique( array_filter( $externals ) );

        /**
         * Get from database
         */
        if ( ! empty( $externals ) ) {
            $exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '" . Category::get_external_key() . "'
                    AND (" . implode( " \t\n OR ", $externals ) . ")";

            $_exists = $wpdb->get_results( $exists_query );
        }

        /**
         * Resort for convenience
         */
        foreach ( $_exists as $exist ) {
            $exists[ $exist->meta_value ] = $exist;
        }
        unset( $_exists );

        $needRepeat = false;
        foreach ( $terms as &$term ) {
            $ext = $term->getExternal();

            if ( ! empty( $exists[ $ext ] ) ) {
                $term->set_id( $exists[ $ext ]->term_id );
                $term->meta_id = $exists[ $ext ]->meta_id;
            }

            $parent_ext = $term->getParentExternal();
            if ( $parent_ext ) {
                if ( ! empty( $exists[ $parent_ext ] ) ) {
                    $term->set_parent_id( $exists[ $parent_ext ]->term_id );
                } else {
                    $needRepeat = true;
                }
            }
        }

        return $needRepeat;
    }

    abstract function prepare();

    public function get_slug() {
        return isset( $this->term['slug'] ) ? (string) $this->term['slug'] : '';
    }

    public function get_description() {
        return isset( $this->term_taxonomy['description'] ) ? (string) $this->term_taxonomy['description'] : '';
    }

    public function get_count() {
        return isset( $this->term_taxonomy['count'] ) ? (string) $this->term_taxonomy['count'] : '';
    }

    function check_mode( $term_id, $setting ) {
        switch ( $setting ) {
            case 'off':
                return false;
                break;

            case 'create':
                return ! $term_id;
                break;

            case 'update':
                return (bool) $term_id;
                break;
        }

        return true;
    }

    function update() {
        if ( $term_id = $this->get_id() ) {
            $result = wp_update_term( $term_id, $this->get_taxonomy(), $this->get_term()->to_array() );
        } else {
            $result = wp_insert_term( $this->get_name(), $this->get_taxonomy(), $this->get_term()->to_array() );
        }

        if ( ! is_wp_error( $result ) ) {
            $this->set_id( $result['term_id'] );
//			if( $this instanceof HasParent ) {
//				foreach ( $termsCollection as &$oTerm ) {
//					if ( $term->getExternal() === $oTerm->getParentExternal() ) {
//						$oTerm->set_parent_id( $term->get_id() );
//					}
//				}
//			}
            return true;
        } else {
            Error::set_wp_error( $result, null, 'Warning' );
        }

        return false;
    }

    public function get_id() {
        return isset( $this->term['term_id'] ) ? (int) $this->term['term_id'] : '';
    }

    public function get_taxonomy() {
        return $this->term_taxonomy['taxonomy'];
    }

    function get_term() {
        return new \WP_Term( (object) array_merge( $this->term, $this->term_taxonomy ) );
    }

    public function get_name() {
        return isset( $this->term['name'] ) ? (string) $this->term['name'] : '';
    }

    function get_raw_external() {
        return esc_external( $this->get_external() );
    }

    function get_external() {
        return $this->get_meta( static::get_external_key() );
    }
}