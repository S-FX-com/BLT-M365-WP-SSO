<?php

/**
 * Plugin Name: Single Sign-On with 365
 * Plugin URI: admin.php?page=microsoft-365-sso
 * Description: Single Sign-On integration for WordPress with Microsoft 365.
 * Version: 1.0
 * Author: S-FX.COM
 * Author URI: https://s-fx.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365-sso.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365-sso-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365-sso-auth.php';

function wp_m365_sso_init()
{
    $plugin = new WP_M365_SSO();
    $plugin->run();
}

wp_m365_sso_init();
