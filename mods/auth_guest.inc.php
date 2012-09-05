<?php defined('KJL_WEB') or die('Access Denied.');
class auth_guest extends session {
	public function __construct(){
	}
	
	public function has_perm($perm){
		return true;
	}
}
if(!$user->logged_in){
	$sess = new auth_guest;
	$user->logged_in=true;
}
?>