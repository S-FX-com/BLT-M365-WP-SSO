<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options and user meta from the database.
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Plugin options to remove.
$options = array(
    'm365_client_id',
    'm365_client_secret',
    'm365_tenant_id',
    'm365_github_token',
    'm365_sso_add_to_login',
    'm365_sso_auto_redirect',
    'm365_sso_create_users',
    'm365_sso_default_role',
    'm365_sso_button_text',
    'm365_sso_allowed_domains',
    'm365_sso_sync_photo',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove user meta created by the plugin.
delete_metadata( 'user', 0, 'm365_sso_profile_photo_id', '', true );
delete_metadata( 'user', 0, 'm365_sso_profile_photo_url', '', true );
delete_metadata( 'user', 0, 'm365_sso_user', '', true );
delete_metadata( 'user', 0, 'm365_sso_oid', '', true );
