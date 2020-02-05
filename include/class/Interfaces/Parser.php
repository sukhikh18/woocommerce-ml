<?php

// Observer.
interface Parser {
    // Get parsed items.
    public function get_categories();
    public function get_warehouses();
    public function get_properties();
    public function get_products();
    public function get_offers();
    // Events
    function add_category_recursive( $category, $parent = null ); // (private)
    function category_event( Event\CategoryEvent $categoryEvent );
    function warehouse_event( Event\WarehouseEvent $warehouseEvent );
    function property_event( Event\PropertyEvent $propertyEvent );
    function product_event( Event\ProductEvent $productEvent );
    function offer_event( Event\OfferEvent $offerEvent );
    // @todo
    function parse_requisites_as_categories();
    function parse_requisites_as_warehouses();
    function parse_requisites_as_properties();
}