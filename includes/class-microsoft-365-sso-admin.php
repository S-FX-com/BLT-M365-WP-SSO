<?php
/**
 * Admin settings page.
 *
 * @package SingleSignOnWith365
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class M365_SSO_Admin {

    /** @var string Current settings tab. */
    private $active_tab = 'setup';

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Register top-level menu page with Microsoft 365 icon.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Single Sign-On with 365', 'single-sign-on-with-365' ),
            __( 'M365 SSO', 'single-sign-on-with-365' ),
            'manage_options',
            'microsoft-365-sso',
            array( $this, 'render_settings_page' ),
            'dashicons-lock',
            80
        );
    }

    /**
     * Enqueue admin CSS only on our settings page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_microsoft-365-sso' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'microsoft-365-sso-admin',
            M365_SSO_PLUGIN_URL . 'css/microsoft-365-sso-admin.css',
            array(),
            M365_SSO_VERSION
        );
    }

    /**
     * Register all plugin settings with sanitization callbacks.
     */
    public function register_settings() {
        // Azure credentials.
        register_setting( 'm365_sso_options', 'm365_client_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'm365_sso_options', 'm365_client_secret', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'm365_sso_options', 'm365_tenant_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Behaviour options.
        register_setting( 'm365_sso_options', 'm365_sso_add_to_login', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( 'm365_sso_options', 'm365_sso_auto_redirect', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( 'm365_sso_options', 'm365_sso_create_users', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( 'm365_sso_options', 'm365_sso_default_role', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'subscriber',
        ) );
        register_setting( 'm365_sso_options', 'm365_sso_button_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Sign in with Microsoft 365',
        ) );
    }

    /**
     * Sanitize a checkbox value to '1' or '0'.
     */
    public function sanitize_checkbox( $value ) {
        return ( '1' === (string) $value ) ? '1' : '0';
    }

    /**
     * Check whether the required Azure credentials are configured.
     */
    private function is_configured() {
        return get_option( 'm365_client_id' )
            && get_option( 'm365_client_secret' )
            && get_option( 'm365_tenant_id' );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $this->active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'setup';

        ?>
        <div class="wrap m365-sso-wrap">
            <h1><?php esc_html_e( 'Single Sign-On with 365', 'single-sign-on-with-365' ); ?></h1>
            <p class="m365-sso-version"><?php echo esc_html( sprintf( __( 'Version %s', 'single-sign-on-with-365' ), M365_SSO_VERSION ) ); ?></p>

            <?php $this->render_status_banner(); ?>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=microsoft-365-sso&tab=setup' ) ); ?>"
                   class="nav-tab <?php echo 'setup' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Setup Guide', 'single-sign-on-with-365' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=microsoft-365-sso&tab=credentials' ) ); ?>"
                   class="nav-tab <?php echo 'credentials' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Azure Credentials', 'single-sign-on-with-365' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=microsoft-365-sso&tab=options' ) ); ?>"
                   class="nav-tab <?php echo 'options' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Options', 'single-sign-on-with-365' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=microsoft-365-sso&tab=shortcode' ) ); ?>"
                   class="nav-tab <?php echo 'shortcode' === $this->active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Shortcode', 'single-sign-on-with-365' ); ?>
                </a>
            </nav>

            <div class="m365-sso-tab-content">
                <?php
                switch ( $this->active_tab ) {
                    case 'credentials':
                        $this->render_credentials_tab();
                        break;
                    case 'options':
                        $this->render_options_tab();
                        break;
                    case 'shortcode':
                        $this->render_shortcode_tab();
                        break;
                    default:
                        $this->render_setup_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Show a status banner indicating whether the plugin is configured.
     */
    private function render_status_banner() {
        if ( $this->is_configured() ) {
            ?>
            <div class="notice notice-success inline m365-sso-notice">
                <p><strong><?php esc_html_e( 'Status: Connected', 'single-sign-on-with-365' ); ?></strong> &mdash;
                <?php esc_html_e( 'Your Azure AD credentials are configured. Microsoft 365 SSO is active.', 'single-sign-on-with-365' ); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="notice notice-warning inline m365-sso-notice">
                <p><strong><?php esc_html_e( 'Status: Not Configured', 'single-sign-on-with-365' ); ?></strong> &mdash;
                <?php esc_html_e( 'Please complete the Setup Guide and enter your Azure AD credentials to enable SSO.', 'single-sign-on-with-365' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Setup Guide tab with step-by-step Azure AD instructions.
     */
    private function render_setup_tab() {
        $redirect_uri = site_url( '/wp-login.php' );
        ?>
        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Azure AD App Registration Guide', 'single-sign-on-with-365' ); ?></h2>
            <p><?php esc_html_e( 'Follow these steps to register an application in Microsoft Azure and connect it to your WordPress site.', 'single-sign-on-with-365' ); ?></p>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 1: Open Azure Portal', 'single-sign-on-with-365' ); ?></h3>
                <p><?php esc_html_e( 'Go to the Microsoft Azure Portal and sign in with your Microsoft 365 administrator account.', 'single-sign-on-with-365' ); ?></p>
                <p><a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                    <?php esc_html_e( 'Open Azure App Registrations &rarr;', 'single-sign-on-with-365' ); ?>
                </a></p>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 2: Register a New Application', 'single-sign-on-with-365' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Click "New registration".', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Enter a name for your application (e.g. "WordPress SSO").', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Under "Supported account types", select "Accounts in this organizational directory only" for single-tenant, or "Accounts in any organizational directory" for multi-tenant.', 'single-sign-on-with-365' ); ?></li>
                    <li>
                        <?php esc_html_e( 'Under "Redirect URI", select "Web" and enter:', 'single-sign-on-with-365' ); ?>
                        <code class="m365-sso-copy-box" id="m365-redirect-uri"><?php echo esc_html( $redirect_uri ); ?></code>
                    </li>
                    <li><?php esc_html_e( 'Click "Register".', 'single-sign-on-with-365' ); ?></li>
                </ol>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 3: Copy Your Client ID and Tenant ID', 'single-sign-on-with-365' ); ?></h3>
                <p><?php esc_html_e( 'After registration, you will see the "Overview" page. Copy the following values:', 'single-sign-on-with-365' ); ?></p>
                <ul>
                    <li><strong><?php esc_html_e( 'Application (client) ID', 'single-sign-on-with-365' ); ?></strong> &rarr; <?php esc_html_e( 'Paste into the "Client ID" field in the Credentials tab.', 'single-sign-on-with-365' ); ?></li>
                    <li><strong><?php esc_html_e( 'Directory (tenant) ID', 'single-sign-on-with-365' ); ?></strong> &rarr; <?php esc_html_e( 'Paste into the "Tenant ID" field in the Credentials tab.', 'single-sign-on-with-365' ); ?></li>
                </ul>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 4: Create a Client Secret', 'single-sign-on-with-365' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'In the left menu, click "Certificates & secrets".', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Under "Client secrets", click "New client secret".', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Add a description (e.g. "WordPress SSO") and choose an expiration period.', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Click "Add", then immediately copy the "Value" (not the "Secret ID").', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Paste this into the "Client Secret" field in the Credentials tab.', 'single-sign-on-with-365' ); ?></li>
                </ol>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'Important: The secret value is only shown once. If you lose it, you will need to create a new secret.', 'single-sign-on-with-365' ); ?></p>
                </div>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 5: Configure API Permissions', 'single-sign-on-with-365' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'In the left menu, click "API permissions".', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Verify that "Microsoft Graph > User.Read" is listed. It is usually added by default.', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'If it is not listed, click "Add a permission" > "Microsoft Graph" > "Delegated permissions" > search for "User.Read" and add it.', 'single-sign-on-with-365' ); ?></li>
                    <li><?php esc_html_e( 'Click "Grant admin consent" if required by your organization.', 'single-sign-on-with-365' ); ?></li>
                </ol>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 6: Enter Credentials', 'single-sign-on-with-365' ); ?></h3>
                <p><?php esc_html_e( 'Go to the Credentials tab and enter the Client ID, Client Secret, and Tenant ID you copied from Azure.', 'single-sign-on-with-365' ); ?></p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=microsoft-365-sso&tab=credentials' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Go to Credentials &rarr;', 'single-sign-on-with-365' ); ?>
                </a></p>
            </div>
        </div>
        <?php
    }

    /**
     * Azure Credentials tab.
     */
    private function render_credentials_tab() {
        $redirect_uri = site_url( '/wp-login.php' );
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'm365_sso_options' ); ?>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'Azure AD Credentials', 'single-sign-on-with-365' ); ?></h2>
                <p><?php esc_html_e( 'Enter the credentials from your Azure AD app registration. See the Setup Guide tab for instructions.', 'single-sign-on-with-365' ); ?></p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="m365_client_id"><?php esc_html_e( 'Application (Client) ID', 'single-sign-on-with-365' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_client_id" name="m365_client_id"
                                   value="<?php echo esc_attr( get_option( 'm365_client_id' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'Found on the Azure app "Overview" page as "Application (client) ID".', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_client_secret"><?php esc_html_e( 'Client Secret', 'single-sign-on-with-365' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="m365_client_secret" name="m365_client_secret"
                                   value="<?php echo esc_attr( get_option( 'm365_client_secret' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'Found under "Certificates & secrets". Copy the secret "Value", not the "Secret ID".', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_tenant_id"><?php esc_html_e( 'Directory (Tenant) ID', 'single-sign-on-with-365' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_tenant_id" name="m365_tenant_id"
                                   value="<?php echo esc_attr( get_option( 'm365_tenant_id' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'Found on the Azure app "Overview" page as "Directory (tenant) ID".', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Redirect URI', 'single-sign-on-with-365' ); ?></th>
                        <td>
                            <code><?php echo esc_html( $redirect_uri ); ?></code>
                            <p class="description"><?php esc_html_e( 'This must match the Redirect URI configured in your Azure app registration.', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Credentials', 'single-sign-on-with-365' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Options tab: behaviour toggles.
     */
    private function render_options_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'm365_sso_options' ); ?>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'Login Integration', 'single-sign-on-with-365' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Add to WordPress Login', 'single-sign-on-with-365' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_add_to_login" value="1"
                                    <?php checked( get_option( 'm365_sso_add_to_login', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'Append the "Sign in with Microsoft 365" button to all WordPress login forms.', 'single-sign-on-with-365' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-redirect after login', 'single-sign-on-with-365' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_auto_redirect" value="1"
                                    <?php checked( get_option( 'm365_sso_auto_redirect', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'When a user accesses a restricted page, redirect them back to that page after SSO login.', 'single-sign-on-with-365' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_sso_button_text"><?php esc_html_e( 'Button Text', 'single-sign-on-with-365' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_sso_button_text" name="m365_sso_button_text"
                                   value="<?php echo esc_attr( get_option( 'm365_sso_button_text', 'Sign in with Microsoft 365' ) ); ?>"
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Text displayed on the SSO login button.', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'User Management', 'single-sign-on-with-365' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-create Users', 'single-sign-on-with-365' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_create_users" value="1"
                                    <?php checked( get_option( 'm365_sso_create_users', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'Automatically create a WordPress account when a new Microsoft 365 user signs in.', 'single-sign-on-with-365' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_sso_default_role"><?php esc_html_e( 'Default Role', 'single-sign-on-with-365' ); ?></label>
                        </th>
                        <td>
                            <select id="m365_sso_default_role" name="m365_sso_default_role">
                                <?php
                                $current_role = get_option( 'm365_sso_default_role', 'subscriber' );
                                wp_dropdown_roles( $current_role );
                                ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'WordPress role assigned to newly created users.', 'single-sign-on-with-365' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Options', 'single-sign-on-with-365' ) ); ?>
            </div>
        </form>
        <?php
    }

    /**
     * Shortcode reference tab.
     */
    private function render_shortcode_tab() {
        ?>
        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Shortcode Usage', 'single-sign-on-with-365' ); ?></h2>
            <p><?php esc_html_e( 'Use the shortcode below to place a Microsoft 365 login button anywhere on your site — pages, posts, widgets, or theme templates.', 'single-sign-on-with-365' ); ?></p>

            <h3><?php esc_html_e( 'Basic Usage', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login]</code>
            <p class="description"><?php esc_html_e( 'Displays a "Sign in with Microsoft 365" button using the default button text from Options.', 'single-sign-on-with-365' ); ?></p>

            <h3><?php esc_html_e( 'Custom Button Text', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login text="Login with your company account"]</code>

            <h3><?php esc_html_e( 'Custom Redirect After Login', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login redirect="/members/dashboard"]</code>
            <p class="description"><?php esc_html_e( 'By default the button redirects to the current page. Use the redirect attribute to override this.', 'single-sign-on-with-365' ); ?></p>

            <h3><?php esc_html_e( 'Custom CSS Class', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login class="my-custom-class"]</code>

            <h3><?php esc_html_e( 'Logged-in Message', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login logged_in_text="You are signed in."]</code>
            <p class="description"><?php esc_html_e( 'Text shown when the user is already logged in. Leave empty to hide the button entirely for logged-in users.', 'single-sign-on-with-365' ); ?></p>

            <h3><?php esc_html_e( 'PHP Template Usage', 'single-sign-on-with-365' ); ?></h3>
            <code class="m365-sso-copy-box">&lt;?php echo do_shortcode( '[m365_login]' ); ?&gt;</code>
        </div>
        <?php
    }
}
