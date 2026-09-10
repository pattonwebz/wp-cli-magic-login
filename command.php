<?php
/**
 * WP-CLI bootstrap for the magic-login command.
 *
 * Loaded automatically by WP-CLI's package manager when this package is
 * installed with `wp package install`, or manually with `require` from
 * wp-cli.yml's `require:` list.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/src/MagicLoginCommand.php';
}

WP_CLI::add_command( 'magic-login', 'WPCLIMagicLogin\\MagicLoginCommand' );
