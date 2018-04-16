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

function klal_show_logs() {
	global $wp, $wpdb;
	global $klal_table_name;
	
	$result = $wpdb->get_results( 
		"SELECT clf FROM ".$klal_table_name.";"
	);

	if ($result) {
	    echo '<textarea class="kl_access_logs" style="width: 98%;" rows="20" readonly>'."\n"; 
		foreach ( $result as $row )	{
			echo $row->clf."\n";
		}
	    echo '</textarea>'."\n";
	} else {
		echo '<p>No results.</p>';
	}
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

		$sql = "SELECT clf FROM ".$klal_table_name." WHERE timestamp < '".$_POST['klal_archive_date']."'".";";
    	$result = $wpdb->get_results( 
			$sql
		);

		if ($result) {
			// insert rows into archive table			
			foreach ($result as $row) {
				$insert_result = $wpdb->insert( 
					$klal_table_name_archive, 
					array( 
						'clf' => $row->clf, 
					),	
					array( 
						'%s', 
					)
				);
				// if error, stop
				if (!$insert_result) {
					echo '<p>'.'Error updating archive table'.'</p>';
					break;
				}
			}
			// delete from current log
			if ($insert_result) {
				$delete_result = $wpdb->query( 
					"
                	DELETE FROM ".$klal_table_name."
 					 WHERE timestamp < '".$_POST['klal_archive_date']."';" 
				);
				if ($delete_result) {
					echo '<p>'.'Done'.'</p>';
				} else {
					echo '<p>'.'Error clearning current log table'.'</p>';
				}
			}
  		} else {
  			echo '<p>'.'Error querying current log or no logs to archive'.'</p>';
  		}
  	}
	
    // show logs
    echo '<h2>Current logs</h2>';
    klal_show_logs();
    echo '<p>'.'<a href="">'.'Refresh'.'</a>'.'</p>'	;
    
    // admin options
    echo '<h2>Archive logs</h2>';
    echo '<form action="" method="post">';
	// this is a WordPress security feature - see: https://codex.wordpress.org/WordPress_Nonces
	wp_nonce_field('klal_archive_nonce');
  	echo '<input type="hidden" value="true" name="klal_archive" />';  	
  	echo '<label for = "klal_archive_date">Archive logs prior to timestamp:</label>'.'&nbsp;';
  	$default_timestamp = date("Y-m")."-"."01"." "."00:00:00";
  	echo '<input type="text" value="'.$default_timestamp.'" name="klal_archive_date" id="klal_archive_date" />';
  	submit_button('Archive now');
    echo '</form>';    
    
	echo '</div>'."\n"; // class="wrap
        
}
