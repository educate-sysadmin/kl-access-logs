<?php
/*
Migration scripts
*/

/* 

CREATE TABLE wp_kl_access_logs_bak_20180702 SELECT * FROM wp_kl_access_logs;
*/

function klal_migrate_categories($atts, $content = null) {
    global $wpdb; 
    $table = 'wp_kl_access_logs';
    $result = $wpdb->get_results( 
		"SELECT * FROM ".$table.";"
	);
    
	if ($result) {
	    foreach ($result as $row) {
		  	//var_dump($row);
//	        echo (string) $row->id; echo (string)$row->request; echo ': ';
       	    $post_id = klal_get_post_id($row->request);
            $category1 = implode(",",klal_get_categories( get_option('klal_add_category_1'), $post_id));	    
            $category2 = implode(",",klal_get_categories( get_option('klal_add_category_2'), $post_id));
		  
		  	$sql = 'UPDATE '.$table.' SET category1 = "'.$category1.'", category2= "'.$category2.'" WHERE id = '.$row->id.'';
		    $wpdb->query($sql);
	    }	
    }
}	    
add_shortcode('klal_migrate_categories','klal_migrate_categories');
