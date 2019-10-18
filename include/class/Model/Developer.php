<?php


namespace NikolayS93\Exchanger\Model;


use NikolayS93\Exchanger\Model\Abstracts\Term;
use NikolayS93\Exchanger\Model\Interfaces\HasParent;
use NikolayS93\Exchanger\Plugin;
use function NikolayS93\Exchanger\check_mode;
use function NikolayS93\Exchanger\Error;

class Developer extends Term {
    public function get_taxonomy_name() {
        return 'brand';
    }

    function prepare() {
        $Plugin = Plugin::get_instance();
        /** @var Int $term_id WP_Term->term_id */
        $term_id = $this->get_id();

        if ( check_mode( $term_id, $Plugin->get_setting( 'developer_mode' ) ) ) {
            // Do not update name?
            switch ( $Plugin->get_setting( 'dev_name' ) ) {
                case false:
                    if ( $term_id ) {
                        $this->unset_name();
                    }
                    break;
            }

            if ( ! check_mode( $term_id, $Plugin->get_setting( 'dev_desc' ) ) ) {
                $this->unset_description();
            }

            return true;
        }

        return false;
    }
}