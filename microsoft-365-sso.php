<?php

/**
 * Plugin Name: Single Sign-On with 365
 * Plugin URI: https://s-fx.com/plugins/single-sign-on-with-365/
 * Description: Single Sign-On integration for WordPress with Microsoft 365.
 * Version: 1.0.1
 * Author: S-FX.COM
 * Author URI: https://s-fx.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365sso.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365-sso-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-microsoft-365-sso-auth.php';

function m365_sso_init()
{
    $plugin = new M365_SSO_Plugin();
    $plugin->run();
}

m365_sso_init();
