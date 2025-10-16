=== Single Sign-On with 365 ===
Contributors: s-fx
Tags: microsoft 365, azure, sso, login, active directory, authentication
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Single Sign-On integration for WordPress with Microsoft 365.

== Description ==

`Single Sign-On with 365` allows you to authenticate your WordPress users through Microsoft 365 using Single Sign-On (SSO). This is ideal for organizations that manage users via Azure Active Directory and want a seamless login experience.

**Features:**
- Microsoft 365 login support
- Azure AD authentication
- Role mapping capabilities
- Automatic user creation on login
- Secure login flow using OAuth2

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-m360-sso` directory, or install it directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to `Settings > WP M365 SSO` to configure the plugin.
4. Enter your Microsoft Azure App credentials (Client ID, Tenant ID, Secret).
5. Save the settings and test the login.

== Frequently Asked Questions ==

= Do I need a Microsoft 365 subscription? =
Yes, a Microsoft 365 or Azure AD subscription is required.

= Are users created automatically? =
Yes, if a user does not exist, it can be created upon successful login if that setting is enabled.

= Is multi-site supported? =
Currently, the plugin is optimized for single-site installations.

== Screenshots ==

1. Plugin settings page
2. Login screen with Microsoft SSO option

== Changelog ==

= 1.0 =
* Initial release of the plugin.

== Upgrade Notice ==

= 1.0 =
Initial release
