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
     * Update checker instance returned by the PUC factory.
     *
     * @var object|null
     */
    private $checker = null;

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

        $this->checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $repo,
            BLT_M365_SSO_PLUGIN_FILE,
            'blt-m365-wp-sso'
        );

        $this->checker->setBranch( $branch );

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
        return (string) get_option( 'm365_github_token', '' );
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
