<?php

interface REST_Controller {
    public function has_permissions( $user );
    function register_routes();
    public function status();
    function check_current_user(); // (private)
    function exchange();
    public function checkauth();
    public function init();
    public function file( $requested = 'php://input' );
    public function query();
    public function import( $Parser = null, $Update = null );
    public function deactivate();
    public function complete();
}