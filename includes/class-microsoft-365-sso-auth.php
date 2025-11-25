<?php

class M365_SSO_Auth
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $tenant_id;

    public function init()
    {
        $this->client_id = get_option('m365_client_id');
        $this->client_secret = get_option('m365_client_secret');
        $this->tenant_id = get_option('m365_tenant_id');
        $this->redirect_uri = site_url('/wp-login.php');

        add_action('login_form', array($this, 'add_m365_button'));
        add_action('init', array($this, 'handle_m365_login'));
        add_filter('get_avatar', array($this, 'override_avatar'), 10, 5);
    }

    public function add_m365_button()
    {
?>
		<script>
				document.addEventListener("DOMContentLoaded", function() {
					let loginForm = document.getElementById("loginform");
					if (loginForm) {
						let m365Button = document.createElement("p");
						m365Button.className = "m365-login-container";
						m365Button.innerHTML = '<a href="<?php echo esc_url($this->get_auth_url()); ?>" class="button button-primary m365-login">Log In with Microsoft 365</a>';

						loginForm.appendChild(m365Button);
					}
				});
		</script>
<?php
    }

    private function get_auth_url()
    {
        $base_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/authorize";
        $params = array(
            'client_id' => $this->client_id,
            'response_type' => 'code',
//             'redirect_uri' => $this->redirect_uri,
            'response_mode' => 'query',
            'scope' => 'https://graph.microsoft.com/User.Read',
            'state' => wp_create_nonce('m365-auth')
        );

        return $base_url . '?' . http_build_query($params);
    }
	
	public function get_login_url()
	{
		return $this->get_auth_url();
	}


    public function handle_m365_login()
    {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }

        $state = sanitize_text_field(wp_unslash($_GET['state']));
        if (!wp_verify_nonce($state, 'm365-auth')) {
            wp_die('Invalid authentication request');
        }

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $token = $this->get_token($code);
        if (!$token) {
            wp_die('Error obtaining access token');
        }

        $user_info = $this->get_user_info($token);
        if (!$user_info) {
            wp_die('Error obtaining user information');
        }

        $this->authenticate_user($user_info);
    }

    private function get_token($code)
    {
        $response = wp_remote_post("https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token", array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
//                 'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['access_token']) ? $body['access_token'] : false;
    }

    private function get_user_info($token)
    {
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me', array(
            'headers' => array(
                'Authorization' => "Bearer $token"
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $user_info = json_decode(wp_remote_retrieve_body($response), true);

        $photo_response = wp_remote_get("https://graph.microsoft.com/v1.0/me/photo/\$value", array(
            'headers' => array(
                'Authorization' => "Bearer $token"
            )
        ));

        if (!is_wp_error($photo_response) && wp_remote_retrieve_response_code($photo_response) === 200) {
            $photo_data = wp_remote_retrieve_body($photo_response);
            $user_info['profile_photo'] = base64_encode($photo_data);
        }

        return $user_info;
    }

    private function authenticate_user($user_info)
    {
        $email = $user_info['mail'] ?? $user_info['userPrincipalName'];
        $user = get_user_by('email', $email);

        $given_name = $user_info['givenName'] ?? '';
        $surname = $user_info['surname'] ?? '';

        if (!$user) {
            $username = sanitize_user(current(explode('@', $email)));
            $user_id = wp_create_user($username, wp_generate_password(), $email);

            if (is_wp_error($user_id)) {
                wp_die('Error creating user account');
            }

            $user = get_user_by('id', $user_id);
        }

        $user_data = array(
            'ID' => $user->ID,
            'first_name' => $given_name,
            'last_name' => $surname,
            'display_name' => trim($given_name . ' ' . $surname)
        );

        wp_update_user($user_data);

        if (!empty($user_info['profile_photo'])) {
            $this->save_profile_photo($user->ID, $user_info['profile_photo']);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        $redirect_url = apply_filters('m365_sso_redirect_url', admin_url(), $user);
		wp_safe_redirect($redirect_url);
        exit;
    }

    private function save_profile_photo($user_id, $photo_data)
    {
        if (empty($photo_data)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $filename = 'profile-photo-' . $user_id . '.jpg';
        $filepath = $upload_dir['path'] . '/' . $filename;

        file_put_contents($filepath, base64_decode($photo_data));

        $existing_attachment_id = get_user_meta($user_id, 'm365_sso_profile_photo_id', true);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        if ($existing_attachment_id) {
            wp_update_attachment_metadata($existing_attachment_id, wp_generate_attachment_metadata($existing_attachment_id, $filepath));
            return $existing_attachment_id;
        } else {
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $attach_id = wp_insert_attachment($attachment, $filepath);
            $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
            wp_update_attachment_metadata($attach_id, $attach_data);

            update_user_meta($user_id, 'm365_sso_profile_photo_id', $attach_id);
            return $attach_id;
        }
    }

    public function override_avatar($avatar, $id_or_email, $size, $default, $alt)
    {
        $user = false;

        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', $id_or_email);
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user = get_user_by('id', $id_or_email->user_id);
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
        }

        if ($user) {
            $profile_photo_id = get_user_meta($user->ID, 'm365_sso_profile_photo_id', true);
            if ($profile_photo_id) {
                $profile_photo_url = wp_get_attachment_url($profile_photo_id);
                if ($profile_photo_url) {
                    return "<img src='" . esc_url($profile_photo_url) . "' width='{$size}' height='{$size}' alt='" . esc_attr($alt) . "' class='avatar avatar-{$size} photo' />";
                }
            }
        }

        return $avatar;
    }
}
