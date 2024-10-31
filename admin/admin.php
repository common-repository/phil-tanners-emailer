<?php
	/*
		Phil Tanner's Emailer 
		
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

	// Create our own Emailer menu items
	// Taken from:https://codex.wordpress.org/Adding_Administration_Menus
	function wppt_emailer_menu_add(){
		// Add a new main menu level for CadetNet Admin
		add_menu_page( 
			__("Phil's Emailer", 'wppt_emailer'), // Page title
			__("Phil's Emailer", 'wppt_emailer'),	// Menu text
			"manage_options", // Capability required (Needed to save option changes to system)
			"wppt_emailer_menu", // Menu slug (unique name)
			"wppt_emailer_menu", // Function to be called when displaying content
			"dashicons-email-alt" // The url to the icon to be used for this menu. This parameter is optional.
		);
	}
	add_action( 'admin_menu', 'wppt_emailer_menu_add' );

	// Output our page contents
	function wppt_emailer_menu() {
		// User must be an admin to access
		if ( !current_user_can( "manage_options" ) )	{
			wp_die( __( "You do not have sufficient permissions to access this page." ) );
		}
		
		echo '<div class="wppt_emailer">';
		
		// So, start our page proper
		$this_ver = get_option('wppt_emailer_version', __('Unknown', 'wppt_emailer'));
		echo "<h2>" . sprintf(__("Phil's Emailer v%s", "wppt_emailer"), $this_ver);
		$gitbranch = wppt_emailer_get_git_branch();
		if($gitbranch) {
			echo sprintf(__('<br /><span style="font-size:80%%">(Current branch: <em>%s</em>)</style>','wppt_emailer'), $gitbranch);
		}
		echo "</h2>";
		
		$plugin_data = get_plugin_data(plugin_dir_path(__FILE__).'../wppt-emailer.php');
		if( $plugin_data['Version'] != $this_ver ){
			echo '<div class="ui-state-error" style="padding:0 1em;">';
			echo sprintf(__('<p><strong style="font-size:120%%;">WARNING:</strong><br/>The activated plugin version ("%s") does not match the current file version ("%s").</p><p>You must deactivate and re-activate the <strong>%s</strong> plugin for the changes to take effect.</p>', 'wppt_emailer'), $this_ver, $plugin_data['Version'], $plugin_data['Name']);
			echo '</div>';
		}
		
		// We've got some settings to save, do so before we output them again
		if( isset($_POST['action']) ) {
			// Check our nonce
			check_admin_referer( 'wppt_emailer_settings' );
			try {
				if( $_POST['action'] == 'test' ) {
					$show_email_received = false;
					// First off, grab what our settings are now
					$settings = array();
					$settings["wppt_emailer_smtpdebug"]  = (int)get_option("wppt_emailer_smtpdebug");
					$settings["wppt_emailer_smtp_host"]  = get_option("wppt_emailer_smtp_host");
					$settings["wppt_emailer_smtp_auth"]  = get_option("wppt_emailer_smtp_auth");
					$settings["wppt_emailer_port"]       = (int)get_option("wppt_emailer_port");
					$settings["wppt_emailer_username"]   = get_option("wppt_emailer_username");
					$settings["wppt_emailer_password"]   = get_option("wppt_emailer_password");
					$settings["wppt_emailer_smtpsecure"] = get_option("wppt_emailer_smtpsecure");
					
					// Then update them to what we've asked for to carry out our test
					update_option("wppt_emailer_smtpdebug",  4 ); // We're always going to test with max debug on!
					update_option("wppt_emailer_smtp_host",  sanitize_option("wppt_emailer_smtp_host", $_POST["wppt_emailer_smtp_host"]) );
					update_option("wppt_emailer_smtp_auth",  sanitize_option("wppt_emailer_smtp_auth", $_POST["wppt_emailer_smtp_auth"]) );
					update_option("wppt_emailer_port",       (int)$_POST["wppt_emailer_port"] );
					update_option("wppt_emailer_username",   sanitize_option("wppt_emailer_username", $_POST["wppt_emailer_username"]) );
					update_option("wppt_emailer_password",   sanitize_option("wppt_emailer_password", $_POST["wppt_emailer_password"]) );
					update_option("wppt_emailer_smtpsecure", sanitize_option("wppt_emailer_smtpsecure", $_POST["wppt_emailer_smtpsecure"]) );
					
					// Then start capturing our output just before we try sending the email
					$mailoutput = '<strong>Server output:</strong>'."\n";
					ob_start();
					$mail = wp_mail( WPPT_EMAILER_TEST_TO_ADDR, WPPT_EMAILER_TEST_SUBJECT, WPPT_EMAILER_TEST_MESSAGE );
					$mailoutput .= ob_get_contents();
					ob_end_clean();
					
					// Then reset our options to what they were (we were testing, not saving after all) and we don't want live cux
					// getting wrong settings.
					// No sanitising, because we're only putting back what we had when we pulled it out. Save some CPU
					update_option("wppt_emailer_smtpdebug",  (int)$settings["wppt_emailer_smtpdebug"] );
					update_option("wppt_emailer_smtp_host",  $settings["wppt_emailer_smtp_host"] );
					update_option("wppt_emailer_smtp_auth",  $settings["wppt_emailer_smtp_auth"] );
					update_option("wppt_emailer_port",       (int)$settings["wppt_emailer_port"]      );
					update_option("wppt_emailer_username",   $settings["wppt_emailer_username"]  );
					update_option("wppt_emailer_password",   $settings["wppt_emailer_password"]  );
					update_option("wppt_emailer_smtpsecure", $settings["wppt_emailer_smtpsecure"]);
					
					// Now, see if we can see any issues we can help you start debugging
					if( strpos($mailoutput, 'No such host is known') !== false ) {
						throw new wppt_emailer_Exception_Local(sprintf(__('Unable to resolve the SMTP Host "%s". Check your internet connection or that you have entered the hostname correctly.','wppt_emailer'), sanitize_text_field($_POST["wppt_emailer_smtp_host"])));
					}
					if( strpos($mailoutput, '10061') !== false || strpos($mailoutput, 'No connection could be made because the target machine actively refused it.') !== false ) {
						throw new wppt_emailer_Exception_Remote_Refused(sprintf(__('The remote server actively refused our connection. SMTP Host "%s" is not listening on port %d. Check your settings and try again.','wppt_emailer'), sanitize_text_field($_POST["wppt_emailer_smtp_host"]), (int)$_POST["wppt_emailer_port"]));
					}
					if( strpos($mailoutput, '530-5.5.1 Authentication Required.') !== false ) {
						throw new wppt_emailer_Exception_Remote_Require_Authentication(sprintf(__('Couldn\'t connect to SMTP Host "%s" on port %d. Username and password is required.','wppt_emailer'), sanitize_text_field($_POST["wppt_emailer_smtp_host"]), (int)$_POST["wppt_emailer_port"]));
					}
					if( strpos($mailoutput, '535-5.7.8 Username and Password not accepted.') !== false ) {
						throw new wppt_emailer_Exception_Remote_Incorrect_Credentials(sprintf(__('Couldn\'t connect to SMTP Host "%s" on port %d. Username and password is incorrect.','wppt_emailer'), sanitize_text_field($_POST["wppt_emailer_smtp_host"]), (int)$_POST["wppt_emailer_port"]));
					}
					if( strpos($mailoutput, '534 5.7.14  https://support.google.com/mail/answer/78754') !== false ) {
						throw new wppt_emailer_Exception_Remote_Unknown_Auth(__('Unknown authentication method. Try TLS? Or enable less secure apps: https://support.google.com/accounts/answer/6010255.','wppt_emailer'));
					}
					if( strpos($mailoutput, '550 5.7.60 SMTP; Client does not have permissions to send as this sender') !== false ) {
						throw new wppt_emailer_Exception_Remote_Unknown_Auth(__('You need to enable Send As permissions for this account, see here: https://technet.microsoft.com/en-us/library/dn554323.aspx.','wppt_emailer'));
					}
					
					// Something went wrong, but we've no idea what. 
					if( strpos($mailoutput, 'SMTP Error: Could not connect to SMTP host') !== false ) {
						throw new wppt_emailer_Exception(__('Unknown error. Check your port number matches your encryption type?','wppt_emailer'));
					}
					
					$show_email_received = true;
				} elseif( $_POST['action'] == 'save' ) {
					update_option("wppt_emailer_smtpdebug",  (int)$_POST["wppt_emailer_smtpdebug"]);
					update_option("wppt_emailer_smtp_host",  sanitize_option("wppt_emailer_smtp_host", $_POST["wppt_emailer_smtp_host"]) );
					update_option("wppt_emailer_smtp_auth",  sanitize_option("wppt_emailer_smtp_auth", $_POST["wppt_emailer_smtp_auth"]) );
					update_option("wppt_emailer_port",       (int)$_POST["wppt_emailer_port"]                  );
					update_option("wppt_emailer_username",   sanitize_option("wppt_emailer_username", $_POST["wppt_emailer_username"])  );
					update_option("wppt_emailer_password",   sanitize_option("wppt_emailer_password", $_POST["wppt_emailer_password"])  );
					update_option("wppt_emailer_smtpsecure", sanitize_option("wppt_emailer_smtpsecure", $_POST["wppt_emailer_smtpsecure"] ) );
				}
				
			} catch( wppt_emailer_Exception_Remote_Refused $Ex ) {
				echo '<div class="ui-state-error" style="padding:0 1em;">';
				echo '<p>'.$Ex->getMessage().'</p>';
				echo '</div>';
				wppt_emailer_log_error( 'AdminUpdates', $Ex );
			} catch( wppt_emailer_Exception_Remote_Unknown_Auth $Ex ) {
				echo '<div class="ui-state-error" style="padding:0 1em;">';
				echo '<p>'.$Ex->getMessage().'</p>';
				if( strtolower(get_option('wppt_emailer_smtp_host'))=='smtp.gmail.com' ){
					echo '<p>';
					echo sscanf(__('When sending out using GMail accounts, you must have already set up the account to Enable Less Secure Apps. For more information, see this URL: <a href="%s">%s</a>', 'wppt_emailer'), "https://support.google.com/accounts/answer/6010255" );
					echo '</p>';
				}
				echo '</div>';
				wppt_emailer_log_error( 'AdminUpdates', $Ex );
			} catch( Exception $Ex ) {
				echo '<div class="ui-state-error" style="padding:0 1em;">';
				echo '<p>'.__('Server reported:','wppt_emailer').'</p>';
				echo '<pre>'.$Ex->getMessage().'</pre>';
				echo '</div>';
				wppt_emailer_log_error( 'AdminUpdates', $Ex );
			} 		
		}
		
		?>
		
		<form style="margin-right:2em;" method="post">			
			<fieldset style="float:left; width: calc(50% - 4em);">
				<legend>Email Settings</legend>
				
				<p>
					<label for="wppt_emailer_smtpdebug">Debug level<br/>(0 = Off, 3=Max)</label>
					<input name="wppt_emailer_smtpdebug" id="wppt_emailer_smtpdebug" type="number" value="<?=get_option("wppt_emailer_smtpdebug",0);?>" min="0" max="4" step="1" required="required" />
					<em>Note: This should be <strong>0</strong> for live web sites!</em>
				</p>
				
				<p>
					<label for="wppt_emailer_smtp_host">SMTP Host</label>
					<input name="wppt_emailer_smtp_host" id="wppt_emailer_smtp_host" placeholder="e.g. smtp.gmail.com" value="<?=get_option('wppt_emailer_smtp_host');?>" required="required" />
					<label for="wppt_emailer_port" style="width:auto">Port</label>
					<input name="wppt_emailer_port" id="wppt_emailer_port" type="number" value="<?=get_option('wppt_emailer_port', 25);?>" required="required" />
				</p>
				
				<p>
					<label for="wppt_emailer_smtpsecure">Use encryption?</label>
					<select name="wppt_emailer_smtpsecure" id="wppt_emailer_smtpsecure" required="required">
						<option value='none'<?=(get_option('wppt_emailer_smtpsecure')==''?' selected="selected"':'');?>>No</option>
						<option value='tls'<?=(get_option('wppt_emailer_smtpsecure')=='tls'?' selected="selected"':'');?>>Yes - using <strong>TLS</strong></option>
						<option value='ssl'<?=(get_option('wppt_emailer_smtpsecure')=='ssl'?' selected="selected"':'');?>>Yes - using <strong>SSL</strong></option>
					</select>
				</p>
				
				<p>
					Use username/password to sign in?<br />
					<label for="wppt_emailer_smtp_auth_y">Yes</label>
					<input type="radio" name="wppt_emailer_smtp_auth" id="wppt_emailer_smtp_auth_y" value="1" required="required"<?=(get_option('wppt_emailer_smtp_auth')?' checked="checked"':'');?> />
					<label for="wppt_emailer_smtp_auth_n">No</label>
					<input type="radio" name="wppt_emailer_smtp_auth" id="wppt_emailer_smtp_auth_n" value="0" required="required"<?=(get_option('wppt_emailer_smtp_auth')?'':' checked="checked"');?> />
				</p>
				<div id="auth" style="<?=(get_option('wppt_emailer_smtp_auth')?'':'display:none;');?>border:1px solid black; margin:1em; border-radius:5px;">
					<p>
						<label for="wppt_emailer_username">Username</label>
						<input name="wppt_emailer_username" id="wppt_emailer_username" value="<?=get_option('wppt_emailer_username');?>" required="required" />
					</p>
					<p>
						<label for="wppt_emailer_password">Password</label>
						<input name="wppt_emailer_password" id="wppt_emailer_username" type="password" value="<?=get_option('wppt_emailer_password');?>" required="required" />
					</p>
				</div>
				<?php
					wp_nonce_field( 'wppt_emailer_settings' );
				?>
				<button type="submit" value="test" name="action" id="test_button">Test</button>
				<button type="submit" value="save" name="action" id="save_button">Save</button>
			</fieldset>
			<fieldset style="float:left; width: 50%;">
				<legend>Email test results</legend>
				<?php
				if( $_POST['action'] == 'test' ) {
					if( $show_email_received ) {
				?>
					<iframe style="width: 100%;" src="https://email.ghostinspector.com/<?=WPPT_EMAILER_TEST_TO;?>/latest"></iframe>
				<?php 
					}
				?>
					<pre style="padding:1ex;overflow-y:scroll; width:100%; height:16em;background-color:Silver;border:1px solid black; white-space: pre-wrap;"><?=$mailoutput?></pre>
				<?php } else { ?>
					<p> No test running... </p>
				<?php } ?>
			</fieldset>
			
		
		<fieldset style="clear:left;">
			<legend>Log Files</legend>
			<ul>
				<?php
					$logs = scandir(WPPT_EMAILER_LOG_DIR);
					
					foreach( $logs as $log ){
						if( strtolower(substr($log, -4)) == ".log" ) {
							echo '<li> <a href="javascript:showlog(\''.$log.'\');">'.$log.'</a> </li>';
						}
					}
				?>
			</ul>
		</fieldset>
		</form>
		
		<script defer="defer">
			jQuery(document).ready( function($){
				// As and when we say we want auth, show the options
				jQuery('input[name="wppt_emailer_smtp_auth"]').change(function(){
					if( jQuery('#wppt_emailer_smtp_auth_y').prop('checked') ) {
						jQuery('#auth').show();
					} else jQuery('#auth').hide();
				});
				
				// If we select secure SMTP, see if we want to change the port
				jQuery('#wppt_emailer_smtpsecure').change(function() {
					var defaultPorts = {none: 25, ssl: 465, tls: 587 };
					var currPort = jQuery('#wppt_emailer_port').val();
					var suggestedPort = eval('defaultPorts.'+jQuery('#wppt_emailer_smtpsecure').val());
					
					if( currPort != suggestedPort ) {
						jQuery('<div></div>').html('<p>The default SMTP port for this encryption type is <strong>'+
							suggestedPort+'</strong>, but you have requested port <strong>'+currPort+'</strong>.</p>'+
							'<p>Do you want to update your settings to use Port <strong>'+suggestedPort+'</strong> instead?</p>').dialog({
							modal:true,
							title:'Change port',
							buttons: [
								{ text: 'Yes', click: function(){ jQuery('#wppt_emailer_port').val(suggestedPort); jQuery(this).dialog('close'); } },
								{ text: 'No', click: function(){ jQuery(this).dialog('close'); } }
							],
							close: function(){ jQuery(this).dialog("destroy"); }
						}).parent().appendTo('.wppt_emailer');
					}
				});
				
				// Prettify our buttons & track which one was clicked on
				jQuery('form button[type="submit"]').click(function() {
					jQuery("button[type=submit]", jQuery(this).parents("form")).removeAttr("clicked");
					jQuery(this).attr("clicked", "true");
				}).button({ icons:{ primary: 'ui-icon-wrench' } }).filter('[value="save"]').button({icons: { secondary: 'ui-icon-disk'}}).css({float:'right'});
				
				// Check our settings for Gmail
				jQuery('form').submit( function( event ) {
					var settingsGood = true;
					// If we already have a confirm screen in place and we're submitting again, we're overriding our suggestion, so 
					// don't bother checking & asking again...
					if( !jQuery("div.confirmsettings").length ) {
						// Look for a GMail/Google server
						if( jQuery('#wppt_emailer_smtp_host').val().indexOf('gmail') >= 0 || jQuery('#wppt_emailer_smtp_host').val().indexOf('google') >= 0 ) {
							// As soon as any step fails, don't bother checking the rest
							while( settingsGood ) {
								if( jQuery('#wppt_emailer_smtp_host').val().toLowerCase() != 'smtp.gmail.com') {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_port').val() != '587') {
									settingsGood = false;
								}
								if( !jQuery('#wppt_emailer_smtp_auth_y').prop('checked') ) {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_username').val().indexOf('@') < 0) {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_smtpsecure').val() != 'tls') {
									settingsGood = false;
								}
								// Avoid infinite loops :D
								break;
							}
							// If there's an issue, suggest what our settings should be
							if( !settingsGood ) {
								jQuery('<div class="confirmsettings"></div>').html('<p>It looks like you\'re trying to use Google outbound servers to send mail, but '+
									'your settings don\'t seem to match their recommended ones.</p>'+
									'<p> You should update your values to the following settings:</p>'+
									'<dl>'+
									'	<dt>Host</dt>'+
									'	<dd>smtp.gmail.com</dd>'+
									'	<dt>Port</dt>'+
									'	<dd>587</dd>'+
									'	<dt>Use encryption</dt>'+
									'	<dd>TLS</dd>'+
									'	<dt>Use username/password</dt>'+
									'	<dd>Yes</dd>'+
									'	<dt>Username</dt>'+
									'	<dd><em>&lt;Your GMail email address&gt;</em></dd>'+
									'	<dt>Password</dt>'+
									'	<dd><em>&lt;The password you use to log in to GMail.com&gt;</em></dd>'+
									'</dl>'+
									'<p>You also need to make sure that you have enabled "Less Secure Apps" to use your account:<br />'+
									'<a href="https://support.google.com/accounts/answer/6010255" target="_blank">https://support.google.com/accounts/answer/6010255</a>'+
									'</p>').dialog({
									modal:true,
									title:'Confirm settings',
									width:'50%',
									buttons: [
										{ text: 'Cancel', click: function(){ jQuery(this).dialog('close'); } },
										{ text: 'Override', click: function(){ jQuery('button[type=submit][clicked=true]').click(); } }
									],
									close: function(){ jQuery(this).dialog("destroy"); }
								}).parent().appendTo('.wppt_emailer');
							// Look for an MS server
							}
						} else if( jQuery('#wppt_emailer_smtp_host').val().indexOf('office365') >= 0 ) {
							// As soon as any step fails, don't bother checking the rest
							while( settingsGood ) {
								if( jQuery('#wppt_emailer_smtp_host').val().toLowerCase() != 'smtp.office365.com') {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_port').val() != '587') {
									settingsGood = false;
								}
								if( !jQuery('#wppt_emailer_smtp_auth_y').prop('checked') ) {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_username').val().indexOf('@') < 0) {
									settingsGood = false;
								}
								if( jQuery('#wppt_emailer_smtpsecure').val() != 'tls') {
									settingsGood = false;
								}
								// Avoid infinite loops :D
								break;
							}
							// If there's an issue, suggest what our settings should be
							if( !settingsGood ) {
								jQuery('<div class="confirmsettings"></div>').html('<p>It looks like you\'re trying to use Microsoft Office 365 outbound servers to send mail, but '+
									'your settings don\'t seem to match their recommended ones.</p>'+
									'<p> You should update your values to the following settings:</p>'+
									'<dl>'+
									'	<dt>Host</dt>'+
									'	<dd>smtp.office365.com</dd>'+
									'	<dt>Port</dt>'+
									'	<dd>587</dd>'+
									'	<dt>Use encryption</dt>'+
									'	<dd>TLS</dd>'+
									'	<dt>Use username/password</dt>'+
									'	<dd>Yes</dd>'+
									'	<dt>Username</dt>'+
									'	<dd><em>&lt;Your Office 365 email address&gt;</em></dd>'+
									'	<dt>Password</dt>'+
									'	<dd><em>&lt;The password you use to log in to Office 365.com&gt;</em></dd>'+
									'</dl>'+
									'<p>You also need to make sure that you have enabled "multifunction devices or applications" to use your account:<br />'+
									'<a href="https://technet.microsoft.com/en-us/library/dn554323.aspx" target="_blank">https://technet.microsoft.com/en-us/library/dn554323.aspx</a>'+
									'</p>').dialog({
									modal:true,
									title:'Confirm settings',
									width:'50%',
									buttons: [
										{ text: 'Cancel', click: function(){ jQuery(this).dialog('close'); } },
										{ text: 'Override', click: function(){ jQuery('button[type=submit][clicked=true]').click(); } }
									],
									close: function(){ jQuery(this).dialog("destroy"); }
								}).parent().appendTo('.wppt_emailer');
							}
						}
					}
					// Exit with our status, true means continue, false means don't submit
					return settingsGood;
				});
			
			});
			
			// AJAX call to display contents of a log file
			function showlog( log ) {
				jQuery('<pre style="word-wrap:break-word;white-space:pre-wrap"></pre>').load('<?=get_site_url()?>/wp-admin/admin-ajax.php?action=wppt_emailer_logfile&log='+log).dialog({
					title: "Logfile: "+log,
					modal: true,
					width: "80%",
					height:"500",
					close: function(){ jQuery(this).dialog("destroy"); }
				}).parent().css({ zIndex:10000 }).appendTo('.wppt_emailer');
			}
		</script>
		<?php
		echo '</div>';
	}
	