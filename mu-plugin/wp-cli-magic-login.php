<?php
/**
 * Plugin Name: WP-CLI Magic Login (auto-installed)
 * Description: Handles one-time login tokens created via `wp magic-login create`. Auto-installed by pattonwebz/wp-cli-magic-login into mu-plugins on first use — safe to delete, it's recreated on the next `wp magic-login create`.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const WPCLI_MAGIC_LOGIN_QUERY_VAR = 'magic_login';
const WPCLI_MAGIC_LOGIN_TRANSIENT_PREFIX = 'wpcli_magic_login_';

add_action(
    'init',
    static function () {
        if ( empty( $_GET[ WPCLI_MAGIC_LOGIN_QUERY_VAR ] ) || is_user_logged_in() ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET[ WPCLI_MAGIC_LOGIN_QUERY_VAR ] ) );
        $data  = get_transient( WPCLI_MAGIC_LOGIN_TRANSIENT_PREFIX . $token );

        // Tokens are single-use: delete on first attempt regardless of validity,
        // so a leaked/guessed URL can't be retried after a failed race.
        delete_transient( WPCLI_MAGIC_LOGIN_TRANSIENT_PREFIX . $token );

        if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
            return;
        }

        $user = get_userdata( (int) $data['user_id'] );
        if ( ! $user ) {
            return;
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, ! empty( $data['remember'] ) );

        /** This action is documented in wp-login.php. */
        do_action( 'wp_login', $user->user_login, $user );

        $redirect_to = ! empty( $data['redirect'] ) ? $data['redirect'] : admin_url();

        // Some sites emit stray output before 'init' (a stray newline in an
        // mu-plugin, a BOM, etc) which silently breaks header()-based
        // redirects. Fall back to a meta-refresh/JS redirect rather than
        // leaving the user logged in but stranded on the wrong page.
        if ( ! headers_sent() ) {
            wp_safe_redirect( $redirect_to );
            exit;
        }

        printf(
            '<meta http-equiv="refresh" content="0;url=%1$s"><script>location.replace(%2$s);</script><p>Logged in — <a href="%1$s">continue</a>.</p>',
            esc_url( $redirect_to ),
            wp_json_encode( $redirect_to )
        );
        exit;
    },
    1
);
