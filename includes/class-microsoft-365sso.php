<?php

class M365_SSO_Plugin
{
    private $admin;
    private $auth;

    public function __construct()
    {
        $this->admin = new M365_SSO_Admin();
        $this->auth = new M365_SSO_Auth();
    }

    public function run()
    {
        $this->admin->init();
        $this->auth->init();
        add_action('login_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('microsoft-365-sso-style', plugin_dir_url(__FILE__) . '../css/microsoft-365-sso.css', array(), '1.0.1');
    }
}
