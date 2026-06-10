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

if (! defined( 'ABSPATH' ) ) { die( 'Forbidden' ); }

/* ================================================================== */

function wpterm_help() {

	$null = esc_html__('An xterm-like plugin to run non-interactive shell commands.', 'wpterm');

	// Contextual help:

	get_current_screen()->add_help_tab( array(
		'id'        => 'wpterm_settings',
		'title'     => esc_html__("Terminal settings", "wpterm"),
		'content'   => '<div style="height:300px;">' .
							'<h3>' . esc_html__( "Fonts and Colors", "wpterm" ) . '</h3>' .
							'<p>' . esc_html__( "You can select the terminal fonts and background colors as well as the font size and weight. Use hexadecimal values (e.g., `FFFFFF`) or CSS color names (e.g., `red`) for colors.", "wpterm" ) . "</p>" .
							'<h3>' . esc_html__( "Terminal", "wpterm" ) . '</h3>' .
							'<p><b>' . esc_html__( "Use the following PHP function for program execution", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "You can select which PHP function WPTerm will use to run the terminal commands.", "wpterm" ) .
							" " . esc_html__( "`exec` is the default one.", "wpterm" ) . '</p>' .
							'<p><b>' . esc_html__( "Emulate pseudo-Tab completion?", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "Because it is not a real shell, WPTerm cannot reproduce the TAB completion feature of a regular terminal. It can, however, emulate its own one which is linked to your history, i.e., it applies only to the commands that you have typed during the current session. Those commands can be seen by entering `history` or by pressing the `UP` and `DOWN` arrows.", "wpterm" ) . '</p>' .
							'<p><b>' . esc_html__( "Default working directory", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "You can select to start the terminal in the WordPress root folder (a.k.a. `ABSPATH`) or in your user home directory (`~`).", "wpterm" ) .
							'<br />' .
							esc_html__( "Note that WPTerm adds a specific variable named `\$ABSPATH<` to your environment. You can use it just like any other environment variables for instance:", "wpterm" ) . '</p>' .
							'<ul>' .
							'<li>' . sprintf( esc_html__( 'Go back to the ABSPATH: %s', "wpterm" ), '<code>cd $ABSPATH</code>' ) . '</li>' .
							'<li>' . sprintf( esc_html__( 'Display the ABSPATH: %s', "wpterm" ), '<code>echo $ABSPATH</code>' ) . '</li>' .
							'<li>' . sprintf( __( 'Find all files in the ABSPATH that were changed the last 10 days: %s', "wpterm" ), '<code>find $ABSPATH -type f -ctime -10</code>' ) . '</li>' .
							'</ul>' .
							'<p><b>' . esc_html__( "Scrollback", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "The scrollback buffer can keep up to 3,000 lines. Default value is set to 512 lines.", "wpterm" ) . '</p>' .
							'<p><b>' . esc_html__( "Welcome message", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "Select your welcome message.", "wpterm" ) . '</p>' .
							'<p><b>' . esc_html__( "Terminal bell", "wpterm" ) . '</b></p>' .
							'<p>' . esc_html__( "You can enable the visual or/and audible bell. Note that the audible bell is not compatible with some older browsers (e.g., IE 11 or earlier versions).", "wpterm" ) . '</p>' .
							'</div>'
	) );

	get_current_screen()->add_help_tab( array(
		'id'        => 'wpterm_security',
		'title'     => esc_html__( "Password protection", 'wpterm' ),
		'content'   =>	'<div style="height:300px;">' .
							'<h3>' . esc_html__( "Password protection", "wpterm" ) . '</h3>' .
							'<p>' . esc_html__( "You can password protect the access to WPTerm. To do so, follow these steps:", "wpterm" ) . "</p>" .
							'<ol>' .
							'<li>' . esc_html__( "Choose a password!", "wpterm" ) . "</li>" .
							'<li>' . esc_html__( "Generate a SHA1 hash of your password; from WPTerm terminal, enter the following command (replace PASSWORD with your chosen password):", "wpterm" ) .
							"<p><code>echo -n 'PASSWORD' | sha1sum</code></p>" . "</li>" .
							'<li>' . esc_html__( "Copy the 40-character hash returned by the above command.", "wpterm" ) . "</li>" .
							'<li>' . esc_html__( "Download your WordPress wp-config.php file. It is located inside your WordPress root folder. If you cannot find it, search it with the following command from WPTerm terminal:", "wpterm" ) .
							'<p><code>find $ABSPATH -type f -name \'wp-config.php\'</code></p>' . "</li>" .
							'<li>' . esc_html__( "Open that file and add the following line of code below your database credentials (replace HASH with your 40-character hash):", "wpterm" ) .
							'<p><code>define( \'WPTERM_PASSWORD\', \'HASH\' );</code></p>' . "</li>" .
							'</ol>' .
							'<p>' . esc_html__( "Next time you will access WPTerm, it will ask you to enter your password.", "wpterm" ) . '</p>' .
							'<h3>' . esc_html__( "Change or remove the password protection", "wpterm" ) . '</h3>' .
							'<p>' . esc_html__( "To change your password protection, simply generate a new SHA1 hash and edit wp-config.php.", "wpterm" ) . '</p>' .
							'<p>' .esc_html__( "You disable the password protection, remove the line from wp-config.php.", "wpterm" ) . '</p>' .
							'<h3>' . esc_html__( "Password expiration", "wpterm" ) . '</h3>' .
							'<p>' .esc_html__( "WPTerm relies on PHP sessions for the authentication process. In case of inactivity, the session will expire and you will be asked again for the password. That session timeout depends on your PHP configuration, not WPTerm.", "wpterm" ) . '</p>' .
							'</div>'
	) );

	get_current_screen()->add_help_tab( array(
		'id'        => 'wpterm_command',
		'title'     => esc_html__( "Built-in commands", 'wpterm' ),
		'content'   =>	'<div style="height:300px;">' .
							'<p>' . esc_html__( "WPTerm has the following built-in commands:", "wpterm" ) . "</p>" .
							'<ul>' .
							'<li><code>clear</code>, <code>reset</code>, <code>cls</code>: ' . esc_html__( "Clear the terminal screen. Alternatively, you can press the `CTRL+L` keyboard shortcut.", "wpterm" ) . "</li>" .
							'<li><code>exit</code>, <code>quit</code>, <code>logout</code>, <code>shutdown</code>, <code>reboot</code>: ' . esc_html__( "Log out of WordPress.", "wpterm" ) . "</li>" .
							'<li><code>version</code>, <code>wpterm</code>: ' . esc_html__( "Show WPTerm version.", "wpterm" ) . "</li>" .
							'<li><code>notice</code>: ' . esc_html__( "Display the one-time warning notice.", "wpterm" ) . "</li>" .
							'<li><code>help</code>: ' . esc_html__( 'Open this contextual help menu.', "wpterm" ) . "</li>" .
							'<li><code>history</code>: ' . esc_html__( "Display the history buffer. You can also use the `UP` and `DOWN` arrows to display each element from the buffer.", "wpterm" ) . "</li>" .
							'<li><code>history -c</code>: ' . esc_html__( "Clear the history buffer.", "wpterm" ) . "</li>" .
							'<li><code>wpterm moo</code>: ' . esc_html__( "Don't even try!", "wpterm" ) . "</li>" .
							'</ol>' .
							'</div>'
	) );
}

/* ================================================================== */
// EOF
