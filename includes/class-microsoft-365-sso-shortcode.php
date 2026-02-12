<?php
/**
 * Shortcode handler for [m365_login].
 *
 * @package SingleSignOnWith365
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class M365_SSO_Shortcode {

    /** @var M365_SSO_Auth */
    private $auth;

    /**
     * @param M365_SSO_Auth $auth Auth instance.
     */
    public function __construct( M365_SSO_Auth $auth ) {
        $this->auth = $auth;
    }

    public function init() {
        add_shortcode( 'm365_login', array( $this, 'render' ) );
    }

    /**
     * Render the [m365_login] shortcode.
     *
     * Attributes:
     *   text           – Button label (default: option value).
     *   redirect       – URL to redirect to after login (default: current page).
     *   class          – Additional CSS class(es) for the button.
     *   logged_in_text – Message shown to logged-in users (empty = hide).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render( $atts ) {
        if ( ! $this->auth->is_configured() ) {
            return '';
        }

        $atts = shortcode_atts( array(
            'text'           => get_option( 'm365_sso_button_text', 'Sign in with Microsoft 365' ),
            'redirect'       => '',
            'class'          => '',
            'logged_in_text' => '',
        ), $atts, 'm365_login' );

        // If user is already logged in, show optional message or nothing.
        if ( is_user_logged_in() ) {
            if ( ! empty( $atts['logged_in_text'] ) ) {
                return '<p class="m365-sso-logged-in">' . esc_html( $atts['logged_in_text'] ) . '</p>';
            }
            return '';
        }

        // Determine redirect URL: shortcode attribute > current page.
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
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21" class="m365-sso-icon" aria-hidden="true">';
        $html .= '<rect x="1" y="1" width="9" height="9" fill="#f25022"/>';
        $html .= '<rect x="11" y="1" width="9" height="9" fill="#7fba00"/>';
        $html .= '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>';
        $html .= '<rect x="11" y="11" width="9" height="9" fill="#ffb900"/>';
        $html .= '</svg> ';
        $html .= esc_html( $atts['text'] );
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }
}
