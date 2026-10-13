<?php
/**
 * GitHub-based self-updater built on the Plugin Update Checker library
 * by Yanis Elsts (https://github.com/YahnisElsts/plugin-update-checker).
 *
 * @package BltM365WpSso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BLT_M365_SSO_Updater {

    /** Default GitHub repository to check for updates. */
    const DEFAULT_REPO = 'https://github.com/S-FX-com/BLT-M365-WP-SSO/';

    /** Default branch that holds the production-ready release. */
    const DEFAULT_BRANCH = 'main';

    /**
     * The slug the update checker is built with. It is what the manual
     * "Check for Updates" request keys on, so the settings screen needs it
     * too — see BLT_Family_Updates::check_now_url().
     */
    const CHECKER_SLUG = 'blt-m365-wp-sso';

    /**
     * Update checker instance returned by the PUC factory.
     *
     * @var object|null
     */
    private $checker = null;

    /**
     * The one checker this plugin builds, shared with screens that only need
     * to read from it (the settings page instantiates its own updater for
     * display, and must not build a second checker).
     *
     * @var object|null
     */
    private static $built_checker = null;

    /**
     * Wire up the update checker.
     */
    public function init() {
        $loader = BLT_M365_SSO_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

        if ( ! is_readable( $loader ) ) {
            return;
        }

        require_once $loader;

        if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
            return;
        }

        /**
         * Filter the GitHub repository URL used for update checks.
         *
         * @param string $repo Repository URL.
         */
        $repo = apply_filters( 'blt_m365_sso_update_repo', self::DEFAULT_REPO );

        /**
         * Filter the branch that holds the stable release.
         *
         * @param string $branch Branch name.
         */
        $branch = apply_filters( 'blt_m365_sso_update_branch', $this->get_branch() );

        // The 24 is required, not cosmetic: a checker built with 0 registers no
        // scheduler hooks at all and cannot be revived afterwards.
        $this->checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $repo,
            BLT_M365_SSO_PLUGIN_FILE,
            self::CHECKER_SLUG,
            24
        );

        $this->checker->setBranch( $branch );

        $token = $this->get_token();
        if ( $token ) {
            $this->checker->setAuthentication( $token );
        } else {
            // Nothing local. The shared BLT store is not elected until
            // plugins_loaded, so give get_token()'s fallback a second chance
            // once it can actually see a shared token. Nothing is lost by
            // waiting: plugin-update-checker does not contact GitHub until
            // admin_init or its cron event, both later than this.
            add_action( 'plugins_loaded', array( $this, 'maybe_apply_shared_token' ), 5 );
        }

        // Family update policy: at most one automatic check a day, anchored to
        // 00:00 site time, with manual checks always allowed immediately.
        BLT_Family_Updates::apply(
            $this->checker,
            array(
                'basename'  => BLT_M365_SSO_PLUGIN_BASENAME,
                'icons_url' => BLT_M365_SSO_PLUGIN_URL . 'assets/img/',
            )
        );

        self::$built_checker = $this->checker;
    }

    /**
     * The update checker instance, once init() has built it.
     *
     * @return object|null
     */
    public function get_checker() {
        return self::$built_checker;
    }

    /**
     * Authenticate the checker from the shared BLT store, if it holds a token.
     *
     * Runs on plugins_loaded, which is the earliest point BLT_Family exists.
     * Only reached when this plugin has no token of its own.
     *
     * @return void
     */
    public function maybe_apply_shared_token() {
        if ( ! is_object( $this->checker ) ) {
            return;
        }

        $token = $this->get_token();

        if ( $token ) {
            $this->checker->setAuthentication( $token );
        }
    }

    /**
     * Resolve the branch to track.
     *
     * @return string
     */
    private function get_branch() {
        if ( defined( 'BLT_M365_SSO_UPDATE_BRANCH' ) && BLT_M365_SSO_UPDATE_BRANCH ) {
            return (string) BLT_M365_SSO_UPDATE_BRANCH;
        }
        return self::DEFAULT_BRANCH;
    }

    /**
     * Resolve the GitHub access token for private-repository update checks.
     *
     * A constant takes precedence over the stored option so credentials can be
     * kept out of the database in hardened environments.
     *
     * @return string
     */
    private function get_token() {
        if ( defined( 'BLT_M365_SSO_GITHUB_TOKEN' ) && BLT_M365_SSO_GITHUB_TOKEN ) {
            return (string) BLT_M365_SSO_GITHUB_TOKEN;
        }

        $token = (string) get_option( 'm365_github_token', '' );

        // Last resort only: the shared BLT store, which returns nothing unless
        // the site owner opted this plugin into the 'github' group. Precedence
        // stays constant -> this plugin's own option -> shared store, and
        // nothing here is ever written back to the option.
        if ( '' === $token && class_exists( 'BLT_Family' ) ) {
            $token = (string) BLT_Family::get( 'blt-m365-wp-sso', 'github', 'token' );
        }

        return $token;
    }

    /**
     * The repository URL currently in use (for display on the settings page).
     *
     * @return string
     */
    public function get_repo() {
        return apply_filters( 'blt_m365_sso_update_repo', self::DEFAULT_REPO );
    }

    /**
     * The branch currently tracked (for display on the settings page).
     *
     * @return string
     */
    public function get_active_branch() {
        return apply_filters( 'blt_m365_sso_update_branch', $this->get_branch() );
    }

    /**
     * Whether an access token is configured via constant.
     *
     * @return bool
     */
    public function token_is_constant() {
        return defined( 'BLT_M365_SSO_GITHUB_TOKEN' ) && BLT_M365_SSO_GITHUB_TOKEN;
    }
}
