<?php defined('KJL_WEB') or die('Access Denied.');
$db->q('DELETE FROM `user_session` WHERE TIME_TO_SEC(TIMEDIFF(NOW(),`sessTime`)) > ? OR `sessTime` = 0','i',$core['session_length']);
?>