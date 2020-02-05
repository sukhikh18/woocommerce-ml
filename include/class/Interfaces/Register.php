<?php

interface Register {
    public static function get_warehouse_taxonomy_slug();
    public function register_plugin_page();
    public function register_exchange_url();
    static function get_exchange_table_name();
}
