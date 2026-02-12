=== Single Sign-On with 365 ===
Contributors: s-fx
Tags: microsoft 365, azure, sso, login, authentication
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Single Sign-On integration for WordPress with Microsoft 365. Let users sign in with their Microsoft account using OAuth2.

== Description ==

**Single Sign-On with 365** allows you to authenticate your WordPress users through Microsoft 365 using OAuth2 and Azure Active Directory. This is ideal for organizations that manage users via Azure AD and want a seamless login experience.

= Features =

* **Microsoft 365 SSO** — One-click sign-in via Azure AD OAuth2.
* **Setup Guide** — Step-by-step instructions with direct links to the Azure Portal, so you can configure the app registration without guessing.
* **Add to WordPress Login** — Optionally append a "Sign in with Microsoft 365" button to every `wp-login.php` form. Toggle on or off from the Options tab.
* **Shortcode** — Place `[m365_login]` on any page, post, or widget to display a login button. Customize the button text, redirect URL, CSS class, and logged-in message.
* **Auto-redirect** — When a user accesses a member-restricted page and logs in via SSO, they are automatically redirected back to the page they were trying to reach.
* **Auto-create Users** — Optionally create a WordPress account on first SSO login, with a configurable default role.
* **Profile Photo Sync** — Pulls the user's Microsoft profile photo and uses it as their WordPress avatar.
* **Clean Uninstall** — All plugin options and user meta are removed when the plugin is deleted.

= Requirements =

* A Microsoft 365 or Microsoft Entra ID (Azure AD) subscription.
* An Azure AD App Registration with a Client ID, Client Secret, and Tenant ID.
* WordPress 5.0+ and PHP 7.4+.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/single-sign-on-with-365/`, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen.
3. Navigate to **M365 SSO** in the admin sidebar.
4. Follow the **Setup Guide** tab to register an application in Azure and obtain your credentials.
5. Enter the Client ID, Client Secret, and Tenant ID in the **Azure Credentials** tab.
6. Configure login behaviour in the **Options** tab.
7. Optionally add the `[m365_login]` shortcode to pages or posts.

== Frequently Asked Questions ==

= Do I need a Microsoft 365 subscription? =
Yes. You need a Microsoft 365 or Microsoft Entra ID (formerly Azure AD) subscription to register an application and authenticate users.

= Are users created automatically? =
By default, yes. You can disable auto-creation in **M365 SSO > Options**. When disabled, only users who already have a WordPress account with a matching email can sign in.

= How do I add the SSO button to my login page? =
Go to **M365 SSO > Options** and enable "Add to WordPress Login". The button will appear on all standard WordPress login forms.

= Can I place the SSO button anywhere on my site? =
Yes. Use the `[m365_login]` shortcode. See the **Shortcode** tab in the plugin settings for all available attributes.

= What happens if a user visits a restricted page? =
If "Auto-redirect after login" is enabled and a restricted page redirects the user to `wp-login.php`, the SSO button will carry the original page URL through the OAuth flow and redirect the user back after a successful sign-in.

= Is multi-site supported? =
Currently the plugin is designed for single-site installations.

= How do I uninstall cleanly? =
Deactivate and delete the plugin from the Plugins screen. All plugin options and user meta will be removed automatically.

== Screenshots ==

1. Setup Guide tab with step-by-step Azure AD instructions.
2. Azure Credentials tab.
3. Options tab with login and user management settings.
4. Shortcode reference tab.
5. WordPress login page with Microsoft 365 SSO button.

== Changelog ==

= 2.0.0 =
* Major rewrite with enhanced architecture and security.
* New: Tabbed settings page (Setup Guide, Credentials, Options, Shortcode) matching WordPress admin aesthetic.
* New: Step-by-step Azure AD setup guide with direct links to Azure Portal.
* New: `[m365_login]` shortcode with customizable text, redirect, CSS class, and logged-in message.
* New: "Add to WordPress Login" toggle to control whether the SSO button appears on wp-login.php.
* New: Auto-redirect — users accessing restricted pages are redirected back after SSO login.
* New: Auto-create users toggle and configurable default role.
* New: Customizable button text.
* New: Microsoft four-square logo SVG icon on the login button.
* New: Plugin activation sets sensible defaults.
* New: Settings link on the Plugins page.
* New: Clean uninstall via uninstall.php removes all options and user meta.
* New: Centralized version constant (M365_SSO_VERSION) used across all files.
* Fixed: redirect_uri now included in both authorization and token exchange requests (was commented out).
* Fixed: Username collision handling when auto-creating users.
* Fixed: Email validation before user creation.
* Fixed: Profile photo saved via WP_Filesystem instead of file_put_contents.
* Fixed: Proper HTTP status code checks on token exchange response.
* Fixed: Scoped OAuth scope to include openid, email, and profile.
* Improved: Top-level admin menu page instead of sub-menu under Settings.
* Improved: User-friendly error messages with proper HTTP status codes.
* Improved: Text domain support for internationalization.
* Improved: Separate CSS files for admin, login page, and front-end.

= 1.0.1 =
* Fixed: Plugin URI header validation.
* Fixed: Added sanitization to all register_setting() calls.
* Fixed: Sanitized and unslashed $_GET variables for security.
* Fixed: Changed wp_redirect() to wp_safe_redirect() for better security.
* Fixed: Added version parameter to wp_enqueue_style().
* Updated: Changed class prefixes from WP_ to M365_SSO_ to avoid namespace collisions.
* Updated: Tested up to WordPress 6.8.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major update with shortcode support, login form toggle, auto-redirect for restricted pages, setup guide, and multiple security fixes. Recommended for all users.

= 1.0.1 =
Security improvements and WordPress.org compliance fixes. Recommended update for all users.

= 1.0 =
Initial release.
