<?php
/*
Plugin Name: WPTerm
Plugin URI: https://nintechnet.com/bruandet/
Description: An xterm-like plugin to run non-interactive shell commands.
Author: Jerome Bruandet
Version: 1.3
Author URI: https://nintechnet.com/
Text Domain: wpterm
License: GPLv3 or later
Network: true
*
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
define('WPTERM_VERSION', '1.3');

/* ================================================================== */

if (! defined('ABSPATH') ) {
	die('Forbidden');
}

/* ================================================================== */

/**
 * Terminal AJAX action.
 */
require __DIR__ .'/class-ajax.php';

/**
 * Start a session if the user is an admin and
 * WPTerm password protection is enabled.
 */
function wpterm_session() {

	if ( current_user_can('install_plugins') && defined('WPTERM_PASSWORD') &&  is_main_site() ) {

		if (! headers_sent() ) {
			if (! function_exists('session_status') ) {
				if (! session_id() ) {
					session_start();
				}
			} else {
				if ( session_status() !== PHP_SESSION_ACTIVE ) {
					session_start();
				}
			}
		}
	}
}

add_action('admin_init', 'wpterm_session');

/* ================================================================== */

function wpterm_activate() {

	/**
	 * Make sure the user meets the requirements to run WPTerm.
	 */
	if ( PATH_SEPARATOR == ';') {
		wp_die(
			esc_html__('WPTerm is not compatible with Microsoft Windows.', 'wpterm')
		);
	}

	global $wp_version;
	if ( version_compare( $wp_version, '5.0', '<') ) {
		wp_die( sprintf(
			esc_html__('WPTerm requires WordPress 5.0 or greater but your version is %s.', 'wpterm'),
			esc_html( $wp_version )
		) );
	}

	if ( version_compare( PHP_VERSION, '7.0', '<') ) {
		wp_die( sprintf(
			esc_html__('WPTerm requires PHP 5.3 or greater but yourversion is %s.', 'wpterm'),
			PHP_VERSION
		) );
	}

}

register_activation_hook( __FILE__, 'wpterm_activate');

/* ================================================================== */

function wpterm_settings_link( $links ) {

	/**
	 * Display the link in the "Plugins" page.
	 */
	if (! current_user_can('install_plugins') || ! is_main_site() ) {
		return $links;
	}

	$links[] = '<a href="'. get_admin_url( null, 'tools.php?page=wpterm') .
					'">' . esc_html__('Terminal', 'wpterm') . '</a>';
	return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpterm_settings_link');

/* ================================================================== */

function wpterm_js_insert() {

	/**
	 * Insert our JS and CSS files in the footer for the admin...
	 */
	if (! current_user_can('install_plugins') || ! is_main_site() ) {
		return;
	}
	/**...when viewing WPTerm pages only.
	 */
	if (! empty( $_GET['page'] ) && $_GET['page'] == 'wpterm') {

		/**
		 * Load terminal JS code only if we are requesting the terminal tab.
		 */
		if (! empty( $_GET['wptermtab'] ) && $_GET['wptermtab'] == 'terminal') {
			wp_enqueue_script(
				'wpterm_script2',
				plugin_dir_url( __FILE__ ) .'wpterm-terminal.js',
				array('jquery')
			);

		} else {
			wp_enqueue_script(
				'wpterm_script',
				plugin_dir_url( __FILE__ ) .'wpterm.js',
				array('jquery')
			);
		}

		wp_enqueue_style(
			'wpterm_style',
			plugin_dir_url( __FILE__ ) . 'wpterm.css'
		);
	}
}

add_action('admin_footer', 'wpterm_js_insert');

/* ================================================================== */

function wpterm_admin_menu() {

	/**
	 * Append WPTerm menu to the "Tools" menu.
	 */
	if (! is_main_site() ) {
		return;
	}

	global $menu_hook;

	require_once( plugin_dir_path(__FILE__) . 'wpterm-help.php');

	$menu_hook = add_submenu_page(
		'tools.php',
		'WPTerm',
		'WPTerm',
		/**
		 * In a multisite environment, only the superadmin will be able to access WPTerm.
		 */
		'install_plugins',
		'wpterm',
		'wpterm_main_menu'
	);
	/**
	 * Load contextual help.
	 */
	add_action('load-' . $menu_hook, 'wpterm_help');

}

add_action('admin_menu', 'wpterm_admin_menu');

/* ================================================================== */

function wpterm_main_menu() {

	/**
	 * Show the selected tab and page.
	 */

	// If the terminal is password protected,
	// check if the user is authenticated:
	if (! wpterm_is_allowed() ) {
		return;
	}

	$tab = ['terminal', 'settings', 'about'];
	// Make sure $_GET['wptermtab']'s value is okay,
	// otherwise set it to its default 'terminal' value:
	if (! isset( $_GET['wptermtab'] ) || ! in_array( $_GET['wptermtab'], $tab ) ) {
		$_GET['wptermtab'] = 'terminal';
	}
	$wpterm_menu = "wpterm_menu_{$_GET['wptermtab']}";
	$wpterm_menu();

}

/* ================================================================== */

function wpterm_menu_terminal() {

	/**
	 * Display the terminal.
	 */

	/**
	 * If DISALLOW_FILE_MODS is set, we don't allow access to the terminal.
	 */
	if ( defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS ) {
		wp_die(
			esc_html__('Sorry, but DISALLOW_FILE_MODS is set, hence I refuse to run.', 'wpterminal')
		);
	}

	// Fetch our options.
	$wpterm_options = wpterm_menu_get_settings();

	// Retrieve the current user info (name, home dir etc)
	$userinfo = posix_getpwuid( posix_getuid() );

	// Get current working directory:
	if ( $wpterm_options['user-home'] == 'abspath') {
		// WP current dir (a.k.a. ABSPATH):
		$cwd = htmlspecialchars( rtrim( ABSPATH, '/') );
	} else {
		// Linux home dir:
		$cwd = htmlspecialchars( rtrim( $userinfo['dir'], '/') );
	}

	$last_login  = '';
	$kernel_info = '';

	// Get/set last login:
	if (! empty( $wpterm_options['last_login'] ) ) {
		list ( $time, $user, $ip ) = explode( ':', $wpterm_options['last_login'], 3 );
		// Try to get hostname from its IP:
		if (! $host =  gethostbyaddr( $ip ) ) {
			$host = $ip;
		}
		$date = date_i18n( 'D M d H:i:s Y', $time );
		// We'll display this along the "welcome" message:
		$last_login = sprintf(
			__('Last login: %s, %s from %s', 'wpterm'),
			esc_html( $user ),
			$date,
			esc_html( $host ) . '\n'
		);
	}

	// Get the current user (system and WordPress) + his/her IP:
	$current_user = wp_get_current_user();
	$wpuser       = esc_html( $current_user->user_login );
	$user         = esc_html( $userinfo['name'] );
	$ip           = esc_html( $_SERVER['REMOTE_ADDR'] );
	$time         = time();

	// We refuse to run if we're root (unless stated otherwise):
	if ( $user == 'root' && ! defined('THOU_SHALT_NOT_RUN_AS_ROOT') ) {
		?>
		<div class="error notice is-dismissible"><p><?php
			esc_html_e( 'Sorry, but I refuse to run as user root.', 'wpterm');
		?></p></div>
		<div class="wrap"><h1>WPTerm</h1></div>
		<?php
		return;
	}

	// Display a one-time notice if we just installed WPTerm
	// (this notice can be displayed again by entering `notice`
	// at the terminal prompt):
	$notice = esc_html__( "Thanks for using WPTerm!", "wpterm") . " ";
	$notice.= esc_html__( "This is a one-time notice, please read it carefully:", "wpterm") . "<br />";
	$notice.= "<ol>";
	$notice.= "<li>" .
		esc_html__( "Just like a terminal, WPTerm lets you do almost everything you want (e.g., changing file permissions, viewing network connections or current processes etc). That's great, but if you aren't familiar with Unix shell commands, you can also damage your blog.", "wpterm")  . "<br />" .
		esc_html__( "Therefore, each time you use WPTerm, please follow this rule of thumb: if you don't know what you're doing, don't do it!", "wpterm") .  "</li>";
	$notice.= "<li>" .
		esc_html__( 'Take the time to password protect the access to WPTerm. Click on the contextual "Help" menu tab located in the upper right corner to get more details about how to enable this feature (or type `help` from WPTerm prompt).', "wpterm" ) . "</li>";
	$notice.= "<li>" .
		esc_html__( "Do not try to run interactive commands, you can't (most would not run anyway because the TERM environment variable is not set). If you run one by mistake and are stuck at the prompt, press CTRL-C.", "wpterm" ) . "</li>";
	$notice.= "</ol>";
	$notice.=  esc_html__( "If you want to read this notice again, type `notice` from WPTerm prompt.", "wpterm" );

	if ( empty( $wpterm_options['version'] ) ) {
		$style = '';
	} else {
		$style = 'style="display:none" ';
	}
	// Display notice:
	?>
	<div <?php echo $style; ?>id="wpterm-warning" class="error notice"><?php
		echo $notice;
	?><p style="text-align:center">
			<a onclick="jQuery('#wpterm-warning').slideUp();"><?php _e( "Click to hide", "wpterm" ) ?></a>
	</p></div>
	<?php

	// Save options to the database:
	$wpterm_options['last_login'] = "$time:$wpuser:$ip";
	$wpterm_options['version'] = WPTERM_VERSION;
	update_option('wpterm_options', $wpterm_options );

	// Greeting + help command (in english only, no i18n):
	$greeting['cowsay'] = '  _________________________________\n/ ';
	$greeting['cowsay'].= " Welcome and thank you for using" . ' \x5c\n| ';
	$greeting['cowsay'].= " WPTerm :)" . '                       |\n\x5c ';
	$greeting['cowsay'].= " If you need help, type 'help'. " . ' /\n';
	$greeting['cowsay'].= '  ---------------------------------\n        \x5c';
	$greeting['cowsay'].= '   ^__^              v' . WPTERM_VERSION . '\n';
	$greeting['cowsay'].= '         \x5c  (oo)\x5c_______\n';
	$greeting['cowsay'].= '            (__)\x5c       )\x5c/\x5c\n';
	$greeting['cowsay'].= '                ||----w |\n';
	$greeting['cowsay'].= '                ||     ||\n';
	$greeting['wpterm'] = '  __        ______ _____\n';
	$greeting['wpterm'].= '  \x5c \x5c      / /  _ \x5c_   _|__ _ __ _ __ ___\n';
	$greeting['wpterm'].= '   \x5c \x5c /\x5c / /| |_) || |/ _ \x5c \'__| \'_ ` _ \x5c\n';
	$greeting['wpterm'].= '    \x5c V  V / |  __/ | |  __/ |  | | | | | |\n';
	$greeting['wpterm'].= '     \x5c_/\x5c_/  |_|    |_|\x5c___|_|  |_| |_| |_| v' .
									WPTERM_VERSION . '\n';
	$greeting['wpterm'].= '     If you need help, type \'help\'.\n\n';
	$greeting['tux'] = '       .--.      [------------------------------]\n';
	$greeting['tux'].= '      |o_o |      WPTerm v' . WPTERM_VERSION . '\n';
	$greeting['tux'].= '      |:_/ |\n';
	$greeting['tux'].= '     //   \x5c \x5c     Welcome and thank you for\n';
	$greeting['tux'].= '    (|     | )    using WPTerm :)\n';
	$greeting['tux'].= '   /\'\x5c_   _/`\x5c    If you need help, type \'help\'.\n';
	$greeting['tux'].= '   \x5c___)-(___/   [------------------------------]\n';

	// Try to get the kernel info:
	list( $uname, $null ) = @run_command('uname -a', $wpterm_options['php-function'] );
	if (! empty( $uname ) ) {
		$kernel_info = htmlspecialchars( trim( $uname ) ) . '\n';
	} else {
		// Maybe we are running on a shared hosting account that has
		// PHP program execution functions disabled?
		?>
		<div class="error notice is-dismissible"><p><?php
			printf( esc_html__( "I was unable to run a shell command. Make sure that you are allowed to run %sPHP program execution functions%s, otherwise WPTerm will not function.", "wpterm" ), '<a href="http://php.net/manual/en/ref.exec.php">', '</a>' ) ?></p></div>
		<?php
	}

	// Security nonce used for the terminal (AJAX):
	$wpterm_ajax_nonce = wp_create_nonce('wpterm_menu_terminal');

	?>
<style>
	.terminal-user {
		<?php
		if (! empty( $wpterm_options['bold-font'] ) ) {
			echo "font-weight:bold;\n";
		}
		?>
		background-color:<?php echo $wpterm_options['background-color-val'] ?>;
		color:<?php echo $wpterm_options['font-color-val'] ?>;
		font-family:<?php echo $wpterm_options['font-family'] ?>;
		font-size:<?php echo $wpterm_options['font-size'] ?>px;
	}
</style>
<script>
	var wpterm_ajax_nonce = "<?php echo $wpterm_ajax_nonce ?>";
	var prompt = "<?php echo "$user:$cwd" ?> $ ";
	var user = "<?php echo $user ?>";
	var cwd = "<?php echo $cwd ?>";
	var abspath = "<?php echo htmlspecialchars( rtrim( ABSPATH, '/' ) ) ?>";
	var exec = "<?php echo htmlspecialchars( $wpterm_options['php-function'] ) ?>";
	var last_login = "<?php echo $kernel_info . $greeting[$wpterm_options['welcome-message']] . $last_login ?>";
	var in_progress = "<?php echo esc_js( __( 'Operations in progress, please wait.', 'wpterm' ) ) .'\n'.
									esc_js( __( 'If you want to cancel, press CTRL+C.', 'wpterm' ) ) ?>";
	var op_cancelled = "<?php echo esc_js( __( 'operation cancelled', 'wpterm' ) ) ?>";
	var iptables = "<?php echo esc_js( __( 'if you want a good firewall, install NinjaFirewall (WP Edition):', 'wp-shell' ) );
						echo '\n        https://wordpress.org/plugins/ninjafirewall/'; ?>";
	var emul_tab = <?php echo (int) $wpterm_options['tab-completion'] ?>;
	var emul_tab_msg = "<?php echo esc_js( __( 'Tab completion is disabled. You can enable it from the Settings page', 'wpterm' ) ) ?>";
	var logout_url = "<?php echo esc_url( wp_logout_url() ); ?>";
	var logout_msg = "<?php echo esc_js( __( 'Log out of WordPress?', 'wpterm' ) ) ?>";
	var unknown_err = "<?php echo esc_js( __( 'WPTerm: error, no data received', 'wpterm' ) ) ?>";
	var version = "<?php echo '\nWPTerm v' . WPTERM_VERSION ?>";
	var scrollback = <?php echo (int) $wpterm_options['scrollback'] ?>;
	var visual_bell = <?php echo (int) $wpterm_options['visual-bell'] ?>;
	var audible_bell = <?php echo (int) $wpterm_options['audible-bell'] ?>;
	var wrap_on	= "<?php echo esc_js( __( "Line wrapping is enabled", "wpterm" ) ) ?>";
	var wrap_off = "<?php echo esc_js( __( "Line wrapping is disabled", "wpterm" ) ) ?>";
</script>
<?php

// If the blog is setup to use a right-to-left language and the user runs IE/Edge browser
// we inform them that it is not compatible:
if ( is_rtl() && preg_match( '/MSIE|Trident|Edge/', $_SERVER['HTTP_USER_AGENT'] ) ) {
	echo '<div class="notice-warning notice is-dismissible"><p>' .
		esc_html__('Because your current locale is RTL (Right To Left script), the terminal will not work well with your IE/Edge browser. Consider using another browser that is compatible (Firefox, Chrome, Opera or Safari).', 'wpterm') .'</p></div>';
}

?>
<div class="wrap">
	<h1>WPTerm</h1>

	<h2 class="nav-tab-wrapper wp-clearfix">
		<a href="?page=wpterm&wptermtab=terminal" class="nav-tab nav-tab-active"><?php esc_html_e( 'Terminal', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=settings" class="nav-tab"><?php esc_html_e( 'Settings', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=about" class="nav-tab"><?php esc_html_e( 'About', 'wpterm' ) ?></a>
	</h2>

	<table style="width:100%;padding-top:4px">
		<tr>
			<td width="100%">
				<textarea dir="auto" ondragstart="return false;" id="terminal" class="terminal terminal-user" onMouseOver="this.focus();" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" wrap="soft"></textarea>
			</td>
		</tr>
	</table>

	<div>
		<p class="alignleft">
			<img id="progress_gif" style="display:none" src="<?php echo plugins_url() ?>/wpterm/images/wpterm-progress.gif" width="51" height="13" title="<?php esc_attr_e('Operations in progress, please wait.', 'wpterm') ?>">
		</p>
		<p class="alignright">
			<img onClick="line_wrapping(this);" onTouchStart="line_wrapping(this);" id="wrap-line" border="0" src="<?php echo plugins_url() ?>/wpterm/images/wpterm-wrap.png" width="20" height="20" title="<?php esc_attr_e( "Line wrapping is enabled", "wpterm" ) ?>" style="cursor:pointer">
				&nbsp;&nbsp;&nbsp;
			<img onClick="font_size(-1);" onTouchStart="font_size(-1);" border="0" src="<?php echo plugins_url() ?>/wpterm/images/wpterm-fontminus.png" width="21" height="20" title="<?php esc_attr_e( "Decrease font size", "wpterm" ) ?>" style="cursor:pointer">
				&nbsp;&nbsp;&nbsp;
			<img onClick="font_size(1);" onTouchStart="font_size(1);" border="0" src="<?php echo plugins_url() ?>/wpterm/images/wpterm-fontplus.png" width="21" height="20" title="<?php esc_attr_e( "Increase font size", "wpterm" ) ?>" style="cursor:pointer">
		</p>
	</div>

</div>
<?php
}

/* ================================================================== */

function wpterm_menu_settings() {

	// Display the settings page:

	// Save settings?
	if ( isset( $_POST['save-settings'] ) ) {
		// Verify security nonce:
		if ( empty( $_POST['wptermnonce'] ) ||
			! wp_verify_nonce( $_POST['wptermnonce'], 'save_settings' ) ) {

			wp_nonce_ays( 'save_settings' );
		}
		wpterm_menu_save_settings();
		echo '<div class="updated notice is-dismissible"><p>' .
			esc_html__('Your changes have been saved.', 'wpterm') .'</p></div>';
	}

	// Fetch, verify and sanitize the current settings:
	$wpterm_options = wpterm_menu_get_settings();

?>
<div class="wrap">
	<h1>WPTerm</h1>

	<h2 class="nav-tab-wrapper wp-clearfix">
		<a href="?page=wpterm&wptermtab=terminal" class="nav-tab">
			<?php esc_html_e( 'Terminal', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=settings" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Settings', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=about" class="nav-tab">
			<?php esc_html_e( 'About', 'wpterm' ) ?></a>
	</h2>

	<br />

	<form method="post">

	<h3><?php esc_html_e('Fonts and Colors', 'wpterm') ?></h3>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e('Font color', 'wpterm') ?></th>
			<td>
				<input type="text" name="font-color" value="<?php echo esc_attr( $wpterm_options['font-color'] ) ?>" oninput="wpterm_preview('color', 'color', this.value)" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
				<p>
					<span class="description">
						<?php printf ( esc_html__( 'Hexadecimal value (e.g., %s) or CSS color name (e.g., <code>red</code>).', 'wpterm' ), '<code>ffffff</code>' ) ?>
					</span>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e('Background color', 'wpterm') ?></th>
			<td>
				<input type="text" name="background-color" value="<?php echo esc_attr( $wpterm_options['background-color'] ) ?>" oninput="wpterm_preview('color', 'background', this.value)" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
				<p>
					<span class="description">
						<?php printf ( esc_html__( 'Hexadecimal value (e.g., %s) or CSS color name (e.g., <code>red</code>).', 'wpterm' ), '<code>3465A4</code>' ) ?>
					</span>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e('Font size', 'wpterm') ?></th>
			<td>
				<input type="number" class="small-text" name="font-size" step="1" min="9" max="20" value="<?php echo (int) $wpterm_options['font-size'] ?>" oninput="wpterm_preview('fontsize', 0, this.value);" /> px
				&nbsp;&nbsp;&nbsp;&nbsp;
				<label><input type="checkbox" id="bold_font" onchange="wpterm_preview('fontweight', 'bold_font', this.value);" name="bold-font"<?php checked( $wpterm_options['bold-font'], 1 ) ?> /><?php esc_html_e( 'Bold fonts', 'wpterm' ) ?></label>
				<p>
					<span class="description">
						<?php esc_html_e('From 9 to 20px.', 'wpterm') ?>
					</span>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e('Font family', 'wpterm') ?></th>
			<td>
				<input type="text" class="regular-text" name="font-family" value="<?php echo esc_attr( $wpterm_options['font-family'] ) ?>" oninput="wpterm_preview('fontface', 0, this.value)" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" />
				<p>
					<span class="description">
						<?php esc_html_e( 'Multiple values must be comma separated (e.g., <code>Consolas,Monaco,monospace</code>)', 'wpterm' ) ?>
					</span>
				</p>
			</td>
		</tr>

		<?php
		if (! empty( $wpterm_options['bold-font'] ) ) {
			$font_weight = 'font-weight:bold;';
		} else {
			$font_weight = 'font-weight:normal;';
		}
		?>
		<tr>
			<th scope="row"><?php esc_html_e('Test', 'wpterm') ?></th>
			<td>
				<textarea autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" id="textarea-test" rows="3" style="width:20em;resize:both;padding:10px;color:<?php echo esc_attr( $wpterm_options['font-color-val'] ) ?>;background-color:<?php echo esc_attr( $wpterm_options['background-color-val'] ) ?>;font-size:<?php echo (int) $wpterm_options['font-size'] ?>px;font-family:<?php echo esc_attr( $wpterm_options['font-family'] ) ?>;<?php echo $font_weight ?>"><?php echo "ABCDEFGHIJKLMNOPQRSTUVWXYZ\nabcdefghijklmnopqrstuvwxyz\n0123456789" ?></textarea>
			</td>
		</tr>

	</table>

	<br />

	<h3><?php esc_html_e('Terminal', 'wpterm') ?></h3>

	<table class="form-table">

		<tr>
			<th scope="row"><?php esc_html_e('Use the following PHP function for command execution', 'wpterm') ?></th>
			<td>
				<p>
					<label>
						<input type="radio" name="php-function" value="exec"<?php checked( $wpterm_options['php-function'], 'exec' ) ?> /><code>exec</code>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="php-function" value="shell_exec"<?php checked( $wpterm_options['php-function'], 'shell_exec' ) ?> /><code>shell_exec</code>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="php-function" value="system"<?php checked( $wpterm_options['php-function'], 'system' ) ?> /><code>system</code>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="php-function" value="passthru"<?php checked( $wpterm_options['php-function'], 'passthru' ) ?> /><code>passthru</code>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="php-function" value="popen"<?php checked( $wpterm_options['php-function'], 'popen' ) ?> /><code>popen</code>
					</label>
				</p>
			</td>
		</tr>


		<tr>
			<th scope="row"><?php esc_html_e('Emulate pseudo-Tab completion?', 'wpterm') ?></th>
			<td>
				<p>
					<label>
						<input type="radio" name="tab-completion" value="1"<?php checked( $wpterm_options['tab-completion'], 1 ) ?> /><?php _e( 'Yes', 'wpterm' ) ?>
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="tab-completion" value="0"<?php checked( $wpterm_options['tab-completion'], 0 ) ?> /><?php _e( 'No', 'wpterm' ) ?>
					</label>
				</p>
			</td>
		</tr>

		<?php
		// Retrieve user info:
		$userinfo = posix_getpwuid( posix_getuid() );
		?>
		<tr>
			<th scope="row"><?php esc_html_e('Default working directory', 'wpterm') ?></th>
			<td>
				<p>
					<label>
						<input type="radio" name="user-home" value="abspath"<?php checked( $wpterm_options['user-home'], 'abspath' ) ?> /><?php printf( esc_html__( 'WordPress ABSPATH (%s)', 'wpterm' ), '<code>'. esc_html( ABSPATH ) .'</code>' ) ?>
					</label>
				</p>
				<span class="description"><?php printf( esc_html__( "Tip: to go back to that directory, type %s.", "wpterm" ), '<code>cd $ABSPATH</code>' ) ?></span>

				<p>
					<label>
						<input type="radio" name="user-home" value="homedir"<?php checked( $wpterm_options['user-home'], 'homedir' ) ?> /><?php printf( esc_html__( 'User home directory (%s)', 'wpterm' ), '<code>'. esc_html( $userinfo['dir'] ) .'</code>' ) ?>
					</label>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e('Scrollback', 'wpterm') ?></th>
			<td>
				<label><?php printf( esc_html__( "Limit scrollback to %s lines", "wpterm" ) , '<input type="number" class="small-text" name="scrollback" step="1" min="1" max="3000" value="' . (int) $wpterm_options['scrollback'] .'" />' ) ?></label>
				<br>
				<span class="description">
					<?php esc_html_e('Max 3,000 lines.', 'wpterm') ?>
				</span>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e('Welcome message', 'wpterm') ?></th>
			<td>
				<p>
					<label>
						<input type="radio" name="welcome-message" value="wpterm"<?php checked( $wpterm_options['welcome-message'], 'wpterm' ) ?> />WPTerm
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="welcome-message" value="cowsay"<?php checked( $wpterm_options['welcome-message'], 'cowsay' ) ?> />Cowsay
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="welcome-message" value="tux"<?php checked( $wpterm_options['welcome-message'], 'tux' ) ?> />Tux
					</label>
				</p>
			</td>
		</tr>

		<?php
		// IE up to 11 isn't compatible with our 'Audible bell':
		if ( isset( $_SERVER["HTTP_USER_AGENT"] ) && strpos( $_SERVER["HTTP_USER_AGENT"], '; rv:11' ) !== false ) {
			$disabled = ' disabled="disabled"';
		} else {
			$disabled = '';
		}
		?>
		<tr>
			<th scope="row"><?php esc_html_e('Terminal bell', 'wpterm') ?></th>
			<td>
				<p><label id="visual-bell">
					<input type="checkbox" onchange="bell_preview(this, 'visual');" name="visual-bell"<?php checked( $wpterm_options['visual-bell'], 1 ) ?> /><?php esc_html_e( 'Visual bell', 'wpterm' ) ?>
				</label></p>
				<p><label>
				<input type="checkbox"<?php echo $disabled ?> onchange="bell_preview(this, 'beep');" name="audible-bell"<?php checked( $wpterm_options['audible-bell'], 1 ) ?> /><?php esc_html_e( 'Audible bell', 'wpterm' ) ?>
				</label></p>
			</td>
		</tr>

	</table>

	<br />
	<br />

	<input class="button-primary" type="submit" name="save-settings" value="<?php esc_html_e('Save Settings', 'wpterm') ?>" />

	<?php wp_nonce_field('save_settings', 'wptermnonce', 0); ?>

	</form>

</div>

<?php

}

/* ================================================================== */

function wpterm_menu_get_settings() {

	// Retrieve the current settings:

	$wpterm_options = get_option( 'wpterm_options' );

	// First run, not options available yet ($wpterm_options is boolean).
	if (! is_array( $wpterm_options ) ) {
		$wpterm_options = [];
	}

	if ( empty( $wpterm_options['font-color'] ) ) {
		$wpterm_options['font-color'] = 'ffffff';
	} else {
		$wpterm_options['font-color'] = preg_replace( '/\W/', '', $wpterm_options['font-color'] );
	}
	if ( ctype_xdigit( $wpterm_options['font-color'] ) ) {
		$wpterm_options['font-color-val'] = '#' . $wpterm_options['font-color'];
	} else {
		$wpterm_options['font-color-val'] = $wpterm_options['font-color'];
	}

	if ( empty( $wpterm_options['background-color'] ) ) {
		$wpterm_options['background-color'] = '3465A4';
	} else {
		$wpterm_options['background-color'] = preg_replace( '/\W/', '', $wpterm_options['background-color'] );
	}
	if ( ctype_xdigit( $wpterm_options['background-color'] ) ) {
		$wpterm_options['background-color-val'] = '#' . $wpterm_options['background-color'];
	} else {
		$wpterm_options['background-color-val'] = $wpterm_options['background-color'];
	}

	if (! isset( $wpterm_options['font-size'] ) || ! preg_match( '/^(?:9|1[0-9]|20)$/',  $wpterm_options['font-size'] ) ) {
		$wpterm_options['font-size'] = 13;
	}


	if (! empty( $wpterm_options['bold-font'] ) ) {
		$wpterm_options['bold-font'] = 1;
	} else {
		$wpterm_options['bold-font'] = 0;
	}

	if (! empty( $wpterm_options['font-family'] ) ) {
		$wpterm_options['font-family'] = preg_replace( '/[^\'" ,a-zA-Z]/', '', $wpterm_options['font-family'] );
		$wpterm_options['font-family'] = trim( $wpterm_options['font-family'], ' ,' );
	}
	if ( empty( $wpterm_options['font-family'] ) ) {
		$wpterm_options['font-family'] = 'Consolas,Monaco,monospace';
	}

	if ( empty( $wpterm_options['welcome-message'] ) || ! preg_match( '/^(?:wpterm|cowsay|tux)$/', $wpterm_options['welcome-message'] ) ) {
		$wpterm_options['welcome-message'] = 'wpterm';
	}

	if ( empty( $wpterm_options['php-function'] ) || ! preg_match( '/^(?:exec|shell_exec|system|passthru|popen)$/', $wpterm_options['php-function'] ) ) {
		// WPTerm <1.1.2:
		if ( @$wpterm_options['php-function'] == 'backtick' ) {
			$wpterm_options['php-function'] = 'shell_exec';
		} else {
			$wpterm_options['php-function'] = 'exec';
		}
	}

	if (! isset( $wpterm_options['tab-completion'] ) || $wpterm_options['tab-completion'] == 1 ) {
		// Default value:
		$wpterm_options['tab-completion'] = 1;
	} else {
		$wpterm_options['tab-completion'] = 0;
	}


	if (! isset( $wpterm_options['user-home'] ) || $wpterm_options['user-home'] == 'abspath' ) {
		$wpterm_options['user-home'] = 'abspath';
	} else {
		$wpterm_options['user-home'] = 'homedir';
	}


	if (! empty( $wpterm_options['scrollback'] ) ) {
		$wpterm_options['scrollback'] = (int) $wpterm_options['scrollback'];
		if ( $wpterm_options['scrollback'] < 1 || $wpterm_options['scrollback'] > 3000 ) {
			$wpterm_options['scrollback'] = 512;
		}
	} else {
		$wpterm_options['scrollback'] = 512;
	}


	if (! isset( $wpterm_options['visual-bell'] ) || $wpterm_options['visual-bell'] == 1 ) {
		$wpterm_options['visual-bell'] = 1;
	} else {
		$wpterm_options['visual-bell'] = 0;
	}

	if (! empty( $wpterm_options['audible-bell'] ) ) {
		$wpterm_options['audible-bell'] = 1;
	} else {
		$wpterm_options['audible-bell'] = 0;
	}


	return $wpterm_options;

}

/* ================================================================== */

function wpterm_menu_save_settings() {

	// Check and save the terminal settings:

	$wpterm_options = get_option('wpterm_options');


	if ( empty( $_POST['font-color'] ) ) {
		$wpterm_options['font-color'] = 'ffffff';
	} else {
		// Make sure $_POST['font-color'] contains only word characters:
		$wpterm_options['font-color'] = preg_replace( '/\W/', '', $_POST['font-color'] );
	}

	if ( empty( $_POST['background-color'] ) ) {
		$wpterm_options['background-color'] = '3465A4';
	} else {
		// Make sure $_POST['background-color'] contains only word characters:
		$wpterm_options['background-color'] = preg_replace( '/\W/', '', $_POST['background-color'] );
	}

	// Make sure $_POST['font-size'] is an integer between 9 and 20,
	// otherwise set it to 13, its default value:
	if (! isset( $_POST['font-size'] ) || ! preg_match( '/^(?:9|1[0-9]|20)$/',  $_POST['font-size'] ) ) {
		$wpterm_options['font-size'] = 13;
	} else {
		$wpterm_options['font-size'] = (int)$_POST['font-size'];
	}

	if (! empty( $_POST['bold-font'] ) ) {
		$wpterm_options['bold-font'] = 1;
	} else {
		$wpterm_options['bold-font'] = 0;
	}

	// Make sure $_POST['font-family'] contains only letters, commas, spaces, single and double quotes:
	if (! empty( $_POST['font-family'] ) ) {
		$wpterm_options['font-family'] = preg_replace( '/[^\'" ,a-zA-Z]/', '', $_POST['font-family'] );
		$wpterm_options['font-family'] = trim( $wpterm_options['font-family'], ' ,' );
	}
	if ( empty( $_POST['font-family'] ) ) {
		$wpterm_options['font-family'] = 'Consolas,Monaco,monospace';
	}

	// Make sure the value of $_POST['welcome-message'] is 'wpterm', 'cowsay' or 'tux',
	// otherwise set it to 'wpterm', its default value:
	if ( empty( $_POST['welcome-message'] ) || ! preg_match( '/^(?:wpterm|cowsay|tux)$/', $_POST['welcome-message'] ) ) {
		$wpterm_options['welcome-message'] = 'wpterm';
	} else {
		$wpterm_options['welcome-message'] = htmlspecialchars( $_POST['welcome-message'] );
	}

	// Make sure the value of $_POST['php-function'] is 'exec', 'shell_exec', 'system', 'popen' or 'passthru',
	// otherwise set it to 'exec', its default value:
	if ( empty( $_POST['php-function'] ) || ! preg_match( '/^(?:exec|shell_exec|system|passthru|popen)$/', $_POST['php-function'] ) ) {
		$wpterm_options['php-function'] = 'exec';
	} else {
		$wpterm_options['php-function'] = htmlspecialchars( $_POST['php-function'] );
	}

	if ( empty( $_POST['tab-completion'] ) || $_POST['tab-completion'] != 1 ) {
		$wpterm_options['tab-completion'] = 0;
	} else {
		$wpterm_options['tab-completion'] = 1;
	}

	// Make sure the value of $_POST['user-home'] is 'abspath' or 'homedir',
	// otherwise set it to 'abspath', its default value:
	if ( empty( $_POST['user-home'] ) || ! preg_match( '/^(?:abspath|homedir)$/', $_POST['user-home'] ) ) {
		$wpterm_options['user-home'] = 'abspath';
	} else {
		$wpterm_options['user-home'] = htmlspecialchars( $_POST['user-home'] );
	}

	// Make sure $_POST['scrollback'] is an integer between 1 and 3,000,
	// otherwise set it to 512, its default value:
	if (! empty( $_POST['scrollback'] ) ) {
		$wpterm_options['scrollback'] = (int) $_POST['scrollback'];
		if ( $wpterm_options['scrollback'] < 1 || $wpterm_options['scrollback'] > 3000 ) {
			$wpterm_options['scrollback'] = 512;
		}
	} else {
		$wpterm_options['scrollback'] = 512;
	}


	if (! empty( $_POST['audible-bell'] ) ) {
		$wpterm_options['audible-bell'] = 1;
	} else {
		$wpterm_options['audible-bell'] = 0;
	}
	if (! empty( $_POST['visual-bell'] ) ) {
		$wpterm_options['visual-bell'] = 1;
	} else {
		$wpterm_options['visual-bell'] = 0;
	}


	// Save current version too (we'll likely need it when updating the plugin):
	$wpterm_options['version'] = WPTERM_VERSION;

	update_option( 'wpterm_options', $wpterm_options );

}

/* ================================================================== */

function wpterm_menu_about() {

	if ( file_exists( plugin_dir_path(__FILE__) . 'LICENSE.TXT' ) ) {
		$gpl3 = file_get_contents( plugin_dir_path(__FILE__) . 'LICENSE.TXT' );
	} else {
		$gpl3 = esc_html__( 'Error: cannot open LICENSE.TXT!', 'wpterm' );
	}
?>
<div class="wrap">
	<h1>WPTerm</h1>

	<h2 class="nav-tab-wrapper wp-clearfix">
		<a href="?page=wpterm&wptermtab=terminal" class="nav-tab"><?php esc_html_e( 'Terminal', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=settings" class="nav-tab"><?php esc_html_e( 'Settings', 'wpterm' ) ?></a>
		<a href="?page=wpterm&wptermtab=about" class="nav-tab nav-tab-active"><?php esc_html_e( 'About', 'wpterm' ) ?></a>
	</h2>

	<div class="card">
		<h1>WPTerm v<?php echo WPTERM_VERSION ?></h1>
		<h3>&copy; <?php echo date( 'Y' ) ?> Jerome Bruandet</h3>
		<hr />
		<strong><?php esc_html_e('From the same author:', 'wpterm' ) ?></strong>
		<ul>
			<li><a href="https://wordpress.org/plugins/ninjafirewall/"><strong>NinjaFirewall (WP Edition)</strong></a>: <?php esc_html_e('A true Web Application Firewall to protect and secure WordPress.', 'wpterm' ) ?></li>
			<li><a href="https://wordpress.org/plugins/code-profiler/"><strong>Code Profiler</strong></a>: <?php esc_html_e('WordPress Performance Profiling and Debugging Made Easy.', 'wpterm' ) ?></li>
			<li><a href="https://wordpress.org/plugins/safercheckout-lite/"><strong>SaferCheckout</strong></a>: <?php esc_html_e('Fraud prevention for WooCommerce stores.', 'wpterm' ) ?></li>
			<li><a href="https://wordpress.org/plugins/ninjascanner/"><strong>NinjaScanner</strong></a>: <?php esc_html_e('A lightweight, fast and powerful antivirus scanner for WordPress.', 'wpterm' ) ?></li>
			<li><a href="https://wordpress.org/plugins/dashboard-cleaner/"><strong>Dashboard Cleaner</strong></a>: <?php esc_html_e('Reclaim your admin dashboard: Get rid of annoying banners, unwanted ads and other nuisances.', 'wpterm' ) ?></li>
		</ul>
		<hr />
		<h3><?php _e('Rate it', 'wpterm' ) ?></h3>
		<a href="https://wordpress.org/support/view/plugin-reviews/wpterm?rate=5#postform"><img title="<?php _e('Rate it', 'wpterm' ) ?>" border="0" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHQAAAAcCAIAAAA/XwxHAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH3woMCgQevC7e8gAAActJREFUaN7tmb9LAmEYx9/XM1OLNDMCB8MLQRCHoKGprbW1pcWh/yKabHVsjIj+gKgGh1q1oMEIIoIup8Ph0lOvO7F7720QuZI6fd9q6X2+2/G+H57j88D7gxdTShHkb+IDBSAX5EJ+LJfS5p7RbHGVE4tll0sV82G383BIODZCwVgfcxsbBaPtoHah02gB+6tyqWI+HjoIIaRZj4zNFI1llNtv4+CLrZmisaxy3Tb2w9JM0ViEEEL42xsa1W3ttGc82ZZCTMW2FGLVv5iLp30h2R+SpbAshWT/9NpkPOdDgrGYVS7qEXVTuztxGFoVC+ZuookUFo5lXhYCUuI4ll7F41YKBNIXg0qisTxr7tRE6mw2uTROKX/yfDa1jMVlOTY0HJ/MXEYX5kZsiwtHscz68NIjGstzWsCLoexB0GOitBHJbkkYWL6jGKkTj3WeqI7HsGgsq1xqXRGv8ec3qwssn1ziGLeenXp5MxrA8sntEr3mApH8zMr9/EoxHIm6v6M/U2C/uj+PilN7LSO1hOqVvKHVHHfAtLWiXomqJaSW920H2OGMIde+ble3P5f5GNPWinp1p2cDOxwMr79/F3hDA7kgFwJyQe6/yDsZhxXHUCuqgQAAAABJRU5ErkJggg==" width="116" height="28"><br /><?php _e('Rate it on WordPress.org', 'wpterm' ) ?></a>
		<p><?php _e('Thanks!', 'wpterm' ) ?></p>
		<hr />
		<br />
		<textarea id="wpterm-license" class="small-text code" style="display:none;width:100%" rows="8" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><?php echo esc_textarea( $gpl3 ) ?></textarea>
		<input id="wpterm-license-button" type="button" class="button-secondary" value="<?php echo esc_attr__('View license', 'wpterm' ) ?>" onClick="show_license();" />
		<br />&nbsp;
	</div>
</div>
<?php
}

/* ================================================================== */
function run_command( $command, $function ) {

	$ret_var = '';
	$res = '';

	$wpterm_options = wpterm_menu_get_settings();
	$function = $wpterm_options['php-function'];
	if (! preg_match('/^(?:exec|shell_exec|system|passthru|popen)$/', $function ) ) {
		echo( "\n". esc_html__('Invalid execution method.', 'wpterm') );
		exit;
	}

	// Select which method to use to run the command:
	if ( $function == 'shell_exec') {
		$res = shell_exec( $command );

	} elseif ( $function == 'system') {
        ob_start();
        system( $command, $ret_var );
        $res = ob_get_contents();
        ob_end_clean();

	} elseif ( $function == 'passthru') {
        ob_start();
        passthru( $command, $ret_var );
        $res = ob_get_contents();
        ob_end_clean();

	} elseif ( $function == 'popen') {
		if ( ( $handle = popen( $command , 'r') ) !== false ) {
			while (! feof( $handle ) ) {
				 $res .= fgets( $handle );
			}
			pclose( $handle );
		}

	} else {
		if ( exec( $command, $res, $ret_var ) ) {
			$res = implode( "\n", $res );
		}
	}

	return [ $res, $ret_var ];

}

/* ================================================================== */

function wpterm_is_allowed( $is_ajax = null ) {

	// Check if a password was set:
	if (! defined( 'WPTERM_PASSWORD' ) ) {
		// No, let it go:
		return true;
	}

	// Check if the user session exists:
	if ( empty( $_SESSION['wptermpwd'] ) ) {
		// Return if this is an AJAX call (a warning
		// will be displayed from the terminal prompt):
		if ( isset( $is_ajax ) ) { return false; }
		// Display the password form:
		if( ! wpterm_password_prompt(1) ) {
			return false;
		}
	}
	// Check if passwords match:
	if ( $_SESSION['wptermpwd'] != WPTERM_PASSWORD ) {
		// Password does not match, clear it:
		unset( $_SESSION['wptermpwd'] );
		if ( isset( $is_ajax ) ) { return false; }
		// Display the password form:
		if (! wpterm_password_prompt(2) ) {
			return false;
		}
	}

	// Okay, go ahead!
	return true;

}

/* ================================================================== */

function wpterm_password_prompt( $err = 0 ) {

	// Display the password form:

	// Password form submitted?
	if ( isset( $_POST['wptermpwd'] ) ) {
		// Verify security nonce:
		if ( empty( $_POST['wptermnonce'] ) || ! wp_verify_nonce( $_POST['wptermnonce'], 'wpterm_password' ) ) {
			wp_nonce_ays( 'wpterm_password' );
		}
		// Verify password:
		if ( sha1( $_POST['wptermpwd'] ) === WPTERM_PASSWORD ) {
			$_SESSION['wptermpwd'] = sha1( $_POST['wptermpwd'] );
			return true;
		} else {
			$err = 3;
		}
	}

	if ( $err == 3 ) {
		?>
		<div class="error notice is-dismissible"><p><?php esc_html_e( 'Wrong password, please try again.', 'wpterm' ) ?></p></div>
		<?php
	} else {
		?>
		<div class="notice-info notice is-dismissible"><p><?php printf( esc_html__( 'A password is required to access WPTerm (#%s).', 'wpterm' ), (int) $err ) ?></p></div>
		<?php
	}
?>

<div class="wrap">
	<h1>WPTerm</h1>

	<h2 class="nav-tab-wrapper wp-clearfix" style="cursor:not-allowed">
		<a class="nav-tab"><?php esc_html_e( 'Terminal', 'wpterm' ) ?></a>
		<a class="nav-tab"><?php esc_html_e( 'Settings', 'wpterm' ) ?></a>
		<a class="nav-tab"><?php esc_html_e( 'About', 'wpterm' ) ?></a>
		<a class="nav-tab"><?php esc_html_e( 'Info', 'wpterm' ) ?></a>
	</h2>

	<div class="card">

		<form method="post">
			<h3><?php esc_html_e( 'Enter your WPTerm password:', 'wpterm' ) ?></h3>
			<p><input class="input" type="password" name="wptermpwd" placeholder="Password" autofocus /></p>
			<p><input type="submit" class="button-secondary" /></p>
			<?php wp_nonce_field('wpterm_password', 'wptermnonce', 0); ?>
		</form>

	</div>
</div>
<?php

	return false;

}

/* ================================================================== */
// EOF
