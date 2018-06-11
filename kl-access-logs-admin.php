<?php
/*
KL Access Logs Admin
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/
add_action('admin_menu', 'klal_plugin_menu');

 
function klal_plugin_menu(){
        add_menu_page( 'KL Access Logs Admin', 'KL Access Logs', 'manage_options', 'kl-access-logs-plugin', 'klal_admin_init' );
}

// handle downloading
add_action('admin_init','klal_requests');
function klal_requests() {
    if (isset($_GET['download'])) {
        if ($_GET['download'] =="csv") {  
            header("Content-type: application/x-msdownload",true,200);
            header("Content-Disposition: attachment; filename=kl_access_log.csv");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo klal_get_logs_csv();
            exit();
        }
        if ($_GET['download'] =="clf") {  
            header("Content-type: application/text",true,200);
            header("Content-Disposition: attachment; filename=kl_access.log");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo klal_get_logs_clf();
            exit();
        }        
    }
}

/* helper: get user_id for username */
function klal_get_id_for_username($username) {
    global $wpdb;    
    $sql = 'SELECT ID FROM '.$wpdb->prefix.'users WHERE user_login = "'.$username.'"';
	$result = $wpdb->get_row($sql);
    return $result->ID;
}

/* temp helper function to add groups to existing records */
function klal_migrate_groups() {

    global $wpdb;
    global $klal_table_name;

	$result = $wpdb->get_results( 
		"SELECT * FROM ".$klal_table_name." ORDER BY datetime DESC;"
	);
    
	if ($result) {
    	foreach ($result as $row) {
    	    $user_id = klal_get_id_for_username($row->userid);
	        $groups = implode(",",klal_get_user_groups(get_option('klal_add_groups'), $user_id));
	        $sql = 'UPDATE '.$klal_table_name.' SET groups = "'.$groups.'" WHERE id = '.$row->id;
	        $wpdb->query( $sql );
    	}    	
    }    
}
// option to call
//add_action('admin_init','klal_migrate_groups');

function klal_get_logs() {
	global $wp, $wpdb;
	global $klal_table_name;
	
	$return = "";
	
	// get field details for table heading
	$describe = $wpdb->get_results( 
		"DESCRIBE ".$klal_table_name.";"
	);	
	if (!$describe) return;
	
	$return .= '<table id = "kl_access_logs" class="kl_access_logs datatable">'."\n";
	$return .= '<thead>'."\n";
	$return .= '<tr>'."\n";	
	foreach ($describe as $field) {
	    $return .= '<th>'.$field->Field.'</th>';
	}
	$return .= '</tr>'."\n";	
	$return .= '</thead>'."\n";		
	
	$return .='<tbody>'."\n";	
	$result = $wpdb->get_results( 
		"SELECT * FROM ".$klal_table_name." ORDER BY datetime DESC;"
	);
    
	if ($result) {
	foreach ($result as $row) {
    	$return .= '<tr>'."\n";		
    	foreach ($row as $key => $val) {
    	    $return .= '<td class = "kl_access_logs_'.$key.'">';
    	    $return .= $val;
    	    $return .= '</td>';
    	}
        $return .= '</tr>'."\n";		    
	}
	
	} else {
		//echo '<p>No results.</p>';
	}
	$return .= '</tbody>'."\n";	
	$return .= '</table>'."\n";	
	
	return $return;
}


function klal_get_logs_csv() {
	global $wp, $wpdb;
	global $klal_table_name;
	
	$return = "";
	
	$result = $wpdb->get_results( 
		"SELECT * FROM ".$klal_table_name." ORDER BY datetime DESC;"
	);
	
	return klutil_array_to_csv($result);    
}

/* helper to convert array to csv */
if (!function_exists('klutil_array_to_csv')) {
    function klutil_array_to_csv($array) {
        $output = '';
        // field names row
        $firstrow = $array[0];        
        $record = '';
        foreach ($firstrow as $key => $_) {
            $record .= $key.',';
        }
        if (substr($record,strlen($record)-1) == ',') {
            $record = substr($record,0,strlen($record)-1); // remove final comma
        }
        $output .= $record."\n";
        
        // data rows
        foreach ($array as $row) {
            $record = '';
            foreach ($row as $key => $val) {
                $record .= $val.',';
            }
            if (substr($record,strlen($record)-1) == ',') {
                $record = substr($record,0,strlen($record)-1); // remove final comma
            }
            $output .= $record."\n";
        }
        return $output;             
    }
}

function klal_get_logs_clf() {
    // ref https://docstore.mik.ua/orelly/webprog/pcook/ch11_14.htm
    // e.g. 127.0.0.1 - frank [10/Oct/2000:13:55:36 -0700] "GET /apache_pb.gif HTTP/1.0" 200 2326 "http://www.example.com/start.html" "Mozilla/4.08 [en] (Win98; I ;Nav)"
    
	global $wp, $wpdb;
	global $klal_table_name;	
	
	$return = "";
	
	$result = $wpdb->get_results( 
		"SELECT * FROM ".$klal_table_name.";"
	);
    
	if ($result) {
	    foreach ($result as $row) {
        	$return .= $row->remote_host.' '.$row->client.' '.$row->userid.' '.$row->time.' '.'"'.$row->method.' '.$row->request.' '.$row->protocol.'"'.' '.$row->status.' '.$row->size.' '.'"'.$row->referer.'"'.' '.'"'.$row->useragent.'"'."\n";
	    }
	
	} else {
		//echo '<p>No results.</p>';
	}
	
	return $return;
}
 
function klal_admin_init(){
	global $wp, $wpdb;
	global $klal_table_name, $klal_table_name_archive;

	echo '<div class="wrap">'."\n";
	
	echo "<h1>KL Access Logs</h1>";
	
	// handle selecting table
	if (isset($_POST['klal_table']) /*&& check_admin_referer('klal_table_nonce')*/) {
	    if (in_array($_POST['klal_table'], explode(',',get_option('klal_tables')))) {
	         $klal_table_name = $wpdb->prefix.$_POST['klal_table'];
	    }
	}
	
	echo '<h2 style="display: inline:">'.$klal_table_name.'</h2>';
	
	// provide table selection
    $tables = explode(',',get_option('klal_tables'));
    echo '<form action="" method="post" style="display: inline;">';
	//wp_nonce_field('klal_table_nonce');
	echo '<select name = "klal_table" id = "klal_table_select">'."\n";
    foreach ($tables as $table) {               
        echo '<option value = "'.$table.'"';
        if ($wpdb->prefix.$table == $klal_table_name) { echo ' selected '; }
        echo '>';
        echo $table;
        echo '</option>'."\n";
    }	
    echo '</select>'."\n";
    // submit_button('change');
    echo '<input name="submit" id="submit" class="button button-primary" value="change" type="submit">'."\n";
    echo '</form>';
	
	// thanks https://stackoverflow.com/questions/8597846/wordpress-plugin-call-function-on-button-click-in-admin-panel
	// handle admin requests
	if (isset($_POST['klal_archive']) && check_admin_referer('klal_archive_nonce')) {
	    // todo validate klal_archive_date
	    
	    if (isset($_POST['klal_table_name_from']) && isset($_POST['klal_table_name_from'])
	        && in_array($_POST['klal_table_name_from'], explode(',',get_option('klal_tables')))
	        && in_array($_POST['klal_table_name_to'], explode(',',get_option('klal_tables')))
	        && $_POST['klal_table_name_from'] != $_POST['klal_table_name_to']
	    ) {
	    
	        $klal_table_name_from = $wpdb->prefix.$_POST['klal_table_name_from'];
	        $klal_table_name_to = $wpdb->prefix.$_POST['klal_table_name_to'];	        
	    
        	echo '<p>Archiving logs (from '.$klal_table_name_from.' to '.$klal_table_name_to.')'.'...</p>';
        	//$wpdb->show_errors(); // debug only not production
        	
            // copy into archive
            $insert_sql = "INSERT INTO ".$klal_table_name_to." ( SELECT * FROM ".$klal_table_name_from." WHERE ".$klal_table_name_from.".datetime < '".$_POST['klal_archive_date']."');";
            
            $insert_result = $wpdb->query( 
                $insert_sql
		    );
		
		    // delete from current log
		    if ($insert_result) {
		        $delete_sql = "DELETE FROM ".$klal_table_name_from." WHERE datetime < '".$_POST['klal_archive_date']."';";
		
			    $delete_result = $wpdb->query( 			
			        $delete_sql
			    );
			    if ($delete_result) {
				    echo '<p>'.'Done'.'</p>';
			    } else {
				    echo '<p>'.'Error clearing current log table'.'</p>';
			    }
		    } else {
		        	echo '<p>'.'No records to archive or error populating archive table'.'</p>';
		    }
		} else {
		    echo '<p>Invalid request</p>';
		}
  	}
	
    // show logs
    echo '<h2>Current logs</h2>';
    echo '<p><a href="">'.'Refresh'.'</a></p>';    
    echo klal_get_logs();
    
    // admin options
    echo '<p>';
    echo '<a href="">'.'Refresh'.'</a>';
    echo '&nbsp|&nbsp';
    $url = $_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING'];
    // fix for wordpress.com
    if (strpos($url,'__wp__/') !== false) { $url = str_replace("__wp__/","",$url); }
    echo '<a href="'.$url.'&download=csv'.'" target="_blank">'.'Download .csv'.'</a>';
    echo '&nbsp|&nbsp';
    echo '<a href="'.$url.'&download=clf'.'" target="_blank">'.'Download CLF'.'</a>';
    echo '</p>'	;
    
    echo '<h2>Archive logs</h2>';
    echo '<form action="" method="post">';
	// nonce: this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
	wp_nonce_field('klal_archive_nonce');	
  	echo '<input type="hidden" value="true" name="klal_archive" />';  	
  	// remember selected table
    echo '<input type="hidden" value="'.(str_replace($wpdb->prefix,'',$klal_table_name)).'" name="klal_table" />';  	  	     	
  	
  	// from / to tables
    $tables = explode(',',get_option('klal_tables'));
	echo 'From: '.'<select name = "klal_table_name_from">'."\n";
    foreach ($tables as $table) {               
        echo '<option value = "'.$table.'"';
        if ($wpdb->prefix.$table == $klal_table_name) { echo ' selected '; }
        echo '>';
        echo $table;
        echo '</option>'."\n";
    }	
    echo '</select>'."\n";
	echo 'From: '.'<select name = "klal_table_name_to">'."\n";
    foreach ($tables as $table) {               
        echo '<option value = "'.$table.'"';
        if ($wpdb->prefix.$table == $klal_table_name_archive) { echo ' selected '; }
        echo '>';
        echo $table;
        echo '</option>'."\n";
    }	
    echo '</select>'."\n";

    echo '<br/>';
    // date range
  	echo '<label for = "klal_archive_date">Archive logs prior to timestamp:</label>'.'&nbsp;';
  	$default_timestamp = date("Y-m")."-"."01"." "."00:00:00";
  	echo '<input type="text" value="'.$default_timestamp.'" name="klal_archive_date" id="klal_archive_date" />';
  	submit_button('Archive now');
    echo '</form>';    
    
	echo '</div>'."\n"; // class="wrap
        
}
