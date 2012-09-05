<?php defined('KJL_WEB') or die('Access Denied.');
//TODO Create First User (make the user SUPER ADMIN, so they have all permissions by default)
//TODO Scrub all POST data to make sure its safe (and w/o php, mysql, html exploits)
if(isset($_POST['mysql_host'])){
/*		Note:
			This is for a basic installation of KJL's Webhandler Functions
				This doesn't include any custom tables
*/
if($_POST['user_pass'] != $_POST['user_pass2']){
	__db_printInstall("Error: Admin Password didn't match");
}

$clean = array('salt'=>'string', 'mysql_host'=>'string',
	'mysql_user'=>'string', 'mysql_pass'=>'string',
	'mysql_db'=>'string', 'mysql_debug'=>'boolean',
);

foreach($clean as $var => $val){
	if($val === 'string'){
		$_POST[$var] = (string) str_replace('\'','\\\'',$_POST[$var]);
	}else{
		settype($_POST[$var], $val);
	}
}
$settings = <<<END
<?php defined('KJL_WEB') or die('Access Denied.');
\$core = array(
	//for storing passwords
	//	NOTE: IF SALT IS UPDATED IT WILL BREAK THE LOGIN AND USER TABLE
	'salt' => '{$_POST['salt']}',
	
	//mysql database information
	'mysql' => array(
		'host' => '{$_POST['mysql_host']}',
		'user' => '{$_POST['mysql_user']}',
		'password' => '{$_POST['mysql_pass']}',
		'database' => '{$_POST['mysql_db']}',
		'debug' => {$_POST['mysql_debug']}
	)
);
?>
END;

global $db;
//connects to the database
$db = new dbMySQL($_POST['mysql_host'], $_POST['mysql_user'], $_POST['mysql_pass'], $_POST['mysql_db'], FALSE, FALSE);

//makes sure its connected
if($db->is_connected()){
	//creates tables
	//checks to see if the tables have been created in the past
	$db->q('CREATE TABLE IF NOT EXISTS `stats_login_attempts` (
		`user_name` varchar(16) NOT NULL,
		`ip` varchar(15) NOT NULL,
		`logged_in` tinyint(1) NOT NULL,
		`dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`user_name`,`dt`)
		) ENGINE=MyISAM;');
	$db->q('CREATE TABLE IF NOT EXISTS `url` (
		`url` varchar(128) NOT NULL DEFAULT "",
		`file` varchar(128) DEFAULT NULL,
		`Description` varchar(255) NOT NULL,
		PRIMARY KEY (`url`)
		) ENGINE=MyISAM;');
	$db->q('INSERT IGNORE INTO `url` (`url`, `file`, `Description`) VALUES
		("/", "web/index.inc.php", "the default page to load"),
		("cron", "core/cron.inc.php", "the page that handles cron jobs");');
	$db->q('CREATE TABLE IF NOT EXISTS `cron` (
		`file` varchar(128) DEFAULT NULL,
		`Description` varchar(255) NOT NULL,
		PRIMARY KEY (`file`)
		) ENGINE=MyISAM;');
	$db->q('INSERT IGNORE INTO `cron` (`file`, `Description`) VALUES
		("cron/session.inc.php", "Removes old session data");');
	$db->q('CREATE TABLE IF NOT EXISTS `user` (
		`user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`user_name` varchar(16) NOT NULL,
		`user_pass` varchar(40) NOT NULL,
		`info_first` varchar(32) DEFAULT NULL,
		`info_last` varchar(32) DEFAULT NULL,
		`info_email` varchar(64) DEFAULT NULL,
		`group_id` int(11) DEFAULT NULL,
		PRIMARY KEY (`user_id`),
		UNIQUE KEY `user_name` (`user_name`)
		) ENGINE=MyISAM;');
	$db->q('INSERT IGNORE INTO `user` (`user_name`, `user_pass`) VALUES (?, ?)','ss',$_POST['user_name'],sha1($_POST['user_pass'].$_POST['user_name'].$_POST['salt']));
	$db->q('CREATE TABLE IF NOT EXISTS `group` (
		`group_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`group_name` varchar(16) NOT NULL,
		PRIMARY KEY (`group_id`)
		) ENGINE=MyISAM;');
//	$db->q('INSERT IGNORE INTO `user` (`user_name`, `user_pass`) VALUES (?, ?)','ss',$_POST['user_name'],sha1($_POST['user_pass'].$_POST['user_name'].$_POST['salt']));
// create default groups, admin, authorized user, and guest
	$db->q('CREATE TABLE IF NOT EXISTS `permissions` (
		`permission_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`permission_name` varchar(64) NOT NULL,
		PRIMARY KEY (`permission_id`)
		) ENGINE=MyISAM;');
	$db->q('CREATE TABLE IF NOT EXISTS `group_permissions` (
		`group_id` int(11) unsigned NOT NULL,
		`permission_id` int(11) unsigned NOT NULL,
		PRIMARY KEY (`group_id`,`permission_id`)
		) ENGINE=MyISAM;');
	$db->q('CREATE TABLE IF NOT EXISTS `user_session` (
		`user_id` int(11) NOT NULL,
		`sess` char(32) NOT NULL,
		`sessTime` datetime DEFAULT NULL,
		`ip` varchar(15) NOT NULL,
		PRIMARY KEY (`sess`,`user_id`)
		) ENGINE=MyISAM;');
	$db->q('CREATE TABLE IF NOT EXISTS `settings` (
		`group` VARCHAR(32) DEFAULT "core",
		`name` VARCHAR(32) NOT NULL,
		`value` VARCHAR(127) DEFAULT NULL,
		PRIMARY KEY (`group`, `name`)
		) ENGINE=MyISAM;');
	$db->q('INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
		("website", ?),				("session_length", ?),
		("login_attempts", ?),		("login_delay", ?),
		("token_length", ?),		("password_only_login", ?),
		("theme", ?),				("production", 1),
		("timezone", "US/Central"),	("display_errors", 1),
		("sess_name", "tmp")',	'siiiiis',
		$_POST['website'],		$_POST['session_length'],
		$_POST['login_attempts'],$_POST['login_delay'],
		$_POST['token_length'],	$_POST['password_only_login'],
		$_POST['theme']);

	//writes settings file
	$fp = fopen('settings.inc.php', 'w');
	fwrite($fp, $settings);
	fclose($fp);

	//creates error log file
	$fp = fopen('errors/log.inc.php', 'w');
	fwrite($fp,"<?php defined('KJL_WEB') or die('Access Denied.'); ?>\n");
	fclose($fp);
	
	
	//tells the user to reload the page
	header('location: '.$_POST['website']);
	?><html><head><title>Reload</title></head><body><h1>Reload Page</h1><h2>Please <a href="<?=$_POST['website']?>">click here</a> to update your settings</h2></body></html><?php
	exit;
}else{
	//it wasn't connected so it prints the error
	__db_printInstall('Error: Invalid Database Information');
}
}else{
	//no database connection and no information posted about the database
	//	so it prints the install page
	__db_printInstall();
}

function __db_printInstall($err = false){
?><html><head><title>Install KJL Web Handler</title><style>#error{background-color:darkred;border:1px solid black;color:white;height:20px;width:500px;}td{font-weight:bold;} td span{font-weight:normal;color:green;font-size:10px;}</style></head><body>
<form method="post">
<? if($err !== false): ?>
<div id="error"><?=$err?></div>
<? endif; ?>
<input type="submit" value="Submit">
<table>
	<tr>
		<td>Website URL</td>
		<td><input type="text" name="website" value="<?='http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']?>" size="50"></td>
	</tr>
	<tr>
		<td>MySQL Host</td>
		<td><input type="text" name="mysql_host" value="localhost" size="50"></td>
	</tr>
	<tr>
		<td>MySQL User</td>
		<td><input type="text" name="mysql_user" value="root" size="50"></td>
	</tr>
	<tr>
		<td>MySQL Pass</td>
		<td><input type="password" name="mysql_pass" value="" size="50"></td>
	</tr>
	<tr>
		<td>MySQL DB</td>
		<td><input type="text" name="mysql_db" value="" size="50"></td>
	</tr>
	<tr>
		<td>MySQL Debug Mode</td>
		<td><select name="mysql_debug"><option value="TRUE">True</option><option value="FALSE" selected>False</option></select></td>
	</tr>
	<tr>
		<td>Login Attempts</td>
		<td><select name="login_attempts"><option value="1">1</option><option value="2">2</option><option value="3" selected>3</option><option value="4">4</option><option value="5">5</option><option value="10">10</option><option value="1000">Unlimited</option></select></td>
	</tr>
	<tr>
		<td>Login Delay<br><span>(time to wait once attempts used)</span></td>
		<td><select name="login_delay"><option value="1">1 minute</option><option value="2">2 minutes</option><option value="3">3 minutes</option><option value="4">4 minutes</option><option value="5">5 minutes</option><option value="10" selected>10 minutes</option><option value="15">15 minutes</option><option value="20">20 minutes</option><option value="25">25 minutes</option><option value="30">30 minutes</option><option value="45">45 minutes</option><option value="60">1 hour</option></select></td>
	</tr>
	<tr>
		<td>Session Length</td>
		<td><select name="session_length"><option value="300">5 minute</option><option value="600">10 minutes</option><option value="900">15 minutes</option><option value="1800" selected>30 minutes</option><option value="3600">1 hour</option><option value="7200">2 hours</option><option value="21600">6 hours</option><option value="43200">12 hours</option><option value="86400">1 day</option><option value="259200">3 days</option><option value="864000">10 days</option><option value="2592000">1 month</option><option value="7776000">3 month</option><option value="31104000">1 year</option><option value="3110400000">Forever</option></select></td>
	</tr>
	<tr>
		<td>Token Length</td>
		<td><select name="token_length"><option value="60">1 minutes</option><option value="120">2 minutes</option><option value="180">3 minutes</option><option value="240">4 minutes</option><option value="300" SELECTED>5 minute</option><option value="600">10 minutes</option><option value="900">15 minutes</option><option value="1800">30 minutes</option><option value="3600">1 hour</option><option value="7200">2 hours</option><option value="21600">6 hours</option><option value="43200">12 hours</option><option value="86400">1 day</option><option value="259200">3 days</option><option value="864000">10 days</option><option value="2592000">1 month</option><option value="7776000">3 month</option><option value="31104000">1 year</option><option value="3110400000">Forever</option></select></td>
	</tr>
	<tr>
		<td>Relogin w/ Only Password Time Limit<br><span>(must be longer then session length)</span></td>
		<td><select name="password_only_login"><option value="0">None</option><option value="300">5 minute</option><option value="600">10 minutes</option><option value="900">15 minutes</option><option value="1800">30 minutes</option><option value="3600">1 hour</option><option value="7200">2 hours</option><option value="21600">6 hours</option><option value="43200">12 hours</option><option value="86400" selected>1 day</option><option value="259200">3 days</option><option value="864000">10 days</option><option value="2592000">1 month</option><option value="7776000">3 month</option><option value="31104000">1 year</option><option value="3110400000">Forever</option></select></td>
	</tr>
	<tr>
		<td>Random Salt<br><span>(for storing passwords)</span></td>
		<td><input type="text" name="salt" value="<?=generateSalt()?>" size="50"></td>
	</tr>
	<tr>
		<td>Admin User</td>
		<td><input type="text" name="user_name" value="admin" size="50"></td>
	</tr>
	<tr>
		<td>Admin Password</td>
		<td><input type="password" name="user_pass" size="50"></td>
	</tr>
	<tr>
		<td>Admin Password<br><span>(reverify)</span></td>
		<td><input type="password" name="user_pass2" size="50"></td>
	</tr>
</table>
<input type="hidden" name="theme" value="default">
<input type="submit" value="Submit">
</form>
</body></html><? exit; }

function generateSalt($max = 16) {
	$characterList = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=~[]|:;<>?,.";
	$i = 0;
	$salt = "";
	do {
		$salt .= $characterList{mt_rand(0,strlen($characterList)-1)};
		$i++;
	} while ($i <= $max);
	return $salt;
}
?>