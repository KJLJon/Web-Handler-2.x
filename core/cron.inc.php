<?php defined('KJL_WEB') or die('Access Denied.');
$r = $db->q('SELECT `file` FROM `cron`');
foreach ($r as $v){
	@include($v['file']);
}
?>