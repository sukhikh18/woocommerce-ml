<?php


namespace NikolayS93\Exchange\Model\Abstracts;


use NikolayS93\Exchange\Error;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Traits\ItemMeta;
use NikolayS93\Exchange\ORM\Collection;
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

        $this->set_id( $term['term_id'] )
             ->set_name( $term['name'] )
             ->set_slug( $term['slug'] )
             ->set_taxonomy( $this->get_taxonomy_name() )
             ->set_description( $term['description'] )
             ->set_external( $external ? $external : $term['external'] );

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

        return $this;
    }

    public function set_name( $name ) {
        $this->term['name'] = trim( apply_filters( 'Term::set_name', $name, $this ) );

        return $this;
    }

    public function set_slug( $slug ) {
        if ( empty( $slug ) && isset( $this->term['name'] ) ) {
            $slug = $this->term['name'];
        }

        $this->term['slug'] = sanitize_title( apply_filters( 'Term::set_slug', $slug, $this ) );

        return $this;
    }

    public function set_taxonomy( $tax ) {
        $this->term_taxonomy['taxonomy'] = $tax;

        return $this;
    }

    abstract function get_taxonomy_name();

    public function set_description( $desc ) {
        $this->term_taxonomy['description'] = (string) $desc;

        return $this;
    }

    function set_external( $ext ) {
        if ( empty( $ext ) ) {
            $ext = esc_cyr( $this->term['slug'] );
        }

        $this->set_meta( static::get_external_key(), $ext );

        return $this;
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

        if( !$this->get_name() ) {
            var_dump($this);
            die();
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
            Error::set_wp_error( $result, null, 'Warning', true );
            Error::set_message( print_r( $this, 1 ), 'Description', true );
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
        return esc_external( $this->get_meta( static::get_external_key() ) );
    }

    function get_external() {
        return $this->get_taxonomy() . '/' . $this->get_raw_external();
    }
}