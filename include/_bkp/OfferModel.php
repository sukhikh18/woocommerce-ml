<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Decorator\OfferDecorator;

class OfferModel extends ItemModel
{
    /**
     * @var array stock by warehouse ext_code => qty
     */
    public $stock_wh;

    function __construct( $external_id )
    {
        parent::__construct($external_id, null, 'XML');
    }

    /**
     * @param  [type] $offer New cloned offer
     * @return [type]        [description]
     */
    function merge( $nextOffer )
    {
        // do_action( 'NikolayS93\Exchange\Model\OfferModel::merge()', $nextOffer );

        /**
         * @todo May be merge recursive?
         */
        foreach (get_object_vars($nextOffer) as $key => $value) {
            if( 'post' == $key ) {
                foreach (get_object_vars($value) as $property => $prop_value) {
                    if( $prop_value ) $this->post->$property = $prop_value;
                }
            }
            else {
                if( $value ) $this->$key = $value;
            }
        }
    }
}
