<?php defined('KJL_WEB') or die('Access Denied.');
global $core,$db,$sess;
$_r = $db->q('SELECT `file` FROM `url` WHERE `url` = "/" LIMIT 1');
$url = '/';
if(isset($_r[0]['file'])){
	$sess->redirect($core['website'], true);
}else{
?><html>
<head>
<title>Error</title>
</head>
<body>
<h1>Error: Corrupt Settings</h1>
<h2>to fix the error delete 'core/settings.inc.php' and reload this page</h2>
</body>
</html>
<?php } ?>