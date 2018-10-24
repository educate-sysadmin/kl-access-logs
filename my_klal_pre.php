// store access logs for participants in dedicated table
function my_klal_pre() {
  
  global $klal_table_name;  
  
  $user = wp_get_current_user();
  if ( !($user instanceof WP_User)) {
	$user = "";
	$roles = array('visitor');
  } else {
	$roles = $user->roles;
  }

  if (in_array("educate_participant",$roles)) {
	$klal_table_name .= "_educate_participant";	
  }    
}

add_action('klal_pre','my_klal_pre');
