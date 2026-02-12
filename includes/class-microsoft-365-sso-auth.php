<?php
/**
 * Authentication handler for Microsoft 365 OAuth2 flow.
 *
 * @package SingleSignOnWith365
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class M365_SSO_Auth {

    /** @var string */
    private $client_id;

    /** @var string */
    private $client_secret;

    /** @var string */
    private $redirect_uri;

    /** @var string */
    private $tenant_id;

    public function init() {
        $this->client_id     = get_option( 'm365_client_id', '' );
        $this->client_secret = get_option( 'm365_client_secret', '' );
        $this->tenant_id     = get_option( 'm365_tenant_id', '' );
        $this->redirect_uri  = site_url( '/wp-login.php' );

        // Handle the OAuth callback early.
        add_action( 'init', array( $this, 'handle_m365_login' ) );

        // Conditionally add the login button to wp-login.php.
        if ( '1' === get_option( 'm365_sso_add_to_login', '1' ) ) {
            add_action( 'login_form', array( $this, 'render_login_button' ) );
        }

        // Avatar override.
        add_filter( 'get_avatar', array( $this, 'override_avatar' ), 10, 5 );

        // Capture the redirect_to parameter from login pages for auto-redirect.
        add_filter( 'login_redirect', array( $this, 'capture_login_redirect' ), 5, 3 );
    }

    /**
     * Check whether the plugin has valid credentials.
     */
    public function is_configured() {
        return $this->client_id && $this->client_secret && $this->tenant_id;
    }

    /**
     * Build the Microsoft OAuth2 authorization URL.
     *
     * @param string $redirect_after Optional URL to redirect to after login.
     * @return string
     */
    public function get_auth_url( $redirect_after = '' ) {
        if ( ! $this->is_configured() ) {
            return '';
        }

        // Encode the nonce and the desired post-login redirect into the state parameter.
        $nonce = wp_create_nonce( 'm365-auth' );
        $state_data = array(
            'nonce' => $nonce,
        );

        if ( $redirect_after ) {
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
            'scope'         => 'openid email profile https://graph.microsoft.com/User.Read',
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

        // Pick up redirect_to from the login form if present.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
        $auth_url    = $this->get_auth_url( $redirect_to );
        $button_text = esc_html( get_option( 'm365_sso_button_text', 'Sign in with Microsoft 365' ) );

        ?>
        <div class="m365-sso-separator">
            <span><?php esc_html_e( 'or', 'single-sign-on-with-365' ); ?></span>
        </div>
        <div class="m365-sso-login-wrap">
            <a href="<?php echo esc_url( $auth_url ); ?>" class="m365-sso-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" class="m365-sso-icon" aria-hidden="true">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                <?php echo $button_text; ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handle the OAuth2 callback from Microsoft.
     */
    public function handle_m365_login() {
        if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
            return;
        }

        // Decode and validate the state parameter.
        $raw_state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
        $state_data = json_decode( base64_decode( $raw_state ), true );

        if ( ! is_array( $state_data ) || empty( $state_data['nonce'] ) ) {
            wp_die(
                esc_html__( 'Invalid authentication request. Please try again.', 'single-sign-on-with-365' ),
                esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                array( 'response' => 403 )
            );
        }

        if ( ! wp_verify_nonce( $state_data['nonce'], 'm365-auth' ) ) {
            wp_die(
                esc_html__( 'Authentication session expired. Please try again.', 'single-sign-on-with-365' ),
                esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                array( 'response' => 403 )
            );
        }

        $code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $token = $this->exchange_code_for_token( $code );

        if ( ! $token ) {
            wp_die(
                esc_html__( 'Unable to obtain an access token from Microsoft. Please verify your Azure AD credentials.', 'single-sign-on-with-365' ),
                esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                array( 'response' => 500 )
            );
        }

        $user_info = $this->fetch_user_info( $token );

        if ( ! $user_info || empty( $user_info['mail'] ) && empty( $user_info['userPrincipalName'] ) ) {
            wp_die(
                esc_html__( 'Unable to retrieve your Microsoft account information. Please try again.', 'single-sign-on-with-365' ),
                esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                array( 'response' => 500 )
            );
        }

        // Determine post-login redirect URL.
        $redirect_to = '';
        if ( ! empty( $state_data['redirect_to'] ) ) {
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
        $token_url = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            rawurlencode( $this->tenant_id )
        );

        $response = wp_remote_post( $token_url, array(
            'timeout' => 30,
            'body'    => array(
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'redirect_uri'  => $this->redirect_uri,
                'grant_type'    => 'authorization_code',
                'scope'         => 'openid email profile https://graph.microsoft.com/User.Read',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['access_token'] ) ? $body['access_token'] : false;
    }

    /**
     * Fetch user information from Microsoft Graph.
     *
     * @param string $token Access token.
     * @return array|false
     */
    private function fetch_user_info( $token ) {
        $response = wp_remote_get( 'https://graph.microsoft.com/v1.0/me', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $user_info = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $user_info ) || isset( $user_info['error'] ) ) {
            return false;
        }

        // Attempt to fetch profile photo.
        $photo_response = wp_remote_get( 'https://graph.microsoft.com/v1.0/me/photo/$value', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        ) );

        if ( ! is_wp_error( $photo_response ) && 200 === wp_remote_retrieve_response_code( $photo_response ) ) {
            $photo_data = wp_remote_retrieve_body( $photo_response );
            if ( ! empty( $photo_data ) ) {
                $user_info['profile_photo'] = base64_encode( $photo_data );
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
            wp_die(
                esc_html__( 'Invalid email address returned by Microsoft. SSO login failed.', 'single-sign-on-with-365' ),
                esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                array( 'response' => 400 )
            );
        }

        $user = get_user_by( 'email', $email );

        $given_name = isset( $user_info['givenName'] ) ? sanitize_text_field( $user_info['givenName'] ) : '';
        $surname    = isset( $user_info['surname'] ) ? sanitize_text_field( $user_info['surname'] ) : '';

        if ( ! $user ) {
            // Check whether auto-creation is enabled.
            if ( '1' !== get_option( 'm365_sso_create_users', '1' ) ) {
                wp_die(
                    esc_html__( 'No WordPress account exists for this email address and automatic account creation is disabled. Please contact your administrator.', 'single-sign-on-with-365' ),
                    esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                    array( 'response' => 403 )
                );
            }

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

            $user_id = wp_insert_user( array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => wp_generate_password( 24, true, true ),
                'first_name' => $given_name,
                'last_name'  => $surname,
                'display_name' => trim( $given_name . ' ' . $surname ),
                'role'       => $default_role,
            ) );

            if ( is_wp_error( $user_id ) ) {
                wp_die(
                    esc_html__( 'Unable to create a WordPress account. Please try again later.', 'single-sign-on-with-365' ),
                    esc_html__( 'SSO Error', 'single-sign-on-with-365' ),
                    array( 'response' => 500 )
                );
            }

            $user = get_user_by( 'id', $user_id );

            // Flag user as SSO-created.
            update_user_meta( $user_id, 'm365_sso_user', '1' );
        } else {
            // Update existing user profile from Microsoft data.
            wp_update_user( array(
                'ID'           => $user->ID,
                'first_name'   => $given_name,
                'last_name'    => $surname,
                'display_name' => trim( $given_name . ' ' . $surname ),
            ) );
        }

        // Save profile photo.
        if ( ! empty( $user_info['profile_photo'] ) ) {
            $this->save_profile_photo( $user->ID, $user_info['profile_photo'] );
        }

        // Log the user in.
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        /**
         * Determine the redirect URL.
         * Priority: state redirect_to > filter > admin_url.
         */
        if ( empty( $redirect_to ) ) {
            $redirect_to = admin_url();
        }

        $redirect_to = apply_filters( 'm365_sso_redirect_url', $redirect_to, $user );

        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Save a user's Microsoft profile photo to the media library.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $photo_data Base64-encoded photo data.
     * @return int|false Attachment ID or false.
     */
    private function save_profile_photo( $user_id, $photo_data ) {
        if ( empty( $photo_data ) ) {
            return false;
        }

        $decoded = base64_decode( $photo_data );
        if ( empty( $decoded ) ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'm365-profile-' . absint( $user_id ) . '.jpg';
        $filepath   = trailingslashit( $upload_dir['path'] ) . $filename;

        // Use WP_Filesystem for writing.
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $wp_filesystem->put_contents( $filepath, $decoded, FS_CHMOD_FILE );

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $existing_id = get_user_meta( $user_id, 'm365_sso_profile_photo_id', true );

        if ( $existing_id ) {
            wp_update_attachment_metadata(
                $existing_id,
                wp_generate_attachment_metadata( $existing_id, $filepath )
            );
            return (int) $existing_id;
        }

        $attachment = array(
            'post_mime_type' => 'image/jpeg',
            'post_title'    => sanitize_file_name( $filename ),
            'post_content'  => '',
            'post_status'   => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $filepath );
        if ( ! is_wp_error( $attach_id ) ) {
            wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $filepath ) );
            update_user_meta( $user_id, 'm365_sso_profile_photo_id', $attach_id );
            return $attach_id;
        }

        return false;
    }

    /**
     * Override WordPress avatars with Microsoft profile photo.
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

        $photo_id = get_user_meta( $user->ID, 'm365_sso_profile_photo_id', true );
        if ( ! $photo_id ) {
            return $avatar;
        }

        $photo_url = wp_get_attachment_url( $photo_id );
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
     * Preserve WordPress redirect_to parameter when SSO is triggered from a restricted page.
     *
     * @param string  $redirect_to           Requested redirect destination.
     * @param string  $requested_redirect_to The requested redirect destination passed as a parameter.
     * @param WP_User $user                  WP_User object if the user is authenticated.
     * @return string
     */
    public function capture_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        return $redirect_to;
    }
}
