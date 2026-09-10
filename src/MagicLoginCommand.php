<?php

namespace WPCLIMagicLogin;

use WP_CLI;
use WP_CLI_Command;

/**
 * Generate and manage one-time magic login links for local testing.
 */
class MagicLoginCommand extends WP_CLI_Command {

    const TRANSIENT_PREFIX = 'wpcli_magic_login_';

    /**
     * Creates a one-time login link for a user.
     *
     * The link logs the given user in on first visit, then expires — either
     * after being used once, or after --expires seconds, whichever comes
     * first. Never touches the user's password.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, user_login, or email address.
     *
     * [--expires=<seconds>]
     * : Seconds until the link expires if unused. Default: 300.
     *
     * [--redirect=<url>]
     * : URL to redirect to after login. Default: the admin dashboard.
     *
     * [--remember]
     * : Set a "remember me" auth cookie (30 days) instead of a session cookie.
     *
     * [--porcelain]
     * : Output just the URL, nothing else. Useful for scripting.
     *
     * ## EXAMPLES
     *
     *     # Log in as admin, land on the dashboard, link expires in 5 minutes.
     *     $ wp magic-login create admin
     *     Success: One-time login link for admin (expires in 300s):
     *     http://site.test/?magic_login=aBcD1234...
     *
     *     # Land on a specific screen, short-lived link, script-friendly output.
     *     $ wp magic-login create qa_admin --redirect=/wp-admin/edit.php?post_type=edbs_meeting --expires=60 --porcelain
     *     http://site.test/?magic_login=...
     *
     * @when after_wp_load
     */
    public function create( $args, $assoc_args ) {
        list( $user_identifier ) = $args;

        $user = $this->find_user( $user_identifier );
        if ( ! $user ) {
            WP_CLI::error( "No user found matching '{$user_identifier}'." );
        }

        $this->ensure_mu_plugin_installed();

        $expires  = isset( $assoc_args['expires'] ) ? max( 1, (int) $assoc_args['expires'] ) : 300;
        $redirect = $assoc_args['redirect'] ?? admin_url();
        $remember = WP_CLI\Utils\get_flag_value( $assoc_args, 'remember', false );

        $token = wp_generate_password( 32, false );

        set_transient(
            self::TRANSIENT_PREFIX . $token,
            [
                'user_id'  => $user->ID,
                'redirect' => $redirect,
                'remember' => (bool) $remember,
            ],
            $expires
        );

        $url = add_query_arg( 'magic_login', $token, home_url( '/' ) );

        if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
            WP_CLI::line( $url );
            return;
        }

        WP_CLI::success( "One-time login link for {$user->user_login} (expires in {$expires}s):" );
        WP_CLI::line( $url );
    }

    /**
     * Invalidates all outstanding magic-login tokens.
     *
     * Use this to revoke unused links, e.g. after a test run or before
     * handing a site off.
     *
     * ## EXAMPLES
     *
     *     $ wp magic-login invalidate
     *     Success: Invalidated 2 outstanding magic-login token(s).
     *
     * @when after_wp_load
     */
    public function invalidate( $args, $assoc_args ) {
        global $wpdb;

        $like = $wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%';
        $count = (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
        );

        $like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%';
        $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout )
        );

        WP_CLI::success( "Invalidated {$count} outstanding magic-login token(s)." );
    }

    /**
     * Finds a user by ID, login, or email.
     *
     * @param string $identifier User ID, login, or email.
     * @return \WP_User|false
     */
    private function find_user( string $identifier ) {
        if ( is_numeric( $identifier ) ) {
            $user = get_user_by( 'id', (int) $identifier );
            if ( $user ) {
                return $user;
            }
        }

        $user = get_user_by( 'login', $identifier );
        if ( $user ) {
            return $user;
        }

        return get_user_by( 'email', $identifier );
    }

    /**
     * Copies the bundled mu-plugin into wp-content/mu-plugins if missing or
     * outdated, so the site can handle ?magic_login= requests. Re-copying on
     * every run (cheap md5 check) means a package update propagates to
     * already-set-up sites without a separate "reinstall" step.
     */
    private function ensure_mu_plugin_installed(): void {
        $mu_plugins_dir = WPMU_PLUGIN_DIR;
        if ( ! is_dir( $mu_plugins_dir ) ) {
            wp_mkdir_p( $mu_plugins_dir );
        }

        $target = $mu_plugins_dir . '/wp-cli-magic-login.php';
        $source = dirname( __DIR__ ) . '/mu-plugin/wp-cli-magic-login.php';

        if ( ! file_exists( $target ) || md5_file( $target ) !== md5_file( $source ) ) {
            copy( $source, $target );
            WP_CLI::debug( 'Installed/updated wp-cli-magic-login mu-plugin.', 'magic-login' );
        }
    }
}
