<?php defined('KJL_WEB') or die('Access Denied.');
if($user->logged_in){
	echo 'Hello ', $user->name;
}else{
	echo 'Hello World!';
}