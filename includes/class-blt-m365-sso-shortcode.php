<?php
/**
 * Shortcode handler for [m365_login].
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BLT_M365_SSO_Shortcode {

    /** @var BLT_M365_SSO_Auth */
    private $auth;

    /**
     * @param BLT_M365_SSO_Auth $auth Auth instance.
     */
    public function __construct( BLT_M365_SSO_Auth $auth ) {
        $this->auth = $auth;
    }

    public function init() {
        add_shortcode( 'm365_login', array( $this, 'render' ) );
    }

    /**
     * Render the [m365_login] shortcode.
     *
     * Attributes:
     *   text           - Button label (default: option value).
     *   redirect       - URL to redirect to after login (default: current page).
     *   class          - Additional CSS class(es) for the button.
     *   logged_in_text - Message shown to logged-in users (empty = hide).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render( $atts ) {
        if ( ! $this->auth->is_configured() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'text'           => get_option( 'm365_sso_button_text', 'Login with Microsoft 365' ),
                'redirect'       => '',
                'class'          => '',
                'logged_in_text' => '',
            ),
            $atts,
            'm365_login'
        );

        // If the user is already logged in, show an optional message or nothing.
        if ( is_user_logged_in() ) {
            if ( ! empty( $atts['logged_in_text'] ) ) {
                return '<p class="m365-sso-logged-in">' . esc_html( $atts['logged_in_text'] ) . '</p>';
            }
            return '';
        }

        // Determine the redirect URL: shortcode attribute first, then the current page.
        $redirect_after = $atts['redirect'];
        if ( empty( $redirect_after ) ) {
            global $wp;
            if ( isset( $wp->request ) ) {
                $redirect_after = home_url( $wp->request );
            }
        }

        $auth_url = $this->auth->get_auth_url( $redirect_after );
        if ( empty( $auth_url ) ) {
            return '';
        }

        $extra_class = $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';

        $html  = '<div class="m365-sso-shortcode-wrap">';
        $html .= '<a href="' . esc_url( $auth_url ) . '" class="m365-sso-btn' . $extra_class . '">';
        $html .= BLT_M365_SSO_Auth::get_logo_svg();
        $html .= ' ' . esc_html( $atts['text'] );
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }
}
