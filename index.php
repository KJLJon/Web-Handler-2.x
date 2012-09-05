<?php define('KJL_WEB', TRUE);
//defaults display off (just in case the database doesn't load and there is an error
ini_set('display_errors', 0);
ini_set('log_errors', 1); 
ini_set('error_log', 'errors/log.inc.php'); 
error_reporting(E_ALL);

$url = ( isset($_GET['p']) && $_GET['p'] != '' ) ? $_GET['p'] : '/';

$core=array('mysql' => array('host' => '','user' => '','password' => '','database' => '','debug' => FALSE), 'install' => true);

ob_start();
@include('settings.inc.php');
require('core/dbMySQL.inc.php');	//$db
require('core/settings.inc.php');	//settings stored in db
settings::get('core', true);		//sets the rest of the core data
settings::defaults();
require('core/sessions.inc.php');	//$sess and $user
require('core/base.inc.php');		//base functions
ob_end_clean();

function __autoload($name) {
	require('mods/'. $name .'.inc.php');
}

//BEGIN CUSTOM MODS
	//delete after login form created
	//require('mods/auth_guest.inc.php');	//gives guest users full permissions
	
	/*/	CACHE CONTROLS
 		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
	//  END CACHE CONTROLS */
//END CUSTOM MODS

//loads files
ob_start();
$_r = $db->q('SELECT `file` FROM `url` WHERE ? LIKE `url` LIMIT 1','s',$url);
if(isset($_r[0]['file'])){
	if(is_file($_r[0]['file'])){
		//include page
		include($_r[0]['file']);
	}else{
		$url = '/';
		$_r = $db->q('SELECT `file` FROM `url` WHERE `url` = "/" LIMIT 1');
		//supresses errors if the file doesn't exist or database entry is not there.
		@include($_r[0]['file']);
	}
}else{
	//supresses errors if the file doesn't exist or database entry is not there.
	if($url == '500.inc.php'){
		@include('errors/500.inc.php');
	}else{
		@include('errors/404.inc.php');
	}
}
$theme['body'] = ob_get_contents();
ob_end_clean();

//prints data
if(empty($core['theme']) || strpos($core['theme'],'/') !== false){
	echo $theme['body'];
}else{
	@include('themes/'.$core['theme'].'/theme.inc.php');
}
?>