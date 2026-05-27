<?php
/**
 * Plugin Name: BLT M365 WP SSO
 * Plugin URI: https://s-fx.com/plugins/blt-m365-wp-sso/
 * Description: Single Sign-On for WordPress with Microsoft 365 / Entra ID (Azure AD). One-click OAuth2 login, auto-inserted login button, shortcode, and self-updating from GitHub.
 * Version: 3.0.1
 * Author: S-FX.COM
 * Author URI: https://s-fx.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blt-m365-wp-sso
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'BLT_M365_SSO_VERSION', '3.0.1' );
define( 'BLT_M365_SSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLT_M365_SSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLT_M365_SSO_PLUGIN_FILE', __FILE__ );
define( 'BLT_M365_SSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once BLT_M365_SSO_PLUGIN_DIR . 'includes/class-blt-m365-sso.php';
require_once BLT_M365_SSO_PLUGIN_DIR . 'includes/class-blt-m365-sso-admin.php';
require_once BLT_M365_SSO_PLUGIN_DIR . 'includes/class-blt-m365-sso-auth.php';
require_once BLT_M365_SSO_PLUGIN_DIR . 'includes/class-blt-m365-sso-shortcode.php';
require_once BLT_M365_SSO_PLUGIN_DIR . 'includes/class-blt-m365-sso-updater.php';

/**
 * Initialize the plugin.
 */
function blt_m365_sso_init() {
    $plugin = new BLT_M365_SSO_Plugin();
    $plugin->run();
}
blt_m365_sso_init();

/**
 * Plugin activation hook. Sets sensible defaults on first activation.
 */
function blt_m365_sso_activate() {
    $defaults = array(
        'm365_sso_add_to_login'  => '1',
        'm365_sso_auto_redirect' => '1',
        'm365_sso_create_users'  => '1',
        'm365_sso_default_role'  => 'subscriber',
        'm365_sso_button_text'   => 'Login with Microsoft 365',
    );

    foreach ( $defaults as $option => $value ) {
        if ( false === get_option( $option ) ) {
            update_option( $option, $value );
        }
    }
}
register_activation_hook( __FILE__, 'blt_m365_sso_activate' );

/**
 * Add a Settings link on the Plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified links.
 */
function blt_m365_sso_settings_link( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=blt-m365-wp-sso' ) ) . '">'
        . esc_html__( 'Settings', 'blt-m365-wp-sso' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . BLT_M365_SSO_PLUGIN_BASENAME, 'blt_m365_sso_settings_link' );
