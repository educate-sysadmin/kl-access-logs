<?php
/*
KL Access Logs Settings
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

// create custom plugin settings menu
add_action('admin_menu', 'klal_plugin_create_menu');

function klal_plugin_create_menu() {
	//create options page
	add_options_page('KL Access Logs Plugin Settings', 'KL Access Logs', get_option('klal_admin_capability')?get_option('klal_admin_capability'):'manage_options', __FILE__, 'klal_plugin_settings_page' , __FILE__ );

	//call register settings function
	add_action( 'admin_init', 'register_klal_plugin_settings' );
}

function register_klal_plugin_settings() {
	//register our settings
	register_setting( 'klal-plugin-settings-group', 'klal_tables' ); 	
	register_setting( 'klal-plugin-settings-group', 'klal_posts_filter_false');
	register_setting( 'klal-plugin-settings-group', 'klal_posts_filter_true' );
    register_setting( 'klal-plugin-settings-group', 'klal_store_wp_content' );
	register_setting( 'klal-plugin-settings-group', 'klal_roles_filter_false');
	register_setting( 'klal-plugin-settings-group', 'klal_roles_filter_true' );
	register_setting( 'klal-plugin-settings-group', 'klal_ip_filter_false');	
	register_setting( 'klal-plugin-settings-group', 'klal_salt');
	register_setting( 'klal-plugin-settings-group', 'klal_hide_userid' );
	register_setting( 'klal-plugin-settings-group', 'klal_hide_ip' );	
	register_setting( 'klal-plugin-settings-group', 'klal_store_useragent' );
	register_setting( 'klal-plugin-settings-group', 'klal_add_groups' );	
	register_setting( 'klal-plugin-settings-group', 'klal_add_roles' );		
	register_setting( 'klal-plugin-settings-group', 'klal_admin_capability' );	
}

function klal_plugin_settings_page() {
?>
<div class="wrap">
<h1>KL Access Logs Settings</h1>

<form method="post" action="options.php">
    <?php settings_fields( 'klal-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'klal-plugin-settings-group' ); ?>
    <p>Filters are evaluated in order.</p>
    <table class="form-table">
    
        <tr valign="top">
        <th scope="row">Log tables</th>
        <td>
        	<input type="text" name="klal_tables" value="<?php echo esc_attr( get_option('klal_tables') ); ?>"  size = "80" />
        	<p><small>Allowed tables to use for logs (comma-delimited, leave out wp_ prefix).</small></p>
        </td>
        </tr>    
        
        <tr valign="top">
        <th scope="row">Post(s) to NOT log</th>
        <td>
        	<input type="text" name="klal_posts_filter_false" value="<?php echo esc_attr( get_option('klal_posts_filter_false') ); ?>"  size = "80" />
        	<p><small>If set, does not log for matching URL portions. Separate multiple options with commas.</small></p>
        </td>
        </tr>

        <tr valign="top">
        <th scope="row">Post(s) to log</th>
        <td>
        	<input type="text" name="klal_posts_filter_true" value="<?php echo esc_attr( get_option('klal_posts_filter_true') ); ?>"  size = "80" />
        	<p><small>If set, only stores logs for matching URL portions. Separate multiple options with commas.</small></p>
        </td>
        </tr>

    	<tr valign="top">
        <th scope="row">Log wp-content file accesses</th>
        <td><input type="checkbox" name="klal_store_wp_content" value="true" <?php if ( get_option('klal_store_wp_content') ) echo ' checked '; ?> /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Role(s) to NOT log</th>
        <td>
        	<input type="text" name="klal_roles_filter_false" value="<?php echo esc_attr( get_option('klal_roles_filter_false') ); ?>" size = "80" />
        	<p><small>If set, does not log for matching roles. Separate multiple options with commas.</small></p>        	
        </td>
        </tr>

        <tr valign="top">
        <th scope="row">Role(s) to log</th>
        <td>
        	<input type="text" name="klal_roles_filter_true" value="<?php echo esc_attr( get_option('klal_roles_filter_true') ); ?>" size = "80" />
        	<p><small>If set, only stores logs for matching roles. Separate multiple options with commas.</small></p>        	
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">IPs to NOT log</th>
        <td>
        	<input type="text" name="klal_ip_filter_false" value="<?php echo esc_attr( get_option('klal_ip_filter_false') ); ?>" size = "80" />
        	<p><small>If set, does not log for matching IP addresses (pre or post obfuscation values accepted). Separate multiple options with commas.</small></p>        	
        </td>
        </tr>        

        <tr valign="top">
        <th scope="row">Salt</th>
        <td>
        	<input type="text" name="klal_salt" value="<?php echo esc_attr( get_option('klal_salt') ); ?>" size = "80" />
        	<p><small>Enter a random string salt value to strengthen obfuscations. Keep a copy if used.</small></p>
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Obfuscate Userids</th>
        <td><input type="checkbox" name="klal_hide_userid" value="true" <?php if ( get_option('klal_hide_userid') ) echo ' checked '; ?> /></td>
        </tr>
        
    	<tr valign="top">
        <th scope="row">Obfuscate IP Addresses</th>
        <td><input type="checkbox" name="klal_hide_ip" value="true" <?php if ( get_option('klal_hide_ip') ) echo ' checked '; ?> /></td>
        </tr>
        
    	<tr valign="top">
        <th scope="row">Store User agent</th>
        <td><input type="checkbox" name="klal_store_useragent" value="true" <?php if ( get_option('klal_store_useragent') ) echo ' checked '; ?> /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Add groups (KL-specific)</th>
        <td>
        	<input type="text" name="klal_add_groups" value="<?php echo esc_attr( get_option('klal_add_groups') ); ?>" size = "80" />
        	<p><small>Populate a column including user groups that loosely match these comma-delimied values.</small></p>
        </td>
        </tr>        
        
        <tr valign="top">
        <th scope="row">Add roles (KL-specific)</th>
        <td>
        	<input type="text" name="klal_add_roles" value="<?php echo esc_attr( get_option('klal_add_roles') ); ?>" size = "80" />
        	<p><small>Populate a column including user roles that loosely match these comma-delimied values.</small></p>
        </td>
        </tr>                
        
        <tr valign="top">
        <th scope="row">Admin access</th>
        <td>
        	<input type="text" name="klal_admin_capability" value="<?php echo esc_attr( get_option('klal_admin_capability') ); ?>" size = "30" />
        	<p><small>Capability to check for to view logs in admin</small></p>
        </td>
        </tr>            
        
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php } ?>
