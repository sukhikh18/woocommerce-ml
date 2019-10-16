<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Interfaces\Term;
use NikolayS93\Exchange\Model\Abstracts\Term as ATerm;
use NikolayS93\Exchange\Plugin;
use function NikolayS93\Exchange\check_mode;
use function NikolayS93\Exchange\Error;
use function NikolayS93\Exchange\Plugin;

/**
 * Works with terms, term_taxonomy, term_relationships, term-meta
 */
class Category extends ATerm implements Term, ExternalCode, Identifiable, HasParent {
    /** @var int for easy external meta update */
    public $meta_id;
    /** @var string parent external code */
    private $parent_ext;

    function prepare() {
        $Plugin = Plugin::get_instance();
        /** @var Int $term_id WP_Term->term_id */
        $term_id = $this->get_id();

        if ( check_mode( $term_id, $Plugin->get_setting( 'category_mode' ) ) ) {
            // Do not update name?
            switch ( $Plugin->get_setting( 'cat_name' ) ) {
                case false:
                    if ( $term_id ) {
                        $this->unset_name();
                    }
                    break;
            }

            if ( ! check_mode( $term_id, $Plugin->get_setting( 'cat_desc' ) ) ) {
                $this->unset_description();
            }

            if ( $this instanceof HasParent ) {
                if ( ! check_mode( $term_id, $Plugin->get_setting( 'skip_parent' ) ) ) {
                    $this->unset_parent_id();
                }
            }

            return true;
        }

        return false;
    }

    function get_taxonomy_name() {
        return apply_filters( Plugin::PREFIX . 'Category::get_taxonomy_name', 'product_cat' );
    }

    function get_parent_external() {
        return $this->parent_ext;
    }

    function set_parent_external( $ext ) {
        $this->parent_ext = (string) $ext;

        return $this;
    }

    public function get_parent_id() {
        return isset( $this->term_taxonomy['parent'] ) ? (int) $this->term_taxonomy['parent'] : 0;
    }

    public function set_parent_id( $term_id ) {
        $this->term_taxonomy['parent'] = (int) $term_id;

        return $this;
    }

    public function unset_parent_id() {
        unset( $this->term_taxonomy['parent'] );

        return $this;
    }

    public function update_object_term( $post_id ) {
        if( !$post_id || !$this->get_id() ) {
            return false;
        }

        if ( 'off' === ( $post_relationship = Plugin()->get_setting( 'post_relationship' ) ) ) {
            return false;
        }

        $taxonomy = $this->get_taxonomy();
        $result = array();

        if ( 'default' == $post_relationship ) {
            $object_terms    = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
            $default_term_id = (int) get_option( 'default_' . $taxonomy );

            // is relatives not exists try set default term
            if ( is_wp_error( $object_terms ) || empty( $object_terms ) ) {
                if ( $default_term_id ) {
                    $result = wp_set_object_terms( $post_id, $default_term_id, $taxonomy, $append = false );
                }
            }
        } else {
            $result = wp_set_object_terms( $post_id, $this->get_id(), $taxonomy, $append = true );
        }

        if ( $result && !is_wp_error( $result ) ) {
            return true;
        }
        else {
            Error()
                ->add_message( $result, 'Warning', true )
                ->add_message( $this, 'Target', true );
        }

        return false;
    }
}
