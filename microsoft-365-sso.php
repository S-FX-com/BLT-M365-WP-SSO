<?php
/**
 * Plugin Name: Single Sign-On with 365
 * Plugin URI: https://s-fx.com/plugins/single-sign-on-with-365/
 * Description: Single Sign-On integration for WordPress with Microsoft 365. Authenticate users via Azure AD with OAuth2, shortcodes, and login form integration.
 * Version: 2.0.0
 * Author: S-FX.COM
 * Author URI: https://s-fx.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: single-sign-on-with-365
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'M365_SSO_VERSION', '2.0.0' );
define( 'M365_SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'M365_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'M365_SSO_PLUGIN_FILE', __FILE__ );
define( 'M365_SSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once M365_SSO_PLUGIN_DIR . 'includes/class-microsoft-365sso.php';
require_once M365_SSO_PLUGIN_DIR . 'includes/class-microsoft-365-sso-admin.php';
require_once M365_SSO_PLUGIN_DIR . 'includes/class-microsoft-365-sso-auth.php';
require_once M365_SSO_PLUGIN_DIR . 'includes/class-microsoft-365-sso-shortcode.php';

/**
 * Initialize the plugin.
 */
function m365_sso_init() {
    $plugin = new M365_SSO_Plugin();
    $plugin->run();
}
m365_sso_init();

/**
 * Plugin activation hook.
 */
function m365_sso_activate() {
    // Set default options on first activation.
    if ( false === get_option( 'm365_sso_add_to_login' ) ) {
        update_option( 'm365_sso_add_to_login', '1' );
    }
    if ( false === get_option( 'm365_sso_auto_redirect' ) ) {
        update_option( 'm365_sso_auto_redirect', '1' );
    }
    if ( false === get_option( 'm365_sso_create_users' ) ) {
        update_option( 'm365_sso_create_users', '1' );
    }
    if ( false === get_option( 'm365_sso_default_role' ) ) {
        update_option( 'm365_sso_default_role', 'subscriber' );
    }
    if ( false === get_option( 'm365_sso_button_text' ) ) {
        update_option( 'm365_sso_button_text', 'Sign in with Microsoft 365' );
    }
}
register_activation_hook( __FILE__, 'm365_sso_activate' );

/**
 * Add settings link on Plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified links.
 */
function m365_sso_settings_link( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=microsoft-365-sso' ) ) . '">'
        . esc_html__( 'Settings', 'single-sign-on-with-365' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . M365_SSO_PLUGIN_BASENAME, 'm365_sso_settings_link' );
