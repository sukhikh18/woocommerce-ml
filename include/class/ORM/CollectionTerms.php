<?php

namespace NikolayS93\Exchange\ORM;

use NikolayS93\Exchange\Model\Abstracts\Term;
use \NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;

/**
 * Class CollectionTerms
 * @package NikolayS93\Exchange\ORM
 */
class CollectionTerms extends Collection {
    /**
     * @param Collection $terms
     * @param bool $orphaned_only @todo get data for items who not has term_id
     *
     * @return $this
     */
    public function fill_exists( $orphaned_only = true ) {
        /** @global \wpdb $wpdb wordpress database object */
        global $wpdb;

        /** @var array $externals List of external code items list in database attribute context (%s='%s') */
        $externals = array();

        /**
         * @param Term $term
         */
        $build_query = function ( $term ) use ( &$externals ) {
            if ( ! $term->get_id() && $ext = $term->get_external() ) {
                $externals[] = "`meta_value` = '$ext'";
            }

            if ( $term instanceof HasParent && ! $term->get_parent_id() && $parent_ext = $term->get_parent_external() ) {
                $externals[] = "`meta_value` = '$parent_ext'";
            }
        };

        $this->walk( $build_query );

        $externals = array_unique( $externals );
        if ( empty( $externals ) ) {
            return $this;
        }

        /**
         * Get from database
         */
        $external_key   = $this->first()->get_external_key();
        $externals_args = implode( " \t\n OR ", $externals );
        $exists_query   = "
            SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug FROM {$wpdb->prefix}termmeta tm
                INNER JOIN {$wpdb->prefix}terms t ON tm.term_id = t.term_id
            WHERE `meta_key` = '$external_key' AND ($externals_args)";
        $exists         = $wpdb->get_results( $exists_query );

        /**
         * Fill ids
         */
        array_walk( $exists, function ( $result ) use ( $exists ) {
            $external_from_db = $result->meta_value;
            $term             = $this->offsetGet( $external_from_db );

            $term->set_id( $result->term_id );
            $term->meta_id = $result->meta_id;

            if ( $term instanceof HasParent ) {
                if ( false !== $key = array_search( $term->get_parent_external(),
                        wp_list_pluck( $exists, 'meta_value' ) ) ) {
                    $term->set_parent_id( $exists[ $key ]->term_id );
                }
            }
        } );

        return $this;
    }
}
