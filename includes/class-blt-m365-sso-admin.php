<?php
/**
 * Admin settings page.
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BLT_M365_SSO_Admin {

    /**
     * Admin page slug and per-form settings groups.
     *
     * Each tab is a separate form, so each has its own settings group. A shared
     * group would cause options.php to null out the fields that are not present
     * in the submitted form, wiping the other tabs' data.
     */
    const PAGE_SLUG          = 'blt-m365-wp-sso';
    const GROUP_CREDENTIALS  = 'm365_sso_credentials';
    const OPTION_GROUP       = 'm365_sso_options';
    const GROUP_UPDATES      = 'm365_sso_updates';

    /** @var string Current settings tab. */
    private $active_tab = 'setup';

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_blt_m365_sso_test_auth', array( $this, 'ajax_test_auth' ) );
    }

    /**
     * Register the top-level menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'BLT M365 WP SSO', 'blt-m365-wp-sso' ),
            __( 'M365 SSO', 'blt-m365-wp-sso' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' ),
            'dashicons-lock',
            80
        );
    }

    /**
     * Enqueue admin CSS only on our settings page.
     *
     * @param string $hook Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'blt-m365-sso-admin',
            BLT_M365_SSO_PLUGIN_URL . 'css/blt-m365-sso-admin.css',
            array(),
            BLT_M365_SSO_VERSION
        );
        wp_enqueue_script(
            'blt-m365-sso-admin',
            BLT_M365_SSO_PLUGIN_URL . 'js/blt-m365-sso-admin.js',
            array(),
            BLT_M365_SSO_VERSION,
            true
        );
        wp_localize_script(
            'blt-m365-sso-admin',
            'bltM365Sso',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action'  => 'blt_m365_sso_test_auth',
                'nonce'   => wp_create_nonce( 'blt_m365_sso_test_auth' ),
                'testing' => __( 'Testing connection to Microsoft…', 'blt-m365-wp-sso' ),
                'failed'  => __( 'The test could not be completed. Please try again.', 'blt-m365-wp-sso' ),
            )
        );
    }

    /**
     * Register all plugin settings with sanitization callbacks.
     */
    public function register_settings() {
        $text = array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' );

        // Azure credentials.
        register_setting( self::GROUP_CREDENTIALS, 'm365_client_id', $text );
        register_setting( self::GROUP_CREDENTIALS, 'm365_client_secret', $text );
        register_setting( self::GROUP_CREDENTIALS, 'm365_tenant_id', $text );

        // Update source.
        register_setting( self::GROUP_UPDATES, 'm365_github_token', $text );

        // Behaviour options.
        register_setting( self::OPTION_GROUP, 'm365_sso_add_to_login', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( self::OPTION_GROUP, 'm365_sso_auto_redirect', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( self::OPTION_GROUP, 'm365_sso_create_users', array(
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '1',
        ) );
        register_setting( self::OPTION_GROUP, 'm365_sso_default_role', array(
            'sanitize_callback' => array( $this, 'sanitize_role' ),
            'default'           => 'subscriber',
        ) );
        register_setting( self::OPTION_GROUP, 'm365_sso_button_text', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Login with Microsoft 365',
        ) );
        register_setting( self::OPTION_GROUP, 'm365_sso_allowed_domains', array(
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ) );
    }

    /**
     * Sanitize a checkbox value to '1' or '0'.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_checkbox( $value ) {
        return ( '1' === (string) $value ) ? '1' : '0';
    }

    /**
     * Sanitize a role slug against the list of registered roles.
     *
     * @param string $value Submitted role.
     * @return string
     */
    public function sanitize_role( $value ) {
        $value = sanitize_text_field( $value );
        $roles = array_keys( get_editable_roles() );
        return in_array( $value, $roles, true ) ? $value : 'subscriber';
    }

    /**
     * AJAX handler: test the saved Azure credentials against Microsoft.
     */
    public function ajax_test_auth() {
        check_ajax_referer( 'blt_m365_sso_test_auth', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'blt-m365-wp-sso' ) ), 403 );
        }

        $result  = ( new BLT_M365_SSO_Auth() )->test_credentials();
        $payload = array(
            'message' => $result['message'],
            'detail'  => isset( $result['detail'] ) ? $result['detail'] : '',
        );

        if ( ! empty( $result['ok'] ) ) {
            wp_send_json_success( $payload );
        }
        wp_send_json_error( $payload );
    }

    /**
     * Whether the required Azure credentials are configured.
     *
     * @return bool
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

        $tabs = array(
            'setup'       => __( 'Setup Guide', 'blt-m365-wp-sso' ),
            'credentials' => __( 'Azure Credentials', 'blt-m365-wp-sso' ),
            'options'     => __( 'Options', 'blt-m365-wp-sso' ),
            'shortcode'   => __( 'Shortcode', 'blt-m365-wp-sso' ),
            'updates'     => __( 'Updates', 'blt-m365-wp-sso' ),
        );
        ?>
        <div class="wrap m365-sso-wrap">
            <h1><?php esc_html_e( 'BLT M365 WP SSO', 'blt-m365-wp-sso' ); ?></h1>
            <p class="m365-sso-version">
                <?php
                /* translators: %s: plugin version number. */
                echo esc_html( sprintf( __( 'Version %s', 'blt-m365-wp-sso' ), BLT_M365_SSO_VERSION ) );
                ?>
            </p>

            <?php $this->render_status_banner(); ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( $this->tab_url( $slug ) ); ?>"
                       class="nav-tab <?php echo $slug === $this->active_tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
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
                    case 'updates':
                        $this->render_updates_tab();
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
     * Build a URL for a settings tab.
     *
     * @param string $tab Tab slug.
     * @return string
     */
    private function tab_url( $tab ) {
        return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab );
    }

    /**
     * Show a status banner indicating whether the plugin is configured.
     */
    private function render_status_banner() {
        if ( $this->is_configured() ) {
            ?>
            <div class="notice notice-success inline m365-sso-notice">
                <p><strong><?php esc_html_e( 'Status: Connected', 'blt-m365-wp-sso' ); ?></strong> &mdash;
                <?php esc_html_e( 'Your Azure AD credentials are configured. Microsoft 365 SSO is active.', 'blt-m365-wp-sso' ); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="notice notice-warning inline m365-sso-notice">
                <p><strong><?php esc_html_e( 'Status: Not Configured', 'blt-m365-wp-sso' ); ?></strong> &mdash;
                <?php esc_html_e( 'Please complete the Setup Guide and enter your Azure AD credentials to enable SSO.', 'blt-m365-wp-sso' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Setup Guide tab with step-by-step Azure AD instructions.
     */
    private function render_setup_tab() {
        $redirect_uri = BLT_M365_SSO_Auth::get_redirect_uri();
        ?>
        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Getting Started', 'blt-m365-wp-sso' ); ?></h2>
            <p><?php esc_html_e( 'BLT M365 WP SSO lets your users sign in to WordPress with their Microsoft 365 / Entra ID (Azure AD) account. Setup takes about five minutes:', 'blt-m365-wp-sso' ); ?></p>
            <ol class="m365-sso-overview">
                <li><?php esc_html_e( 'Register an application in the Azure portal (Steps 1-5 below).', 'blt-m365-wp-sso' ); ?></li>
                <li><?php esc_html_e( 'Paste the Client ID, Client Secret, and Tenant ID into the Azure Credentials tab.', 'blt-m365-wp-sso' ); ?></li>
                <li><?php esc_html_e( 'Choose your login behaviour in the Options tab (the login button is added automatically by default).', 'blt-m365-wp-sso' ); ?></li>
                <li><?php esc_html_e( 'Optionally drop the [m365_login] shortcode anywhere on your site.', 'blt-m365-wp-sso' ); ?></li>
            </ol>
        </div>

        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Azure AD App Registration Guide', 'blt-m365-wp-sso' ); ?></h2>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 1: Open the Azure Portal', 'blt-m365-wp-sso' ); ?></h3>
                <p><?php esc_html_e( 'Go to the Microsoft Entra admin center / Azure Portal and sign in with an administrator account.', 'blt-m365-wp-sso' ); ?></p>
                <p><a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                    <?php esc_html_e( 'Open Azure App Registrations', 'blt-m365-wp-sso' ); ?>
                </a></p>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 2: Register a New Application', 'blt-m365-wp-sso' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Click "New registration".', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Enter a name (e.g. "WordPress SSO").', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Under "Supported account types", choose "Accounts in this organizational directory only" for single-tenant, or a multi-tenant option if needed.', 'blt-m365-wp-sso' ); ?></li>
                    <li>
                        <?php esc_html_e( 'Under "Redirect URI", select "Web" and enter exactly:', 'blt-m365-wp-sso' ); ?>
                        <code class="m365-sso-copy-box"><?php echo esc_html( $redirect_uri ); ?></code>
                    </li>
                    <li><?php esc_html_e( 'Click "Register".', 'blt-m365-wp-sso' ); ?></li>
                </ol>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 3: Copy the Client ID and Tenant ID', 'blt-m365-wp-sso' ); ?></h3>
                <p><?php esc_html_e( 'On the application "Overview" page, copy these values:', 'blt-m365-wp-sso' ); ?></p>
                <ul>
                    <li><strong><?php esc_html_e( 'Application (client) ID', 'blt-m365-wp-sso' ); ?></strong> &rarr; <?php esc_html_e( 'the "Client ID" field on the Credentials tab.', 'blt-m365-wp-sso' ); ?></li>
                    <li><strong><?php esc_html_e( 'Directory (tenant) ID', 'blt-m365-wp-sso' ); ?></strong> &rarr; <?php esc_html_e( 'the "Tenant ID" field on the Credentials tab.', 'blt-m365-wp-sso' ); ?></li>
                </ul>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 4: Create a Client Secret', 'blt-m365-wp-sso' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'In the left menu, click "Certificates & secrets".', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Under "Client secrets", click "New client secret".', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Add a description and choose an expiration period.', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Click "Add", then immediately copy the secret "Value" (not the "Secret ID").', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Paste it into the "Client Secret" field on the Credentials tab.', 'blt-m365-wp-sso' ); ?></li>
                </ol>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'Important: the secret value is shown only once. Set a calendar reminder before it expires, as sign-in will stop working once it does.', 'blt-m365-wp-sso' ); ?></p>
                </div>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 5: Verify API Permissions', 'blt-m365-wp-sso' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'In the left menu, click "API permissions".', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Confirm that "Microsoft Graph > User.Read" (delegated) is listed. It is added by default.', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'If missing, click "Add a permission" > "Microsoft Graph" > "Delegated permissions", search for "User.Read", and add it.', 'blt-m365-wp-sso' ); ?></li>
                    <li><?php esc_html_e( 'Click "Grant admin consent" if your organization requires it.', 'blt-m365-wp-sso' ); ?></li>
                </ol>
            </div>

            <div class="m365-sso-step">
                <h3><?php esc_html_e( 'Step 6: Enter Your Credentials', 'blt-m365-wp-sso' ); ?></h3>
                <p><a href="<?php echo esc_url( $this->tab_url( 'credentials' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Go to Azure Credentials', 'blt-m365-wp-sso' ); ?>
                </a></p>
            </div>
        </div>

        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Adding the Login Button', 'blt-m365-wp-sso' ); ?></h2>
            <p><?php esc_html_e( 'There are two ways to show the "Login with Microsoft 365" button:', 'blt-m365-wp-sso' ); ?></p>
            <ul class="m365-sso-bullets">
                <li>
                    <strong><?php esc_html_e( 'On the WordPress login screen (automatic):', 'blt-m365-wp-sso' ); ?></strong>
                    <?php esc_html_e( 'enabled by default. The button is appended to the wp-login.php form. Toggle it on the Options tab.', 'blt-m365-wp-sso' ); ?>
                </li>
                <li>
                    <strong><?php esc_html_e( 'Anywhere else (shortcode):', 'blt-m365-wp-sso' ); ?></strong>
                    <?php esc_html_e( 'place', 'blt-m365-wp-sso' ); ?>
                    <code class="m365-sso-copy-box">[m365_login]</code>
                    <?php esc_html_e( 'on any page, post, or widget. See the Shortcode tab for all options.', 'blt-m365-wp-sso' ); ?>
                </li>
            </ul>
            <p>
                <a href="<?php echo esc_url( $this->tab_url( 'options' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Options', 'blt-m365-wp-sso' ); ?></a>
                <a href="<?php echo esc_url( $this->tab_url( 'shortcode' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Shortcode', 'blt-m365-wp-sso' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Azure Credentials tab.
     */
    private function render_credentials_tab() {
        $redirect_uri = BLT_M365_SSO_Auth::get_redirect_uri();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::GROUP_CREDENTIALS ); ?>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'Azure AD Credentials', 'blt-m365-wp-sso' ); ?></h2>
                <p><?php esc_html_e( 'Enter the credentials from your Azure AD app registration. See the Setup Guide tab for instructions.', 'blt-m365-wp-sso' ); ?></p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="m365_client_id"><?php esc_html_e( 'Application (Client) ID', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_client_id" name="m365_client_id"
                                   value="<?php echo esc_attr( get_option( 'm365_client_id' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'From the Azure app "Overview" page: "Application (client) ID".', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_client_secret"><?php esc_html_e( 'Client Secret', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <input type="password" id="m365_client_secret" name="m365_client_secret"
                                   value="<?php echo esc_attr( get_option( 'm365_client_secret' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'From "Certificates & secrets". Copy the secret "Value", not the "Secret ID".', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_tenant_id"><?php esc_html_e( 'Directory (Tenant) ID', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_tenant_id" name="m365_tenant_id"
                                   value="<?php echo esc_attr( get_option( 'm365_tenant_id' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e( 'From the Azure app "Overview" page: "Directory (tenant) ID".', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Redirect URI', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <code class="m365-sso-copy-box"><?php echo esc_html( $redirect_uri ); ?></code>
                            <p class="description"><?php esc_html_e( 'This must match the Redirect URI configured in your Azure app registration exactly.', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Credentials', 'blt-m365-wp-sso' ) ); ?>
            </div>
        </form>

        <?php if ( $this->is_configured() ) : ?>
        <div class="m365-sso-card">
            <h2><?php esc_html_e( 'Test Authentication', 'blt-m365-wp-sso' ); ?></h2>
            <p><?php esc_html_e( 'Verify that your saved credentials are accepted by Microsoft. This runs a server-side token request against your tenant and changes nothing.', 'blt-m365-wp-sso' ); ?></p>
            <p>
                <button type="button" class="button button-secondary" id="blt-m365-sso-test-btn"><?php esc_html_e( 'Test Authentication', 'blt-m365-wp-sso' ); ?></button>
                <span class="spinner" id="blt-m365-sso-test-spinner"></span>
            </p>
            <div id="blt-m365-sso-test-result" class="m365-sso-test-result" role="status" aria-live="polite"></div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Options tab: behaviour toggles.
     */
    private function render_options_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::OPTION_GROUP ); ?>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'Login Integration', 'blt-m365-wp-sso' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Add to WordPress Login', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_add_to_login" value="1"
                                    <?php checked( get_option( 'm365_sso_add_to_login', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'Append the "Login with Microsoft 365" button to all WordPress login forms.', 'blt-m365-wp-sso' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-redirect after login', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_auto_redirect" value="1"
                                    <?php checked( get_option( 'm365_sso_auto_redirect', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'When a user is sent to the login page from a restricted page, return them to that page after signing in.', 'blt-m365-wp-sso' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_sso_button_text"><?php esc_html_e( 'Button Text', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="m365_sso_button_text" name="m365_sso_button_text"
                                   value="<?php echo esc_attr( get_option( 'm365_sso_button_text', 'Login with Microsoft 365' ) ); ?>"
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Text displayed on the SSO login button.', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'User Management', 'blt-m365-wp-sso' ); ?></h2>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-create Users', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="m365_sso_create_users" value="1"
                                    <?php checked( get_option( 'm365_sso_create_users', '1' ), '1' ); ?> />
                                <?php esc_html_e( 'Automatically create a WordPress account when a new Microsoft 365 user signs in.', 'blt-m365-wp-sso' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_sso_default_role"><?php esc_html_e( 'Default Role', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <select id="m365_sso_default_role" name="m365_sso_default_role">
                                <?php wp_dropdown_roles( get_option( 'm365_sso_default_role', 'subscriber' ) ); ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'WordPress role assigned to newly created users.', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_sso_allowed_domains"><?php esc_html_e( 'Allowed Email Domains', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <textarea id="m365_sso_allowed_domains" name="m365_sso_allowed_domains"
                                      rows="3" class="large-text code"
                                      placeholder="example.com, contoso.com"><?php echo esc_textarea( get_option( 'm365_sso_allowed_domains', '' ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Optional. Restrict sign-in to these email domains (comma or space separated). Leave empty to allow any account returned by your Azure app.', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Options', 'blt-m365-wp-sso' ) ); ?>
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
            <h2><?php esc_html_e( 'Shortcode Usage', 'blt-m365-wp-sso' ); ?></h2>
            <p><?php esc_html_e( 'Use the shortcode below to place a Microsoft 365 login button anywhere - pages, posts, widgets, or theme templates.', 'blt-m365-wp-sso' ); ?></p>

            <h3><?php esc_html_e( 'Basic Usage', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login]</code>
            <p class="description"><?php esc_html_e( 'Displays a login button using the default button text from the Options tab.', 'blt-m365-wp-sso' ); ?></p>

            <h3><?php esc_html_e( 'Custom Button Text', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login text="Login with your company account"]</code>

            <h3><?php esc_html_e( 'Custom Redirect After Login', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login redirect="/members/dashboard"]</code>
            <p class="description"><?php esc_html_e( 'By default the button returns to the current page. Use the redirect attribute to override this.', 'blt-m365-wp-sso' ); ?></p>

            <h3><?php esc_html_e( 'Custom CSS Class', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login class="my-custom-class"]</code>

            <h3><?php esc_html_e( 'Logged-in Message', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">[m365_login logged_in_text="You are signed in."]</code>
            <p class="description"><?php esc_html_e( 'Text shown when the user is already logged in. Leave empty to hide the button for logged-in users.', 'blt-m365-wp-sso' ); ?></p>

            <h3><?php esc_html_e( 'PHP Template Usage', 'blt-m365-wp-sso' ); ?></h3>
            <code class="m365-sso-copy-box">&lt;?php echo do_shortcode( '[m365_login]' ); ?&gt;</code>
        </div>
        <?php
    }

    /**
     * Updates tab: GitHub update source and developer notes.
     */
    private function render_updates_tab() {
        $updater       = new BLT_M365_SSO_Updater();
        $token_constant = $updater->token_is_constant();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( self::GROUP_UPDATES ); ?>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'Automatic Updates', 'blt-m365-wp-sso' ); ?></h2>
                <p><?php esc_html_e( 'This plugin checks GitHub for new versions and shows updates on the WordPress Plugins screen, just like plugins from WordPress.org. Updates are powered by the Plugin Update Checker library.', 'blt-m365-wp-sso' ); ?></p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Installed Version', 'blt-m365-wp-sso' ); ?></th>
                        <td><code><?php echo esc_html( BLT_M365_SSO_VERSION ); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Update Source', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <code><?php echo esc_html( $updater->get_repo() ); ?></code>
                            <p class="description">
                                <?php
                                /* translators: %s: git branch name. */
                                echo esc_html( sprintf( __( 'Tracking the "%s" branch.', 'blt-m365-wp-sso' ), $updater->get_active_branch() ) );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="m365_github_token"><?php esc_html_e( 'GitHub Access Token', 'blt-m365-wp-sso' ); ?></label>
                        </th>
                        <td>
                            <?php if ( $token_constant ) : ?>
                                <p class="description"><?php esc_html_e( 'A token is defined via the BLT_M365_SSO_GITHUB_TOKEN constant in wp-config.php and will be used for update checks.', 'blt-m365-wp-sso' ); ?></p>
                            <?php else : ?>
                                <input type="password" id="m365_github_token" name="m365_github_token"
                                       value="<?php echo esc_attr( get_option( 'm365_github_token', '' ) ); ?>"
                                       class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'Optional. Only required if the GitHub repository is private. Use a fine-grained token with read-only "Contents" access. For better security, you may instead define BLT_M365_SSO_GITHUB_TOKEN in wp-config.php.', 'blt-m365-wp-sso' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Check for Updates', 'blt-m365-wp-sso' ); ?></th>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Open Plugins screen', 'blt-m365-wp-sso' ); ?></a>
                            <p class="description"><?php esc_html_e( 'WordPress checks for updates automatically. To check immediately, use the "Check for updates" link next to this plugin on the Plugins screen.', 'blt-m365-wp-sso' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php
                if ( ! $token_constant ) {
                    submit_button( __( 'Save Update Settings', 'blt-m365-wp-sso' ) );
                }
                ?>
            </div>

            <div class="m365-sso-card">
                <h2><?php esc_html_e( 'For Developers: Automatic Version Bumping', 'blt-m365-wp-sso' ); ?></h2>
                <p><?php esc_html_e( 'The repository ships with a git pre-commit hook that bumps the plugin version on every commit, so each push to the tracked branch is offered to sites as an update. To enable it once per clone:', 'blt-m365-wp-sso' ); ?></p>
                <code class="m365-sso-copy-box">git config core.hooksPath .githooks</code>
                <p class="description">
                    <?php esc_html_e( 'The hook runs bin/bump-version.sh (patch level by default) and keeps the Version header, the BLT_M365_SSO_VERSION constant, and readme.txt in sync. Run "bash bin/bump-version.sh minor" or "major" for larger releases. Set BLT_M365_SSO_SKIP_BUMP=1 to skip a single commit.', 'blt-m365-wp-sso' ); ?>
                </p>
                <p class="description">
                    <?php esc_html_e( 'Note: while tracking the "main" branch, the updater prefers GitHub Releases or tags if any exist, and otherwise uses the Version header on the branch. For commit-driven updates, do not publish Releases/tags; to use release-driven updates, publish a GitHub Release for each version.', 'blt-m365-wp-sso' ); ?>
                </p>
            </div>
        </form>
        <?php
    }
}
