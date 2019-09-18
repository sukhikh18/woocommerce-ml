<?php

namespace NikolayS93\Exchange\ORM;

use NikolayS93\Exchange\Model\Abstracts\Term;
use NikolayS93\Exchange\Model\Attribute;
use NikolayS93\Exchange\Model\Category;
use NikolayS93\Exchange\Model\Developer;
use NikolayS93\Exchange\Model\ExchangePost;
use \NikolayS93\Exchange\Model\Interfaces\ExternalCode;
use NikolayS93\Exchange\Model\Interfaces\HasParent;
use NikolayS93\Exchange\Model\Interfaces\Identifiable;
use NikolayS93\Exchange\Model\Warehouse;
use NikolayS93\Exchange\Plugin;

/**
 * Class CollectionPosts
 * @package NikolayS93\Exchange\ORM
 */
class CollectionPosts extends Collection {
    /**
     * @param bool $orphaned_only
     *
     * @return $this
     */
    public function fill_exists( $orphaned_only = true ) {
        /** @global \wpdb wordpress database object */
        global $wpdb;

        $Plugin   = Plugin::get_instance();
        $settings = array(
//            'post_name' =>
            'skip_post_author'  => $Plugin->get_setting( 'skip_post_author' ),
            'skip_post_title'   => $Plugin->get_setting( 'skip_post_title' ),
            'skip_post_content' => $Plugin->get_setting( 'skip_post_content' ),
            'skip_post_excerpt' => $Plugin->get_setting( 'skip_post_excerpt' ),
        );

        /** @var array List of external code items list in database attribute context (%s='%s') */
        $post_mime_types = array();

        /**
         * EXPLODE FOR SIMPLE ONLY @param ExchangePost $post
         * @todo repair this
         */
        $build_query = function ( $post ) use ( &$post_mime_types, $orphaned_only ) {
            if ( ! $orphaned_only || ( $orphaned_only && ! $post->get_id() ) ) {
                list( $ext ) = explode( '#', $post->get_external() );

                if ( $ext ) {
                    $post_mime_types[] = sprintf( "`post_mime_type` = '%s'", esc_sql( $ext ) );
                }
            }
        };

        $this->walk( $build_query );

        $post_mime_types = array_unique( $post_mime_types );
        if ( empty( $post_mime_types ) ) {
            return $this;
        }

        $post_mime_types_args = implode( " \t\n OR ", $post_mime_types );
        $exists_query         = "
            SELECT * FROM {$wpdb->prefix}posts p
            WHERE `post_type` = 'product' AND ($post_mime_types_args)";
        $exists               = $wpdb->get_results( $exists_query );

        array_walk( $exists, function ( $result ) use ( $exists, $settings ) {
            $external_from_db = $result->post_mime_type;
            /** @var ExchangePost $post */
            if ( $post = $this->offsetGet( $external_from_db ) ) {
                $post->set_id( $result->ID );

//                if ( $settings['skip_post_author'] ) {
//                    $post->set_author( $result->post_author );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_title( $result->post_title );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_content( $result->post_content );
//                }
//
//                if ( $settings['skip_post_title'] ) {
//                    $post->set_excerpt( $result->post_excerpt );
//                }
            }
        } );

        return $this;
    }

    function getAllRelativeExternals( $orphaned_only = false ) {
        $arExternals = array();

        if ( ! empty( $this->product_cat ) ) {
            /** @var Category $product_cat */
            foreach ( $this->product_cat as $product_cat ) {
                if ( $orphaned_only && $product_cat->get_id() ) {
                    continue;
                }
                $arExternals[] = $product_cat->get_external();
            }
        }

        if ( ! empty( $this->warehouses ) ) {
            /** @var Warehouse $warehouse */
            foreach ( $this->warehouses as $warehouse ) {
                if ( $orphaned_only && $warehouse->get_id() ) {
                    continue;
                }
                $arExternals[] = $warehouse->get_external();
            }
        }

        if ( ! empty( $this->developer ) ) {
            /** @var Developer $developer */
            foreach ( $this->developer as $developer ) {
                if ( $orphaned_only && $developer->get_id() ) {
                    continue;
                }
                $arExternals[] = $developer->get_external();
            }
        }

        if ( ! empty( $this->properties ) ) {
            /** @var Attribute $property */
            foreach ( $this->properties as $property ) {
                foreach ( $property->get_values() as $ex_term ) {
                    if ( $orphaned_only && $ex_term->get_id() ) {
                        continue;
                    }

                    $arExternals[] = $ex_term->get_external();
                }
            }
        }

        return $arExternals;
    }

    function fillExistsRelativesFromDB() {
        /** @global \wpdb $wpdb built in wordpress db object */
        global $wpdb;

        $arExternals = $this->getAllRelativeExternals( true );

        if ( ! empty( $arExternals ) ) {
            foreach ( $arExternals as $strExternal ) {
                $arSqlExternals[] = "`meta_value` = '{$strExternal}'";
            }

            $arTerms = array();

            $exsists_terms_query = "
                SELECT term_id, meta_key, meta_value
                FROM {$wpdb->prefix}term_meta
                WHERE meta_key = '" . Category::get_external_key() . "'
                    AND (" . implode( " \t\n OR ", array_unique( $arSqlExternals ) ) . ")";

            $ardbTerms = $wpdb->get_results( $exsists_terms_query );
            foreach ( $ardbTerms as $ardbTerm ) {
                $arTerms[ $ardbTerm->meta_value ] = $ardbTerm->term_id;
            }

            if ( ! empty( $this->product_cat ) ) {
                /** @var Category $product_cat */
                foreach ( $this->product_cat as &$product_cat ) {
                    $ext = $product_cat->get_external();
                    if ( ! empty( $arTerms[ $ext ] ) ) {
                        $product_cat->set_id( $arTerms[ $ext ] );
                    }
                }
            }

            if ( ! empty( $this->warehouses ) ) {
                /** @var Warehouse $warehouse */
                foreach ( $this->warehouses as &$warehouse ) {
                    $ext = $warehouse->get_external();
                    if ( ! empty( $arTerms[ $ext ] ) ) {
                        $warehouse->set_id( $arTerms[ $ext ] );
                    }
                }
            }

            if ( ! empty( $this->developer ) ) {
                /** @var Developer $developer */
                foreach ( $this->developer as &$developer ) {
                    $ext = $developer->get_external();
                    if ( ! empty( $arTerms[ $ext ] ) ) {
                        $developer->set_id( $arTerms[ $ext ] );
                    }
                }
            }

            if ( ! empty( $this->properties ) ) {
                /** @var Attribute $property */
                foreach ( $this->properties as &$property ) {
                    if ( $property instanceof Attribute ) {
                        foreach ( $property->get_values() as &$term ) {
                            $ext = $term->get_external();
                            if ( ! empty( $arTerms[ $ext ] ) ) {
                                $term->set_id( $arTerms[ $ext ] );
                            }
                        }
                    } else {
                        // exit with error
                        Error()
                            ->add_message('Property not has attribute instance', 'Error', true)
                            ->add_message( $property, 'Target' );
                    }
                }
            }
        }
    }
}
