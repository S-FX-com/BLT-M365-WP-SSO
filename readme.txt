=== BLT M365 WP SSO ===
Contributors: s-fx
Tags: microsoft 365, azure, entra, sso, login
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Single Sign-On for WordPress with Microsoft 365 / Entra ID. One-click OAuth2 login, an auto-inserted login button, a shortcode, and self-updating from GitHub.

== Description ==

**BLT M365 WP SSO** authenticates your WordPress users through Microsoft 365 / Microsoft Entra ID (Azure AD) using OAuth2. It is ideal for organizations that manage users in Entra ID and want a seamless, secure login experience.

= Features =

* **Microsoft 365 SSO** — One-click sign-in via Azure AD / Entra ID OAuth2.
* **Auto-inserted login button** — A standard "Login with Microsoft 365" button is added to every `wp-login.php` form (toggle on the Options tab).
* **Shortcode** — Place `[m365_login]` on any page, post, or widget. Customize the text, redirect URL, CSS class, and logged-in message.
* **Setup Guide** — Step-by-step in-dashboard instructions with direct links to the Azure portal.
* **Self-updating** — Built-in update checker (powered by the Plugin Update Checker library) surfaces new versions from GitHub on the Plugins screen, with optional private-repo token support.
* **Secure account matching** — Users are matched on their immutable Microsoft object ID, then email, preventing account takeover if an email address is reassigned.
* **Allowed email domains** — Optionally restrict sign-in to specific domains.
* **Auto-redirect** — Return users to the restricted page they were trying to reach after they sign in.
* **Auto-create users** — Optionally create a WordPress account on first SSO login, with a configurable default role.
* **Profile photo sync** — Uses the user's Microsoft profile photo as their WordPress avatar.
* **Clean uninstall** — All plugin options and user meta are removed on deletion.

= Requirements =

* A Microsoft 365 or Microsoft Entra ID (Azure AD) subscription.
* An Azure AD App Registration with a Client ID, Client Secret, and Tenant ID.
* WordPress 5.0+ and PHP 7.4+.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **M365 SSO** in the admin sidebar.
4. Follow the **Setup Guide** tab to register an application in Azure and obtain your credentials.
5. Enter the Client ID, Client Secret, and Tenant ID on the **Azure Credentials** tab.
6. Configure login behaviour on the **Options** tab.
7. Optionally add the `[m365_login]` shortcode to any page or post.

== Frequently Asked Questions ==

= Do I need a Microsoft 365 subscription? =
Yes. You need a Microsoft 365 or Microsoft Entra ID (formerly Azure AD) subscription to register an application and authenticate users.

= How do I add the SSO button to my login page? =
It is added automatically. To turn it off (or back on), go to **M365 SSO > Options** and toggle "Add to WordPress Login".

= Can I place the SSO button anywhere on my site? =
Yes. Use the `[m365_login]` shortcode. See the **Shortcode** tab in the plugin settings for all attributes.

= Can I limit who can sign in? =
Yes. On the **Options** tab you can list "Allowed Email Domains" to restrict sign-in to specific domains, and you can disable automatic account creation so only existing WordPress users can sign in.

= How does the plugin update itself? =
The plugin checks its GitHub repository for new versions and displays updates on the WordPress Plugins screen, just like a WordPress.org plugin. If the repository is private, supply a read-only GitHub access token on the **Updates** tab or define `BLT_M365_SSO_GITHUB_TOKEN` in `wp-config.php`.

= Is multisite supported? =
The plugin is designed for single-site installations.

== For Developers ==

This repository includes a git pre-commit hook that automatically bumps the plugin version on every commit, keeping the `Version:` header, the `BLT_M365_SSO_VERSION` constant, and `readme.txt` in sync.

Enable it once per clone:

`git config core.hooksPath .githooks`

* Default bump is patch level. For a manual bump run `bash bin/bump-version.sh minor` (or `major`).
* Skip a single commit with `BLT_M365_SSO_SKIP_BUMP=1 git commit ...`.

While the updater tracks the `main` branch it prefers GitHub Releases or tags if any exist, and otherwise reads the `Version:` header on the branch. For commit-driven updates, do not publish Releases/tags; for release-driven updates, publish a GitHub Release per version.

== Changelog ==

= 3.0.0 =
* Renamed to **BLT M365 WP SSO**.
* New: Self-updating from GitHub via the Plugin Update Checker library, with an Updates tab and optional private-repo token.
* New: Automatic version bumping via a git pre-commit hook and `bin/bump-version.sh`.
* New: Standard "Login with Microsoft 365" button auto-inserted into login pages, plus the `[m365_login]` shortcode.
* New: Allowed email domains restriction.
* New: `blt_m365_sso_user_authenticated` action and `blt_m365_sso_redirect_url` / `blt_m365_sso_redirect_uri` filters.
* Security: Users are now matched on their immutable Microsoft object ID before email, preventing account takeover from email reassignment.
* Fixed: The "Auto-redirect after login" option is now actually honoured (previously a no-op).
* Fixed: Default role is validated against registered roles on save.
* Improved: Friendlier OAuth error handling and optional debug logging when `WP_DEBUG` is enabled.
* Improved: Expanded in-dashboard setup instructions covering credentials, the login button, the shortcode, and updates.

= 2.0.0 =
* Major rewrite with enhanced architecture and security.
* New: Tabbed settings page (Setup Guide, Credentials, Options, Shortcode).
* New: Step-by-step Azure AD setup guide with direct links to the Azure portal.
* New: `[m365_login]` shortcode with customizable text, redirect, CSS class, and logged-in message.
* New: "Add to WordPress Login" toggle and auto-redirect after login.
* New: Auto-create users toggle and configurable default role.
* New: Microsoft four-square logo on the login button.
* New: Clean uninstall via uninstall.php.
* Fixed: redirect_uri now included in both authorization and token exchange requests.
* Fixed: Username collision handling and email validation when creating users.
* Fixed: Profile photo saved via WP_Filesystem.

= 1.0.1 =
* Security: sanitization on settings, unslashed `$_GET` variables, `wp_safe_redirect()`.
* Updated: class prefixes to avoid namespace collisions; tested up to WordPress 6.8.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 3.0.0 =
Renamed to BLT M365 WP SSO. Adds GitHub self-updating, automatic version bumping, an auto-inserted login button, allowed-domain restrictions, and stronger account matching. Recommended for all users.

= 2.0.0 =
Major update with shortcode support, login form toggle, auto-redirect, setup guide, and security fixes.

= 1.0.1 =
Security improvements and compliance fixes.
