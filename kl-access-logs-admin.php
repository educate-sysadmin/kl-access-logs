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
        if ($_GET['download'] =="xlsx") {  
            header("Content-type: application/x-msdownload",true,200);
            header("Content-Disposition: attachment; filename=kl_access_log.xlsx");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo klal_get_logs();
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
	
	// thanks https://stackoverflow.com/questions/8597846/wordpress-plugin-call-function-on-button-click-in-admin-panel
	// handle admin requests
	if (isset($_POST['klal_archive']) && check_admin_referer('klal_archive_nonce')) {
	    // todo validate klal_archive_date
    	echo '<p>Archiving logs...</p>';
    	//$wpdb->show_errors(); // debug only not production
    	
        // copy into archive
        $insert_sql = "INSERT INTO ".$klal_table_name_archive." ( SELECT * FROM ".$klal_table_name." WHERE ".$klal_table_name.".datetime < '".$_POST['klal_archive_date']."');";
        
        $insert_result = $wpdb->query( 
            $insert_sql
		);
		
		// delete from current log
		if ($insert_result) {
		    $delete_sql = "DELETE FROM ".$klal_table_name." WHERE datetime < '".$_POST['klal_archive_date']."';";
		
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
  	}
	
    // show logs
    echo '<h2>Current logs</h2>';
    echo klal_get_logs();
    // few admin options
    echo '<p>';
    echo '<a href="">'.'Refresh'.'</a>';
    echo '&nbsp|&nbsp';
    echo '<a href="'.$_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING'].'&download=xlsx'.'" target="_blank">'.'Download .xlsx'.'</a>';
    echo '&nbsp|&nbsp';
    echo '<a href="'.$_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING'].'&download=clf'.'" target="_blank">'.'Download CLF'.'</a>';
    echo '</p>'	;
    
    // admin options
    echo '<h2>Archive logs</h2>';
    echo '<form action="" method="post">';
	// nonce: this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
	wp_nonce_field('klal_archive_nonce');
  	echo '<input type="hidden" value="true" name="klal_archive" />';  	
  	echo '<label for = "klal_archive_date">Archive logs prior to timestamp:</label>'.'&nbsp;';
  	$default_timestamp = date("Y-m")."-"."01"." "."00:00:00";
  	echo '<input type="text" value="'.$default_timestamp.'" name="klal_archive_date" id="klal_archive_date" />';
  	submit_button('Archive now');
    echo '</form>';    
    
	echo '</div>'."\n"; // class="wrap
        
}

