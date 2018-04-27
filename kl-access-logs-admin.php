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
	
	// get field details for table heading
	$describe = $wpdb->get_results( 
		"DESCRIBE ".$klal_table_name.";"
	);	
	if (!$describe) return;
	
	echo '<table id = "kl_access_logs" class="kl_access_logs datatable">'."\n";
	echo '<thead>'."\n";
	echo '<tr>'."\n";	
	foreach ($describe as $field) {
	    echo '<th>'.$field->Field.'</th>';
	}
	echo '</tr>'."\n";	
	echo '</thead>'."\n";		
	
	echo '<tbody>'."\n";	
	$result = $wpdb->get_results( 
		"SELECT * FROM ".$klal_table_name.";"
	);
    
	if ($result) {
	foreach ($result as $row) {
    	echo '<tr>'."\n";		
    	foreach ($row as $key => $val) {
    	    echo '<td class = "kl_access_logs_'.$key.'">';
    	    echo $val;
    	    echo '</td>';
    	}
        echo '</tr>'."\n";		    
	}
	
	} else {
		//echo '<p>No results.</p>';
	}
	echo '</tbody>'."\n";	
	echo '</table>'."\n";	
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
    	
    	/*

		$sql = "SELECT * FROM ".$klal_table_name." WHERE timestamp < '".$_POST['klal_archive_date']."'".";";
    	$result = $wpdb->get_results( 
			$sql
		);
*/


		//if ($result) 
		//{
		/*
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
*/
            $insert_result = $wpdb->query( 
					"
                	INSERT INTO ".$klal_table_name_archive."
                	 ( SELECT * FROM ".$klal_table_name." 
 					 WHERE ".$klal_table_name.".timestamp < '".$_POST['klal_archive_date']."');" 
			);

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
  	}
	
    // show logs
    echo '<h2>Current logs</h2>';
    klal_show_logs();
    echo '<p>'.'<a href="">'.'Refresh'.'</a>'.'</p>'	;
    
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

