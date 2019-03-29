<?php

namespace NikolayS93\Exchange\Decorator;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\Model\TermModel;

/**
 * Product ParserModel to Product Wordpress Model
 */
class TermDecorator
{
    /** @var NikolayS93\Exchange\Model\TermModel */
    private $WPModelTerm;

    function __construct( $term, $parent = null )
    {
        /** @var String */
        $external_id = $term->getId();

        $name = $term->getName();

        $this->WPModelTerm = new TermModel( $external_id, (object) array(
            'name' => $name,
            'slug' => Utils::esc_cyr($name),
        ) );

        if( $parent ) {
            $this->WPModelTerm->parent = (object) array(
                'id'       => '',
                'external' => $parent->getid(),
            );
        }

        // $sku = $term->getSku();
        // $this->WPModelTerm->set_property( 'sku', $sku );
    }

    public function getModel()
    {
        return $this->WPModelTerm;
    }
}