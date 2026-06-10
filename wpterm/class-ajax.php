<?php
/*
 +=====================================================================+
 |      __        ______ _____                                         |
 |      \ \      / /  _ \_   _|__ _ __ _ __ ___                        |
 |       \ \ /\ / /| |_) || |/ _ \ '__| '_ ` _ \                       |
 |        \ V  V / |  __/ | |  __/ |  | | | | | |                      |
 |         \_/\_/  |_|    |_|\___|_|  |_| |_| |_|                      |
 |                                                                     |
 | (c) Jerome Bruandet ~ https://nintechnet.com/                       |
 +=====================================================================+
*/
if (! defined('ABSPATH') ) {
	die('Forbidden');
}


class WPTerm_ajax {


	/**
	 * Terminal AJAX action.
	 */
	public static function init() {

		add_action('wp_ajax_wptermajax', [ __CLASS__, 'wptermajax_callback' ] );

	}


	public static function wptermajax_callback() {

		// The terminal AJAX callback function:

		if (! current_user_can('install_plugins' ) || ! is_main_site() ) {
			wp_die(0);
		}

		// Check AJAX security nonce:
		if ( check_ajax_referer( 'wpterm_menu_terminal', 'wpterm_ajax_nonce', false ) ) {

			// Path to return in case of fatal error:
			$if_error = htmlspecialchars( rtrim( ABSPATH, '/' ) ) . '::';

			// If the password protection is enabled, check the password:
			if (! wpterm_is_allowed( 'ajax' ) ) {
				echo $if_error . __( 'WPTerm: error, your password has expired. Reload this page to renew it.', 'wpterm');
				wp_die();
			}

			if ( empty( $_POST['cmd'] ) || empty( $_POST['cwd'] ) ||
				empty( $_POST['exec'] ) || empty( $_POST['abs'] ) ) {

				echo $if_error . __( 'WPTerm error: missing command, path, function or abspath', 'wpterm' );
				wp_die();
			}
			// Make sure the max number of lines to returned to WPTerm
			// is a digit, otherwise set it to 512, its default value:
			if ( empty( $_POST['scrollback'] ) || ! ctype_digit( $_POST['scrollback'] ) ) {
				$scrollback = 512;
			} else {
				$scrollback = (int)$_POST['scrollback'];
				if ( $scrollback > 3000 ) {
					$scrollback = 3000;
				}
			}

			// We don't want WordPress to escape strings with slashes.
			$cmd = wp_unslash( base64_decode( trim( $_POST['cmd'] ) ) );
			$cwd = wp_unslash( trim( $_POST['cwd'] ) );
			$abs = wp_unslash( trim( $_POST['abs'] ) );
			$cwd_real = realpath( $cwd );
			$abs_real = realpath( $abs );
			if ( false === $cwd_real || false === $abs_real ) {
				wp_die( $if_error . __('Invalid path.', 'wpterm') );
			}

			// Set the ABSPATH variable, go to the current working directory,
			// run the command, redirect STDERR to STDOUT and return the current
			// working directory (it may have been changed e.g., `cd /foo/bar`):
			$command = sprintf(
				'ABSPATH=%s; cd %s; %s 2>&1; echo [-{-`pwd`-}-]',
				escapeshellarg( $abs_real ),
				escapeshellarg( $cwd_real ),
				$cmd  // arbitrary by design, we're a terminal.
			);

			// Run the command:
			list( $res, $ret_var ) = @run_command( $command, trim( $_POST['exec'] ) );

			// Split the PWD and the data returned by the command:
			if ( preg_match('`^(.+)?\[-{-(/.*?)-}-\]`s', $res, $match ) ) {
				// Turn the string into an array...
				$res_array = explode( "\n", $match[1] );
				// ...keep only the last $scrollback lines and re-create the string...
				$res_str = implode( "\n", array_slice( $res_array, -$scrollback ) );
				// ...and return it to WPTerm terminal:
				echo rtrim( $match[2] . '::' . $res_str );
			} else {
				if (! empty( $ret_var ) ) {
					echo $if_error . sprintf( esc_html__('WPTerm: error %s', 'wpterm'), (int) $ret_var );
				} else {
					echo $if_error . esc_html__('WPTerm: unknown error. Are you allowed to run PHP program execution functions?', 'wpterm');
				}
			}
		} else {
			echo '/::' . esc_html__('WPTerm: error, security nonces do not match. Try to reload this page to renew them.', 'wpterm');
		}
		wp_die();

	}

}

WPTerm_ajax::init();

/* ================================================================== */
// EOF
