<?php

interface Plugin {
    public function get_option_name( $suffix = '' );
    public function get_permissions();
    public function get_dir( $path = '' );
    public function get_file( $dir_path, $filename );
    public function get_url( $path = '' );
    public function get_template( $template );
    public function get_setting( $prop_name = null, $default = false, $context = '' );
    public function set_setting( $prop_name, $value = '', $context = '' );
    // IO @todo replace to utils functions
    public function get_upload_dir();
    public function try_make_dir( $dir = '' );
    public function check_writable( $dir );
    public function get_exchange_dir( $namespace = null );
    public function get_exchange_file( $filepath, $namespace = 'catalog' );
    public function get_exchange_files( $filename = null, $namespace = 'catalog' );
}
