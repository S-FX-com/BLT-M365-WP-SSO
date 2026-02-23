<?php
/**
 * Core plugin class.
 *
 * @package SingleSignOnWith365
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class M365_SSO_Plugin {

    /** @var M365_SSO_Admin */
    private $admin;

    /** @var M365_SSO_Auth */
    private $auth;

    /** @var M365_SSO_Shortcode */
    private $shortcode;

    public function __construct() {
        $this->admin     = new M365_SSO_Admin();
        $this->auth      = new M365_SSO_Auth();
        $this->shortcode = new M365_SSO_Shortcode( $this->auth );
    }

    public function run() {
        $this->admin->init();
        $this->auth->init();
        $this->shortcode->init();

        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_styles' ) );
    }

    /**
     * Enqueue styles on the WordPress login page.
     */
    public function enqueue_login_styles() {
        wp_enqueue_style(
            'microsoft-365-sso-login',
            M365_SSO_PLUGIN_URL . 'css/microsoft-365-sso-login.css',
            array(),
            M365_SSO_VERSION
        );
    }

    /**
     * Enqueue styles on the front end (for shortcode button).
     */
    public function enqueue_front_styles() {
        wp_enqueue_style(
            'microsoft-365-sso-front',
            M365_SSO_PLUGIN_URL . 'css/microsoft-365-sso-front.css',
            array(),
            M365_SSO_VERSION
        );
    }
}
