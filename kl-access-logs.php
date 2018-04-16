<?php
/*
Plugin Name: KL Access Logs
Plugin URI: https://github.com/educate-sysadmin/...
Description: Save (Combined) Common Log Format access logs in database
Version: 0.1.1
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

require_once('kl-access-logs-options.php');
require_once('kl-access-logs-admin.php');

$klal_db_version = "0.1.1";
$klal_table_name = $wpdb->prefix . "kl_access_log";
$klal_table_name_archive = $klal_table_name . "_archive";

// create or update database table
function klal_install () {
	global $wpdb;
	global $klal_db_version, $klal_table_name, $klal_table_name_archive;

    // setup database
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE ".$klal_table_name." (
  		id mediumint(9) NOT NULL AUTO_INCREMENT,
		clf text,
		timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset_collate;";


    $sql2 = 
	"CREATE TABLE ".$klal_table_name_archive." (
  		id mediumint(9) NOT NULL AUTO_INCREMENT,
		clf text,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	dbDelta( $sql2 );
    add_option("klal_db_version", $klal_db_version);

    // set defaults for options
    update_option('klal_posts_filter_false','wp-admin,wp-login.php,login,logout, wp-content');
    update_option('klal_roles_filter_false','administrator');

}
				
// handle the tracking
function klal_track () {

	global $wp, $wpdb;
	global $klal_table_name;

    try {
					
	    // check filters
	    $track = true;

        // get url
	    $url = $_SERVER['REQUEST_URI']; // $wp->request not available?

        // skip wp-content requests?
        if (strpos($url,'wp-content') !== false && !get_option('klal_store_wp_content')) {
            return;
        }

	    // posts filter false
	    $klal_posts_filter_false = get_option('klal_posts_filter_false');
	    if ($klal_posts_filter_false) {
		    $track = true;
		    $klal_posts_filters_false = explode(",",$klal_posts_filter_false);	
		    foreach ($klal_posts_filters_false as $posts_filter_false) {
			    if (strpos($url,$posts_filter_false) !== false ) {
				    $track = false; break;
			    }
		    }
	    }
	    if (!$track) return;
			
	    // posts filter true
	    $klal_posts_filter_true = get_option('klal_posts_filter_true');
	    if ($klal_posts_filter_true) {
		    $track = false;
		    $klal_posts_filters_true = explode(",",$klal_posts_filter_true);	
		    foreach ($klal_posts_filters_true as $posts_filter_true) {
			    if (strpos($url,$posts_filter_true) !== false ) {
				    $track = true; break;
			    }
		    }
	    }
	    if (!$track) return; 

        // get user and roles
	    $user = wp_get_current_user();
        if ( !($user instanceof WP_User)) {
            $user = "";
            $roles = array('visitor');
        } else {
           	$roles = $user->roles;
        }

	    // roles filter false
	    $klal_roles_filter_false = get_option('klal_roles_filter_false');
	    if ($klal_roles_filter_false) {			
		    $track = true;
		    if ($user) {
			    $klal_roles_filters_false = explode(",",$klal_roles_filter_false);	
			    foreach ($klal_roles_filters_false as $roles_filter_false) {
				    foreach ($roles as $role) {
					    if ($role == $roles_filter_false) {
						    $track = false; break; break;
					    }
				    }
			    }
		    }
	    }
	    if (!$track) return;
				
	    // roles filter true
	    $klal_roles_filter_true = get_option('klal_roles_filter_true');
	    if ($klal_roles_filter_true) {			
		    $track = false;
		    if ($user) {
			    $klal_roles_filters_true = explode(",",$klal_roles_filter_true);	
			    foreach ($klal_roles_filters_true as $roles_filter_true) {
				    foreach ($roles as $role) {
					    if ($role == $roles_filter_true) {
						    $track = true; break; break;
					    }
				    }
			    }
		    }
	    }
	    if (!$track) return;
				
	    // save as Combined CLF 
	    // e.g. 127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /apache_pb.gif HTTP/1.0" 200 2326 "http://www.example.com/start.html" "Mozilla/4.08 [en] (Win98; I ;Nav)"
	    $ip = $_SERVER['REMOTE_ADDR'];
	    if (get_option('klal_hide_ip')) {
		    $ip= md5($ip + get_option('klal_salt'));	
	    }
	    $client = "-"; 
	    $user = wp_get_current_user(); // use wordpress user	
	    $userid = $user?$user->user_login:null;
	    if (get_option('klal_hide_userid')) {
		    $userid= md5($userid + get_option('klal_salt'));
	    }
	    $time = date("d/M/Y:H:i:s O");
	    $request = $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.$_SERVER['SERVER_PROTOCOL'];
	    $status = "200"; // todo?
	    $size = 0; // todo
	    $referrer = $_SERVER['HTTP_REFERER'];
	    if (get_option('klal_store_useragent')) {
		    $useragent = $_SERVER['HTTP_USER_AGENT'];
	    } else {
		    $useragent = "-";
	    }
			
	    $clf = $ip.' '.$client.' '.$userid.' '.'['.$time.']'.' '.'"'.$request.'"'.' '.$status.' '.$size.' '.'"'.$referrer.'"'.' '.'"'.$useragent.'"';

	    $wpdb->insert( 
		    $klal_table_name, 
		    array( 
			    'clf' => $clf, 
		    ),	
		    array( 
			    '%s', 
		    )
	    );
    } catch (Exception $e) {
        return;
        // TODO error logging
        //echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
}
		
// put it together
register_activation_hook( __FILE__, 'klal_install' );
add_action( 'init', 'klal_track' );


