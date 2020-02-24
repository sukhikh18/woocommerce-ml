<?php

interface Plugin {
    public function get_option_name( $suffix = '' );
    public function get_permissions();
    public function get_dir( $path = '' );
    public function get_file( $dir_path, $filename );
    public function get_url( $path = '' );
    public function get_template( $template );
    public function get( $prop_name = null, $default = false, $context = '' );
    public function set( $prop_name, $value = '', $context = '' );
}
