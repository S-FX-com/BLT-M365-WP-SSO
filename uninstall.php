<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options and user meta from the database.
 *
 * @package SingleSignOnWith365
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Plugin options to remove.
$options = array(
    'm365_client_id',
    'm365_client_secret',
    'm365_tenant_id',
    'm365_sso_add_to_login',
    'm365_sso_auto_redirect',
    'm365_sso_create_users',
    'm365_sso_default_role',
    'm365_sso_button_text',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove user meta created by the plugin.
delete_metadata( 'user', 0, 'm365_sso_profile_photo_id', '', true );
delete_metadata( 'user', 0, 'm365_sso_user', '', true );
