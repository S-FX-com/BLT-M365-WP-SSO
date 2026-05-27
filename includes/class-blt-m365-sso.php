<?php
/**
 * Core plugin class.
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BLT_M365_SSO_Plugin {

    /** @var BLT_M365_SSO_Admin */
    private $admin;

    /** @var BLT_M365_SSO_Auth */
    private $auth;

    /** @var BLT_M365_SSO_Shortcode */
    private $shortcode;

    /** @var BLT_M365_SSO_Updater */
    private $updater;

    public function __construct() {
        $this->admin     = new BLT_M365_SSO_Admin();
        $this->auth      = new BLT_M365_SSO_Auth();
        $this->shortcode = new BLT_M365_SSO_Shortcode( $this->auth );
        $this->updater   = new BLT_M365_SSO_Updater();
    }

    public function run() {
        $this->updater->init();
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
            'blt-m365-sso-login',
            BLT_M365_SSO_PLUGIN_URL . 'css/blt-m365-sso-login.css',
            array(),
            BLT_M365_SSO_VERSION
        );
    }

    /**
     * Enqueue styles on the front end (for the shortcode button).
     */
    public function enqueue_front_styles() {
        wp_enqueue_style(
            'blt-m365-sso-front',
            BLT_M365_SSO_PLUGIN_URL . 'css/blt-m365-sso-front.css',
            array(),
            BLT_M365_SSO_VERSION
        );
    }
}
