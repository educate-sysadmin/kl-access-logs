<?php
/*
Plugin Name: KL Access Logs
Plugin URI: https://github.com/educate-sysadmin/kl-access-logs
Description: Save modified (Combined) Common Log Format access logs in database. With KL-specific options.
Version: 0.5
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

$klal_db_version = "0.4";
// default table names
$klal_table_name = $wpdb->prefix . "kl_access_logs";
$klal_table_name_archive = $klal_table_name . "_archive";

require_once('kl-access-logs-options.php');
require_once('kl-access-logs-admin.php');

// create or update database table
function klal_install () {
	global $wpdb;
	global $klal_db_version, $klal_table_name, $klal_table_name_archive;

    // setup database
    // TODO
    /*
SET NAMES utf8;
SET time_zone = '+00:00';

CREATE TABLE `wp_kl_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remote_host` varchar(32) NOT NULL,
  `client` varchar(1) NOT NULL,
  `userid` varchar(128) NOT NULL,
  `groups` varchar(128) NULL,  
  `roles` varchar(128) NULL,    
  `category1` varchar(128) NULL,    
  `category2` varchar(128) NULL,      
  `time` varchar(32) NOT NULL,
  `datetime` datetime DEFAULT NULL,
  `method` varchar(8) NOT NULL,
  `request` varchar(256) NOT NULL,
  `protocol` varchar(8) NOT NULL,
  `status` varchar(8) NOT NULL,
  `size` int(11) NOT NULL DEFAULT '0',  
  `referer` varchar(256) NOT NULL,
  `useragent` varchar(256) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `request` (`request`),
  KEY `remote_host` (`remote_host`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/

/*
CREATE TABLE `wp_kl_access_logs_archive` (
  `id` int(11) NOT NULL,
  `remote_host` varchar(32) NOT NULL,
  `client` varchar(1) NOT NULL,
  `userid` varchar(128) NOT NULL,
  `groups` varchar(128) NULL,
  `roles` varchar(128) NULL,  
  `category1` varchar(128) NULL,    
  `category2` varchar(128) NULL,        
  `time` varchar(32) NOT NULL,
  `datetime` datetime DEFAULT NULL,
  `method` varchar(8) NOT NULL,
  `request` varchar(256) NOT NULL,
  `protocol` varchar(8) NOT NULL,
  `status` varchar(8) NOT NULL,
  `size` int(11) NOT NULL DEFAULT '0',
  `referer` varchar(256) DEFAULT NULL,
  `useragent` varchar(256) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `request` (`request`),
  KEY `remote_host` (`remote_host`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/

	/*
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
    add_option("klal_db_version", $klal_db_version);
    */

    // set defaults for options
    if (!get_option('klal_tables')) { update_option('klal_tables','kl_access_logs,kl_access_logs_archive'); }
    if (!get_option('klal_posts_filter_false')) { update_option('klal_posts_filter_false','wp-admin,wp-login.php,login,logout, wp-content'); }
    if (!get_option('klal_roles_filter_false')) { update_option('klal_roles_filter_false','administrator'); }
    if (!get_option('klal_admin_capability')) { update_option('klal_admin_capability','manage_options'); } 
}

/* helper to get array of users' groups, with filter */
function klal_get_user_groups($filter, $user_id) {
    global $wpdb;

    // ref http://docs.itthinx.com/document/groups/api/examples/
    $groups = array(); // to populate
    $groups_user = new Groups_User( $user_id );
    // get group objects
    $user_groups = $groups_user->groups;
    // get group ids (user is direct member)
    $user_group_ids = $groups_user->group_ids;
    // get group ids (user is direct member or by group inheritance)
    $user_group_ids_deep = $groups_user->group_ids_deep;
    foreach ($user_group_ids_deep as $group_id) {
	    $sql = 'SELECT name FROM '.$wpdb->prefix .'groups_group WHERE group_id='.$group_id;
	    $row = $wpdb->get_row( $sql );
	    $group_name = $row->name;
        $filter_groups = explode(",",$filter);
        foreach ($filter_groups as $filter_group) {
	        if (strpos($group_name,$filter_group) !== false) { 
        	    $groups[] = $group_name;
        	}
        }
    }
    return $groups;
}

/* helper to get array of users' roles, with filter */
function klal_get_user_roles($filter, $user_id) {
    $roles = array();
    $user = get_user_by( 'id', $user_id);    
    if ( !($user instanceof WP_User)) {
        $roles = array('visitor');
    } else {
       	$roles = $user->roles;
    }
    $return_roles = array();
    $filter_roles = explode(",",$filter);    
    foreach ($roles as $role) {
        foreach ($filter_roles as $filter_role) {
            if (strpos($role,$filter_role) !== false) { 
        	    $return_roles[] = $role;
        	}
        }    
    }
    return $return_roles; 
}

/* helper to post id from url i.e. (final part of url) */
function klal_get_post_id($url) {

    global $wpdb;

    $post_name = basename($url);
    
	$result = $wpdb->get_row( 
		'SELECT ID FROM '.$wpdb->prefix.'posts '.' WHERE post_name = "'.$post_name.'"'
	);
    
    if ($result) { return $result->ID; } else { return false; }    
}

/* helper to get array of post categories, with filter */
function klal_get_categories($filter, $post_id) {    
    $categories_ids = wp_get_post_categories($post_id);
    $categories = array();
    foreach ($categories_ids as $categories_id) {
        $cat = get_category( $categories_id );
        if (in_array($cat->name, explode(",",$filter))) {
            $categories[] = $cat->name;
        }
    }    
    return $categories;
}

// handle the tracking
function klal_track () {

	global $wp, $wpdb;
	global $klal_table_name;

    try {
    
		$klal_filter = apply_filters('klal_pre', array('context'=>'klal_pre'));
					
	    // check filters
	    $track = true;
	    
	    // ip filters	    
	    $klal_ip_filter_false = get_option('klal_ip_filter_false');
		if ($klal_ip_filter_false) {	    
		    $ip = $_SERVER['REMOTE_ADDR'];
		    $ip_var = (get_option('klal_hide_ip'))?md5($ip . get_option('klal_salt')):null;			
		    $klal_ip_filters_false = explode(",",$klal_ip_filter_false);	
		    foreach ($klal_ip_filters_false as $ip_filter_false) {
			    if ($ip == $ip_filter_false || ($ip_var && $ip_var == $ip_filter_false)) {
				    $track = false; break;				
			    }
		    }
		}
	    if (!$track) return;					

        // url filters
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
	    
		$klal_filter = apply_filters('klal_track', array('context'=>'klal_track'));	    
				
	    // save as very modified Combined CLF 
	    // e.g. 127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /apache_pb.gif HTTP/1.0" 200 2326 "http://www.example.com/start.html" "Mozilla/4.08 [en] (Win98; I ;Nav)"	    
	    $remote_host = $_SERVER['REMOTE_ADDR'];
	    if (get_option('klal_hide_ip')) {
		    $remote_host = md5($remote_host . get_option('klal_salt'));	
	    }
	    $client = "-"; 
	    $user = wp_get_current_user(); // use wordpress user	
	    $userid = $user?$user->user_login:null;
	    
	    // merge groups
	    $groups_field = array();
	    if (get_option('klal_add_groups') && get_option('klal_add_groups') != '') {	    
	        $groups_field = implode(",",klal_get_user_groups(get_option('klal_add_groups'), get_current_user_id()));
	    }
	    
	    // merge roles
	    $roles_field = array();
	    if (get_option('klal_add_roles') && get_option('klal_add_roles') != '') {	    
	        $roles_field = implode(",",klal_get_user_roles(get_option('klal_add_roles'), get_current_user_id()));
	    }
	    
	    // merge categories
        if (get_option('klal_add_category_1') && get_option('klal_add_category_1') != '') {
    	    $post_id = klal_get_post_id($url);
            $category1 = implode(",",klal_get_categories( get_option('klal_add_category_1'), $post_id));
        }            	    
        if (get_option('klal_add_category_2') && get_option('klal_add_category_2') != '') {
    	    if (!$post_id) { $post_id = klal_get_post_id($url); }
            $category2 = implode(",",klal_get_categories( get_option('klal_add_category_2'), $post_id));
        }

	    if (get_option('klal_hide_userid')) {
		    $userid= md5($userid . get_option('klal_salt'));
	    }
	    $time = '['.date("d/M/Y:H:i:s O").']';
	    $datetime = date("Y-m-d H:i:s");
	    $method = $_SERVER['REQUEST_METHOD'];
	    $request = $_SERVER['REQUEST_URI'];
	    $protocol = $_SERVER['SERVER_PROTOCOL'];
	    $status = "200"; // todo?
	    $size = 0; // todo
	    $referer = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'';
	    if (get_option('klal_store_useragent')) {
		    $useragent = $_SERVER['HTTP_USER_AGENT'];
	    } else {
		    $useragent = "-";
	    }

       	//$wpdb->show_errors(); // debug only not production		
	    $result = $wpdb->insert( 
		    $klal_table_name, 
		    array( 
			    'remote_host' => $remote_host, 
			    'client' => $client,
			    'userid' => $userid,
			    'groups' => $groups_field,
			    'roles' => $roles_field,			    
			    'category1' => $category1,
			    'category2' => $category2,
			    'time' => $time,
			    'datetime' => $datetime,
			    'method' => $method,
			    'request' => $request,
			    'protocol' => $protocol,
			    'status' => $status,
			    'size' => $size,
			    'referer' => $referer,
			    'useragent' => $useragent
		    ),	
		    array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s')
	    );
	    
		$klal_filter = apply_filters('klal_post', array('context'=>'klal_post'));	    
	    
    } catch (Exception $e) {
        return;
        //write_log($e->getMessage()."\n");
    }
}
		
// put it together
register_activation_hook( __FILE__, 'klal_install' );
add_action( 'init', 'klal_track' );
