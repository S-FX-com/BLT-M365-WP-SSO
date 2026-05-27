<?php
/**
 * Authentication handler for the Microsoft 365 OAuth2 flow.
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BLT_M365_SSO_Auth {

    /** @var string */
    private $client_id;

    /** @var string */
    private $client_secret;

    /** @var string */
    private $redirect_uri;

    /** @var string */
    private $tenant_id;

    /** @var string Human-readable reason for the most recent token failure. */
    private $last_error = '';

    /** OAuth scopes requested from Microsoft. */
    const SCOPE = 'openid email profile https://graph.microsoft.com/User.Read';

    public function init() {
        $this->client_id     = get_option( 'm365_client_id', '' );
        $this->client_secret = get_option( 'm365_client_secret', '' );
        $this->tenant_id     = get_option( 'm365_tenant_id', '' );
        $this->redirect_uri  = self::get_redirect_uri();

        // Handle the OAuth callback early.
        add_action( 'init', array( $this, 'handle_m365_login' ) );

        // Conditionally add the login button to wp-login.php.
        if ( '1' === get_option( 'm365_sso_add_to_login', '1' ) ) {
            add_action( 'login_form', array( $this, 'render_login_button' ) );
        }

        // Avatar override.
        add_filter( 'get_avatar', array( $this, 'override_avatar' ), 10, 5 );
    }

    /**
     * The OAuth2 redirect URI. Must match the Azure app registration exactly.
     *
     * @return string
     */
    public static function get_redirect_uri() {
        return apply_filters( 'blt_m365_sso_redirect_uri', wp_login_url() );
    }

    /**
     * Whether the plugin has the credentials required to run.
     *
     * @return bool
     */
    public function is_configured() {
        return $this->client_id && $this->client_secret && $this->tenant_id;
    }

    /**
     * Test the saved Azure credentials without an interactive sign-in.
     *
     * Performs a client-credentials token request against the tenant. The only
     * response that means the client failed to authenticate is "invalid_client";
     * other Azure errors (such as app-permission notices for the .default scope)
     * still confirm that the Client ID, Tenant ID, and Client Secret are valid.
     *
     * @return array {ok: bool, message: string, detail?: string}
     */
    public function test_credentials() {
        $client_id     = get_option( 'm365_client_id', '' );
        $client_secret = get_option( 'm365_client_secret', '' );
        $tenant_id     = get_option( 'm365_tenant_id', '' );

        if ( ! $client_id || ! $client_secret || ! $tenant_id ) {
            return array(
                'ok'      => false,
                'message' => __( 'Please enter and save your Client ID, Client Secret, and Tenant ID first.', 'blt-m365-wp-sso' ),
            );
        }

        // 1) Validate the tenant first. The OpenID configuration endpoint is public
        // and returns HTTP 200 only for a valid, reachable tenant. Checking it here
        // means a tenant problem cannot be mistaken for a client/secret problem below.
        $config_url = sprintf(
            'https://login.microsoftonline.com/%s/v2.0/.well-known/openid-configuration',
            rawurlencode( $tenant_id )
        );
        $config = wp_remote_get( $config_url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $config ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'Could not reach Microsoft. Check this server\'s outbound network access.', 'blt-m365-wp-sso' ),
                'detail'  => $config->get_error_message(),
            );
        }

        if ( 200 !== (int) wp_remote_retrieve_response_code( $config ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'The Directory (Tenant) ID was not found or is not valid. Please check it.', 'blt-m365-wp-sso' ),
            );
        }

        // 2) Validate the client and secret with a client-credentials token request.
        $token_url = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            rawurlencode( $tenant_id )
        );

        $response = wp_remote_post(
            $token_url,
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'https://graph.microsoft.com/.default',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'Could not reach Microsoft. Check this server\'s outbound network access.', 'blt-m365-wp-sso' ),
                'detail'  => $response->get_error_message(),
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['access_token'] ) ) {
            return array(
                'ok'      => true,
                'message' => __( 'Success! Microsoft accepted your credentials and issued a token.', 'blt-m365-wp-sso' ),
            );
        }

        $error = isset( $body['error'] ) ? (string) $body['error'] : '';
        $desc  = isset( $body['error_description'] ) ? (string) $body['error_description'] : '';

        // "invalid_client" is the definitive signal that the secret is wrong or expired.
        if ( 'invalid_client' === $error
            || false !== strpos( $desc, 'AADSTS7000215' )
            || false !== strpos( $desc, 'AADSTS7000222' )
            || false !== strpos( $desc, 'AADSTS7000216' ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'The Client Secret is incorrect or has expired. Create a new secret in Azure and save it here.', 'blt-m365-wp-sso' ),
                'detail'  => $this->first_line( $desc ),
            );
        }

        // The application was not found in this (already validated) tenant -> wrong Client ID.
        if ( false !== strpos( $desc, 'AADSTS700016' ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'The Application (Client) ID was not found in this tenant. Please check it.', 'blt-m365-wp-sso' ),
                'detail'  => $this->first_line( $desc ),
            );
        }

        // The tenant is valid and the client authenticated (no client error above), so
        // the credentials are correct. Any remaining message is an app-permission notice
        // for the .default scope, which does not affect interactive Microsoft 365 sign-in.
        if ( $error ) {
            return array(
                'ok'      => true,
                'message' => __( 'Your Client ID, Tenant ID, and Client Secret are valid. Microsoft also returned an app-permission notice, which does not affect Microsoft 365 user sign-in.', 'blt-m365-wp-sso' ),
                'detail'  => $this->first_line( $desc ),
            );
        }

        return array(
            'ok'      => false,
            'message' => __( 'Unexpected response from Microsoft. Please try again.', 'blt-m365-wp-sso' ),
            'detail'  => 'HTTP ' . wp_remote_retrieve_response_code( $response ),
        );
    }

    /**
     * Return the first line of a (possibly multi-line) string, capped in length.
     *
     * @param string $text Text.
     * @return string
     */
    private function first_line( $text ) {
        $text = trim( (string) $text );
        if ( '' === $text ) {
            return '';
        }
        $line = strtok( $text, "\r\n" );
        return ( strlen( $line ) > 300 ) ? substr( $line, 0, 300 ) . '…' : $line;
    }

    /**
     * Whether the auto-redirect-after-login behaviour is enabled.
     *
     * @return bool
     */
    private function auto_redirect_enabled() {
        return '1' === get_option( 'm365_sso_auto_redirect', '1' );
    }

    /**
     * Build the Microsoft OAuth2 authorization URL.
     *
     * @param string $redirect_after Optional URL to return to after login.
     * @return string
     */
    public function get_auth_url( $redirect_after = '' ) {
        if ( ! $this->is_configured() ) {
            return '';
        }

        // Encode a nonce (CSRF guard) and the desired post-login redirect into state.
        $state_data = array(
            'nonce' => wp_create_nonce( 'm365-auth' ),
        );

        if ( $redirect_after && $this->auto_redirect_enabled() ) {
            $state_data['redirect_to'] = $redirect_after;
        }

        $state = base64_encode( wp_json_encode( $state_data ) );

        $base_url = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize',
            rawurlencode( $this->tenant_id )
        );

        $params = array(
            'client_id'     => $this->client_id,
            'response_type' => 'code',
            'redirect_uri'  => $this->redirect_uri,
            'response_mode' => 'query',
            'scope'         => self::SCOPE,
            'state'         => $state,
        );

        return $base_url . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
    }

    /**
     * Render the SSO button on the WordPress login form.
     */
    public function render_login_button() {
        if ( ! $this->is_configured() ) {
            return;
        }

        $redirect_to = '';
        if ( $this->auto_redirect_enabled() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
        }

        $auth_url    = $this->get_auth_url( $redirect_to );
        $button_text = get_option( 'm365_sso_button_text', 'Login with Microsoft 365' );

        ?>
        <div class="m365-sso-separator">
            <span><?php esc_html_e( 'or', 'blt-m365-wp-sso' ); ?></span>
        </div>
        <div class="m365-sso-login-wrap">
            <a href="<?php echo esc_url( $auth_url ); ?>" class="m365-sso-btn">
                <?php echo self::get_logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo esc_html( $button_text ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * The Microsoft four-square logo as inline SVG.
     *
     * @return string
     */
    public static function get_logo_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" class="m365-sso-icon" aria-hidden="true" focusable="false">'
            . '<rect x="1" y="1" width="9" height="9" fill="#f25022"/>'
            . '<rect x="11" y="1" width="9" height="9" fill="#7fba00"/>'
            . '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>'
            . '<rect x="11" y="11" width="9" height="9" fill="#ffb900"/>'
            . '</svg>';
    }

    /**
     * Handle the OAuth2 callback from Microsoft.
     */
    public function handle_m365_login() {
        // Only act on our OAuth callback: a state plus either an auth code or an error.
        if ( ! isset( $_GET['state'] ) || ( ! isset( $_GET['code'] ) && ! isset( $_GET['error'] ) ) ) {
            return;
        }

        // Decode and validate the state parameter.
        $raw_state  = sanitize_text_field( wp_unslash( $_GET['state'] ) );
        $state_data = json_decode( base64_decode( $raw_state ), true );

        if ( ! is_array( $state_data ) || empty( $state_data['nonce'] ) ) {
            $this->fail( __( 'Invalid authentication request. Please try again.', 'blt-m365-wp-sso' ), 403 );
        }

        if ( ! wp_verify_nonce( $state_data['nonce'], 'm365-auth' ) ) {
            $this->fail( __( 'Authentication session expired. Please try again.', 'blt-m365-wp-sso' ), 403 );
        }

        // Surface a provider error (e.g. consent declined) instead of failing silently.
        if ( isset( $_GET['error'] ) ) {
            $this->log( 'OAuth error: ' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
            $this->fail( __( 'Microsoft returned an error during sign-in. Please try again or contact your administrator.', 'blt-m365-wp-sso' ), 400 );
        }

        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $token = $this->exchange_code_for_token( $code );

        if ( ! $token ) {
            $message = $this->last_error
                ? $this->last_error
                : __( 'Unable to obtain an access token from Microsoft. Please verify your Azure AD credentials.', 'blt-m365-wp-sso' );
            $this->fail( $message, 502 );
        }

        $user_info = $this->fetch_user_info( $token );

        if ( ! $user_info || ( empty( $user_info['mail'] ) && empty( $user_info['userPrincipalName'] ) ) ) {
            $this->fail( __( 'Unable to retrieve your Microsoft account information. Please try again.', 'blt-m365-wp-sso' ), 500 );
        }

        // Honour the post-login redirect only when auto-redirect is enabled.
        $redirect_to = '';
        if ( $this->auto_redirect_enabled() && ! empty( $state_data['redirect_to'] ) ) {
            $redirect_to = esc_url_raw( $state_data['redirect_to'] );
        }

        $this->authenticate_user( $user_info, $redirect_to );
    }

    /**
     * Exchange the authorization code for an access token.
     *
     * @param string $code Authorization code.
     * @return string|false Access token or false on failure.
     */
    private function exchange_code_for_token( $code ) {
        $this->last_error = '';

        $token_url = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            rawurlencode( $this->tenant_id )
        );

        $response = wp_remote_post(
            $token_url,
            array(
                'timeout' => 30,
                'body'    => array(
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'code'          => $code,
                    'redirect_uri'  => $this->redirect_uri,
                    'grant_type'    => 'authorization_code',
                    'scope'         => self::SCOPE,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->last_error = __( 'Could not connect to Microsoft. The website server may be blocking outbound connections to login.microsoftonline.com.', 'blt-m365-wp-sso' );
            $this->log( 'Token request connection error: ' . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $detail = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
            $this->last_error = sprintf(
                /* translators: %s: error message returned by Microsoft. */
                __( 'Microsoft rejected the sign-in request: %s', 'blt-m365-wp-sso' ),
                $this->first_line( $detail )
            );
            $this->log( 'Token endpoint returned HTTP ' . $status . ': ' . $detail );
            return false;
        }

        if ( empty( $body['access_token'] ) ) {
            $this->last_error = __( 'Microsoft did not return an access token.', 'blt-m365-wp-sso' );
            return false;
        }

        return $body['access_token'];
    }

    /**
     * Fetch user information from Microsoft Graph.
     *
     * @param string $token Access token.
     * @return array|false
     */
    private function fetch_user_info( $token ) {
        $response = wp_remote_get(
            'https://graph.microsoft.com/v1.0/me',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->log( 'Graph /me request failed: ' . $response->get_error_message() );
            return false;
        }

        $user_info = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $user_info ) || isset( $user_info['error'] ) ) {
            $this->log( 'Graph /me returned an error response.' );
            return false;
        }

        // Attempt to fetch the profile photo (optional and non-fatal).
        if ( '1' === get_option( 'm365_sso_sync_photo', '1' ) ) {
            $photo_response = wp_remote_get(
                'https://graph.microsoft.com/v1.0/me/photo/$value',
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                )
            );

            if ( ! is_wp_error( $photo_response ) && 200 === wp_remote_retrieve_response_code( $photo_response ) ) {
                $photo_data = wp_remote_retrieve_body( $photo_response );
                if ( ! empty( $photo_data ) ) {
                    $user_info['profile_photo'] = base64_encode( $photo_data );
                }
            }
        }

        return $user_info;
    }

    /**
     * Authenticate or create a WordPress user from Microsoft profile data.
     *
     * @param array  $user_info   Microsoft Graph user data.
     * @param string $redirect_to URL to redirect to after login.
     */
    private function authenticate_user( $user_info, $redirect_to = '' ) {
        $email = ! empty( $user_info['mail'] ) ? $user_info['mail'] : $user_info['userPrincipalName'];
        $email = sanitize_email( $email );

        if ( ! is_email( $email ) ) {
            $this->fail( __( 'Invalid email address returned by Microsoft. SSO login failed.', 'blt-m365-wp-sso' ), 400 );
        }

        // Optional restriction to specific email domains.
        if ( ! $this->domain_is_allowed( $email ) ) {
            $this->fail( __( 'Your account is not permitted to sign in to this site. Please contact your administrator.', 'blt-m365-wp-sso' ), 403 );
        }

        // The Microsoft object ID is immutable; prefer it over the (reassignable) email.
        $oid = isset( $user_info['id'] ) ? sanitize_text_field( $user_info['id'] ) : '';

        $given_name = isset( $user_info['givenName'] ) ? sanitize_text_field( $user_info['givenName'] ) : '';
        $surname    = isset( $user_info['surname'] ) ? sanitize_text_field( $user_info['surname'] ) : '';

        $user = $this->find_existing_user( $oid, $email );

        if ( ! $user ) {
            if ( '1' !== get_option( 'm365_sso_create_users', '1' ) ) {
                $this->fail( __( 'No WordPress account exists for this email address and automatic account creation is disabled. Please contact your administrator.', 'blt-m365-wp-sso' ), 403 );
            }

            $user = $this->create_user( $email, $given_name, $surname );
        } else {
            wp_update_user(
                array(
                    'ID'           => $user->ID,
                    'first_name'   => $given_name,
                    'last_name'    => $surname,
                    'display_name' => trim( $given_name . ' ' . $surname ),
                )
            );
        }

        // Record the immutable object ID for future logins.
        if ( $oid ) {
            update_user_meta( $user->ID, 'm365_sso_oid', $oid );
        }

        // Log the user in first, so optional extras can never block sign-in.
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        if ( ! empty( $user_info['profile_photo'] ) && '1' === get_option( 'm365_sso_sync_photo', '1' ) ) {
            $this->save_profile_photo( $user->ID, $user_info['profile_photo'] );
        }

        /**
         * Fires after a user successfully signs in via Microsoft 365 SSO.
         *
         * @param WP_User $user      The authenticated user.
         * @param array   $user_info Raw Microsoft Graph profile data.
         */
        do_action( 'blt_m365_sso_user_authenticated', $user, $user_info );

        if ( empty( $redirect_to ) ) {
            $redirect_to = admin_url();
        }

        /**
         * Filter the post-login redirect destination.
         *
         * @param string  $redirect_to Destination URL.
         * @param WP_User $user        The authenticated user.
         */
        $redirect_to = apply_filters( 'blt_m365_sso_redirect_url', $redirect_to, $user );

        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Find an existing WordPress user by Microsoft object ID, then by email.
     *
     * @param string $oid   Microsoft object ID.
     * @param string $email Email address.
     * @return WP_User|false
     */
    private function find_existing_user( $oid, $email ) {
        if ( $oid ) {
            $matches = get_users(
                array(
                    'meta_key'   => 'm365_sso_oid', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => $oid,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    'number'     => 1,
                )
            );
            if ( ! empty( $matches ) ) {
                return $matches[0];
            }
        }

        $user = get_user_by( 'email', $email );
        return $user ? $user : false;
    }

    /**
     * Create a new WordPress user from Microsoft profile data.
     *
     * @param string $email      Email address.
     * @param string $given_name First name.
     * @param string $surname    Last name.
     * @return WP_User
     */
    private function create_user( $email, $given_name, $surname ) {
        $username = sanitize_user( current( explode( '@', $email ) ), true );

        // Handle username collisions.
        if ( username_exists( $username ) ) {
            $counter = 2;
            while ( username_exists( $username . $counter ) ) {
                $counter++;
            }
            $username = $username . $counter;
        }

        $default_role = get_option( 'm365_sso_default_role', 'subscriber' );

        $user_id = wp_insert_user(
            array(
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password( 24, true, true ),
                'first_name'   => $given_name,
                'last_name'    => $surname,
                'display_name' => trim( $given_name . ' ' . $surname ),
                'role'         => $default_role,
            )
        );

        if ( is_wp_error( $user_id ) ) {
            $this->log( 'User creation failed: ' . $user_id->get_error_message() );
            $this->fail( __( 'Unable to create a WordPress account. Please try again later.', 'blt-m365-wp-sso' ), 500 );
        }

        update_user_meta( $user_id, 'm365_sso_user', '1' );

        return get_user_by( 'id', $user_id );
    }

    /**
     * Whether the email address belongs to an allowed domain.
     *
     * An empty allow-list permits every domain.
     *
     * @param string $email Email address.
     * @return bool
     */
    private function domain_is_allowed( $email ) {
        $allowed = trim( (string) get_option( 'm365_sso_allowed_domains', '' ) );
        if ( '' === $allowed ) {
            return true;
        }

        $domains = preg_split( '/[\s,]+/', strtolower( $allowed ), -1, PREG_SPLIT_NO_EMPTY );
        $host    = strtolower( (string) substr( strrchr( $email, '@' ), 1 ) );

        return in_array( $host, $domains, true );
    }

    /**
     * Save a user's Microsoft profile photo as a plain file.
     *
     * Deliberately avoids the media library and wp_generate_attachment_metadata():
     * generating thumbnail sub-sizes through Imagick/GD during the login request can
     * exceed max_execution_time and break sign-in. The avatar is served at its native
     * size and scaled by the browser, so no server-side resizing is required.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $photo_data Base64-encoded photo data.
     * @return string|false Photo URL or false.
     */
    private function save_profile_photo( $user_id, $photo_data ) {
        if ( empty( $photo_data ) ) {
            return false;
        }

        $decoded = base64_decode( $photo_data );
        if ( empty( $decoded ) ) {
            return false;
        }

        // Cheap validation: reads the image header only, no decode/resize.
        $info = @getimagesizefromstring( $decoded ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( false === $info ) {
            return false;
        }

        $mime = isset( $info['mime'] ) ? $info['mime'] : 'image/jpeg';
        $exts = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
        $ext = isset( $exts[ $mime ] ) ? $exts[ $mime ] : 'jpg';

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            return false;
        }

        $dir = trailingslashit( $uploads['basedir'] ) . 'm365-sso-avatars';
        if ( ! wp_mkdir_p( $dir ) ) {
            return false;
        }

        $filename = 'avatar-' . absint( $user_id ) . '.' . $ext;
        $filepath = trailingslashit( $dir ) . $filename;

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem || ! $wp_filesystem->put_contents( $filepath, $decoded, FS_CHMOD_FILE ) ) {
            return false;
        }

        $url = trailingslashit( $uploads['baseurl'] ) . 'm365-sso-avatars/' . $filename;
        update_user_meta( $user_id, 'm365_sso_profile_photo_url', $url );

        return $url;
    }

    /**
     * Override WordPress avatars with the Microsoft profile photo.
     *
     * @param string $avatar      HTML img tag for the avatar.
     * @param mixed  $id_or_email User ID, email, or WP_Comment.
     * @param int    $size        Avatar size.
     * @param string $default     Default avatar URL.
     * @param string $alt         Alt attribute.
     * @return string
     */
    public function override_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
        $user = false;

        if ( is_numeric( $id_or_email ) ) {
            $user = get_user_by( 'id', absint( $id_or_email ) );
        } elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) && $id_or_email->user_id > 0 ) {
            $user = get_user_by( 'id', absint( $id_or_email->user_id ) );
        } elseif ( is_string( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
        }

        if ( ! $user ) {
            return $avatar;
        }

        $photo_url = get_user_meta( $user->ID, 'm365_sso_profile_photo_url', true );
        if ( ! $photo_url ) {
            return $avatar;
        }

        return sprintf(
            '<img src="%s" width="%d" height="%d" alt="%s" class="avatar avatar-%d photo m365-sso-avatar" />',
            esc_url( $photo_url ),
            absint( $size ),
            absint( $size ),
            esc_attr( $alt ),
            absint( $size )
        );
    }

    /**
     * Write a message to the error log when debugging is enabled.
     *
     * @param string $message Message to record.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BLT M365 WP SSO] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Abort the request with a friendly, escaped error page.
     *
     * @param string $message Human-readable message.
     * @param int    $status  HTTP status code.
     */
    private function fail( $message, $status = 400 ) {
        wp_die(
            esc_html( $message ),
            esc_html__( 'Microsoft 365 SSO Error', 'blt-m365-wp-sso' ),
            array( 'response' => (int) $status )
        );
    }
}
