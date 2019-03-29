<?php

namespace NikolayS93\Exchange\Decorator;

use NikolayS93\Exchange\Model\OfferModel;

/**
 * Offer ParserModel to
 */
class OfferDecorator
{
    /** @var NikolayS93\Exchange\Model\OfferModel */
    private $WPModelOffer;

    function __construct( \CommerceMLParser\Model\Offer $offer )
    {
        /** @var String */
        $external_id = $offer->getId();

        $this->WPModelOffer = new OfferModel( $external_id );

        $name = $offer->getName();
        if( !empty($name) ) $this->WPModelOffer->post->post_title = $name;

        $quantity = $offer->getQuantity();
        $this->WPModelOffer->set_property( 'quantity', $quantity );

        $prices = $offer->getPrices();
        $this->setPrices($prices);

        $warehouses = $offer->getWarehouses();
        $this->setWarehouses($warehouses);

        $baseunit = $offer->getBaseUnit();
        $this->setBaseUnit($baseunit);
    }

    public function getModel()
    {
        return $this->WPModelOffer;
    }

    /**
     * @param \CommerceMLParser\ORM\Collection $prices
     */
    protected function setPrices( $prices )
    {
        // $price->getShowing()
        // $price->getId()
        // $this->WPModelOffer->set_property('currency', $price->getCurrency());
        if( !$prices->isEmpty() ) {
            $this->WPModelOffer->set_property('price', $prices->current()->getPrice());
        }
    }

    protected function setWarehouses( $warehouses )
    {
        foreach ($warehouses as $warehouse) {
            $this->WPModelOffer->stock_wh[$warehouse->getId()] = $warehouse->getQuantity();
        }
    }

    protected function setBaseUnit( $baseunit )
    {
        $current = $baseunit->current();

        if( !$name = $current->getNameInterShort() ) {
            $name = $current->getNameFull();
        }

        $this->WPModelOffer->set_property('unit', $name);
    }
}