<?php
	/*
		Plugin Name:  Phil Tanner's Emailer 
		Plugin URI:   https://github.com/PhilTanner/wppt_emailer
		Description:  A way to debug email/SMTP error pains
		Version:      1.0.4
		Tested up to: 4.9
		Author:       Phil Tanner
		Author URI:   https://github.com/PhilTanner
		License:      GPL3
		License URI:  http://www.gnu.org/licenses/gpl.html
		Domain Path:  /languages
		Text Domain:  wppt_emailer

		Copyright (C) 2017 Phil Tanner

		This program is free software: you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation, either version 3 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program. If not, see <http://www.gnu.org/licenses/>.
	*/
	
	// Location that we're going to store our log files in
	define( 'WPPT_EMAILER_LOG_DIR',     WP_CONTENT_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'wppt_emailer'.DIRECTORY_SEPARATOR );
	define( 'WPPT_EMAILER_TEST_TO',     'wppt_emailer_'.time() );
	define( 'WPPT_EMAILER_TEST_TO_ADDR', WPPT_EMAILER_TEST_TO.'@email.ghostinspector.com' );
	define( 'WPPT_EMAILER_TEST_SUBJECT','wppt_emailer Test email success!' );
	define( 'WPPT_EMAILER_TEST_MESSAGE','This is a test email from the email system. Success!'."\n\n".'If you can read this, your outbound email demonstrably works. So check the spam filters on the receiving end - and the Sent folder of your mail account.' );
	
	// Some custom exceptions for error handing
	class wppt_emailer_Exception                                      extends Exception {}
		class wppt_emailer_Exception_Remote                            extends wppt_emailer_Exception {}
			class wppt_emailer_Exception_Remote_Refused                extends wppt_emailer_Exception_Remote {}
			class wppt_emailer_Exception_Remote_Incorrect_Credentials  extends wppt_emailer_Exception_Remote {}
			class wppt_emailer_Exception_Remote_Require_Authentication extends wppt_emailer_Exception_Remote {}
				class wppt_emailer_Exception_Remote_Unknown_Auth       extends wppt_emailer_Exception_Remote_Require_Authentication {}
		class wppt_emailer_Exception_Local                             extends wppt_emailer_Exception {}

	/* 
	 * This section holds our WordPress Plugin management functions
	 */
	// User "activates" the plugin in the dashboard
	function wppt_emailer_activate(){
		global $wppt_emailer_version;
		
		// Create our log file directory
		wp_mkdir_p( WPPT_EMAILER_LOG_DIR );
		// Create an htaccess file to prevent it being accessed from the web
		if( !file_exists( WPPT_EMAILER_LOG_DIR . '.htaccess' ) ) {
			$fp = fopen(WPPT_EMAILER_LOG_DIR . '.htaccess', 'w');
			// Stop directory lists
			fwrite($fp, 'Options -Indexes' );
			// Deny any browsing access to any files with the ".log" extention
			fwrite($fp, '<Files "*.log">'."\n");
			fwrite($fp, '	Order Allow,Deny'."\n");
			fwrite($fp, '	Deny from all'."\n");
			fwrite($fp, '</Files>'."\n");
			fclose($fp);
		}
		// Update our plugin version to this one
		$plugin_data = get_plugin_data(__FILE__);
		update_option( "wppt_emailer_version", $plugin_data['Version'] );

		// Default our plugin settings to what WordPress is currently using
		$currentport = get_option('mailserver_port', 25);
		add_option( "wppt_emailer_smtpdebug", 0 );
		add_option( "wppt_emailer_smtp_host", get_option('mailserver_url', 'localhost') );
		add_option( "wppt_emailer_smtp_auth", (strlen(trim(get_option('mailserver_login','')))?true:false) );
		add_option( "wppt_emailer_port",      $currentport );
		add_option( "wppt_emailer_username",  get_option('mailserver_login','') );
		add_option( "wppt_emailer_password",  get_option('mailserver_pass','')  );
		add_option( "wppt_emailer_smtpsecure",($currentport==587?'tls':($currentport==465?'ssl':'none')) );
		
		register_uninstall_hook( __FILE__, 'wppt_emailer_uninstall' );
	}
	register_activation_hook( __FILE__, 'wppt_emailer_activate' );

	// User "deactivates" the plugin in the dashboard
	function wppt_emailer_deactivate() {
		// We're going to do nothing - but we will if you uninstall.
	}
	register_deactivation_hook( __FILE__, 'wppt_emailer_deactivate' );

	// Function to be called when WordPress loads a page with this plugin activated
	function wppt_emailer_load($hook) {
		// Logged in users make an AJAX call
		add_action( 'wp_ajax_wppt_emailer_logfile',   'wppt_emailer_ajax_logfile' );
	}
	add_action('init', 'wppt_emailer_load');

	// Plugin deleted
	function wppt_emailer_uninstall() {
		// Remove all our settings
		delete_option( "wppt_emailer_version"   );
		
		delete_option( "wppt_emailer_smtpdebug" );
		delete_option( "wppt_emailer_smtp_host" );
		delete_option( "wppt_emailer_smtp_auth" );
		delete_option( "wppt_emailer_port"      );
		delete_option( "wppt_emailer_username"  );
		delete_option( "wppt_emailer_password"  );
		delete_option( "wppt_emailer_smtpsecure");

		// Remove our log files
		rmdir( WPPT_EMAILER_LOG_DIR, true );
	}
	
	// Load our JS scripts - we're gonna use jQuery & jQueryUI Dialog boxes, and some buttons
	// Taken from https://developer.wordpress.org/reference/functions/wp_enqueue_script/
	function wppt_emailer_load_admin_scripts($hook) {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-effects-core' );
		wp_enqueue_script( 'jquery-ui-button' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-widget' );
	}
	add_action('admin_enqueue_scripts', 'wppt_emailer_load_admin_scripts');
	
	// Load our style sheets
	function wppt_emailer_load_admin_styles($hook) {
		global $wp_scripts; // Use this to find out which jQueryUI CSS file we need
		
		// Grab a generic jQueryUI stylesheet
		wp_enqueue_style(
			'jquery-ui-redmond',
			// This would make more sense, but WP plugin checkers say no...
			plugins_url( '/css/jquery-ui-1.11.4.custom/jquery-ui.min.css', __FILE__ ),
			$wp_scripts->registered['jquery-ui-core']->ver
		);

		wp_register_style( 
			'wppt_emailer_admin',	
			plugins_url( '/css/admin.css', __FILE__ ), 
			false,	
			get_option( "wppt_emailer_version") 
		);
		wp_enqueue_style ( 'wppt_emailer_admin' );

	}
	add_action('admin_enqueue_scripts', 'wppt_emailer_load_admin_styles');

	// If we're in the admin pages, load our admin console.
	if ( is_admin() ) {
		require_once( dirname(__FILE__).'/admin/admin.php' );
	}


	/* 
	 * This section handles our custom functions
	 */

	// Log any errors for our later perusal
	function wppt_emailer_log_error( $logfile, $err ){
		$fn = WPPT_EMAILER_LOG_DIR . $logfile . '.log';
		$fp = fopen($fn, 'a');
		fputs($fp, date('c')."\t" . json_encode($err) ."\n");
		fclose($fp);
	}

	// Take over our PHPMailer settings
	function wppt_emailer_phpmailer_settings( $phpmailer ) {
		// We're always going to use SMTP
		$phpmailer->isSMTP();
		// Set our debug level (Default to 'off' for production cases)
		$phpmailer->SMTPDebug=  get_option( 'wppt_emailer_smtpdebug' );
		// What SMTP host are we going to be sending out mail through?
		$phpmailer->Host     = get_option( 'wppt_emailer_smtp_host' );
		// Do we need authorisation to access this email host?
		$phpmailer->SMTPAuth = get_option( 'wppt_emailer_smtp_auth' );
		// What SMTP port do we want to access?
		$phpmailer->Port     = get_option( 'wppt_emailer_port' );
		// We're only going to give it auth credentials if we're going to auth
		if( $phpmailer->SMTPAuth ) {
			$phpmailer->Username = get_option( 'wppt_emailer_username' );
			$phpmailer->Password = get_option( 'wppt_emailer_password' );
		}
		$phpmailer->SMTPSecure = get_option( 'wppt_emailer_smtpsecure' );
	}
	// Nice high priority, so it should be run last, overriding any other plugin
	// settings
	add_action( 'phpmailer_init', 'wppt_emailer_phpmailer_settings', 9999 );

	// Trap any WordPress email failures
	function wppt_emailer_log_mailer_errors( $mailer ){
		// First off, throw the contents into a logfile for us.
		wppt_emailer_log_error( 'mail', $mailer );
	}
	add_action('wp_mail_failed', 'wppt_emailer_log_mailer_errors', 10, 1);
	
	// Echos contents of the log files for the admin tool.
	function wppt_emailer_ajax_logfile() {
		$user = wp_get_current_user();
		// Check if we're an admin user
		if( !$user || !$user->has_cap( "manage_options" ) ) {
			wp_die('Insufficient privileges');
		}

		// Which log file?
		$logfile = $_GET['log'];
		// Avoid people passing in things like "/../../../../../../../../etc/passwd"
		// Split our requested file down by dots
		$logfile = explode('.', $logfile);
		// Then only use the first bit (so "/" in above abuse example, or "mail" in legit example)
		$logfile = $logfile[0];
		// Then append ".log" to the end again.
		$logfile .= ".log";
		$f = fopen( WPPT_EMAILER_LOG_DIR.$logfile, 'r' ) or wp_die('Unable to open log file.');
		$fc = fread($f, filesize( WPPT_EMAILER_LOG_DIR.$logfile ));
		fclose($f);
		
		// Prettify our file contents
		$prettycontents = array();
		$contents = explode("\n", $fc);
		for($i=count($contents); $i>=0; $i--){
			$line = explode("\t", $contents[$i]);
			if( count($line) == 2 ){
				$prettycontents[$line[0]] = json_decode($line[1]);
			}
		}
		ob_start();
		print_r($prettycontents);
		$fc = ob_get_contents();
		ob_end_clean();
		$fc = str_replace(
			"]", 
			"</strong>]", 
			str_replace(
				"[", 
				"[<strong class='string'>", 
				htmlspecialchars(
					str_replace(
						"\n\n", 
						"\n", 
						str_replace(
							' => Array', 
							'', 
							str_replace(
								' => stdClass Object', 
								'', 
								$fc
							)
						)
					), 
					ENT_QUOTES, 
					'UTF-8', 
					true
				)
			) 
		);
		
		wp_die($fc);
	}

	// www.gnuterrypratchett.com
	function wppt_emailer_add_header_xua() {
		header( 'X-Clacks-Overhead: GNU Terry Pratchett' );
	}
	add_action( 'send_headers', 'wppt_emailer_add_header_xua' );
	
	if( !function_exists('wppt_emailer_get_git_branch') ) {
		// Taken from: https://stackoverflow.com/questions/7447472/how-could-i-display-the-current-git-branch-name-at-the-top-of-the-page-of-my-de
		function wppt_emailer_get_git_branch() {
			if( !file_exists( plugin_dir_path(__FILE__).'.git'.DIRECTORY_SEPARATOR.'HEAD' ) ) {
				return false; 
			} else { 
				return trim(substr(file_get_contents(plugin_dir_path(__FILE__).'.git'.DIRECTORY_SEPARATOR.'HEAD'), 16));
			}
		}
	}