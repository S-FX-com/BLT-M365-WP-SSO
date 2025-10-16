<?php

class WP_M365_SSO_Admin
{
    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_options_page(
            'WP M365 SSO Settings',
            'WP M365 SSO',
            'manage_options',
            'microsoft-365-sso',
            array($this, 'settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('m365_sso_options', 'm365_client_id');
        register_setting('m365_sso_options', 'm365_client_secret');
        register_setting('m365_sso_options', 'm365_tenant_id');
    }

    public function settings_page()
    {
?>
        <div class="wrap">
            <h2>WP M365 SSO Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('m365_sso_options');
                do_settings_sections('m365_sso_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th>Client ID</th>
                        <td>
                            <input type="text" name="m365_client_id"
                                value="<?php echo esc_attr(get_option('m365_client_id')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Client Secret</th>
                        <td>
                            <input type="password" name="m365_client_secret"
                                value="<?php echo esc_attr(get_option('m365_client_secret')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Tenant ID</th>
                        <td>
                            <input type="text" name="m365_tenant_id"
                                value="<?php echo esc_attr(get_option('m365_tenant_id')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }
}
