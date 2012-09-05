<?php defined('KJL_WEB') or die('Access Denied.');
//make storing passwords more safe by salting it with something b4 hashing it
//	have salt change based on user? (ie user_id or user_name, etc)
//make function to allow for preprocessed hashing
//	ie if js hashed it b4 sending it.

/*
 * Class: super_admin
 * Extends: session
 * 
 * This class makes the user have all permissions
 */
class super_admin extends session {
	public function __construct($ip,$reason,$id){
		$this->ip=$ip;
		$this->reason=$reason;
		$this->id=$id;
	}
	public function has_perm($perm){return true;}
}

$user = new anonymous_user;
$sess = new session;
if($user->id == 1){
	$sess = new super_admin($sess->ip,$sess->reason,$sess->id);
}

/*
 * Class: anonymous_user
 * 
 * This class creates a default user
 */
class anonymous_user{
	public $logged_in = false;
	public $id = 0;
	public $permissions = array();
	public $group;
	public function __construct(){
		$group->id = 0;
		$group->name = 'Guest';
	}
}

/*
 * Class: session
 *
 * Handles all the session stuff
 */
class session{
	/* users ip */
	public $ip = '';
	/* users ip */
	public $reason = '';
	/* users id (agent and ip md5 mix) */
	public $id = '';
	/* stored token code so it can be used with different salts once it is created */
	private $token;
	
	/*
	 * Creates a user
	 *
	 * $data is an object of all the user information
	 */
	public function generate_user($data){
		global $user, $db;
		$data->logged_in=true;
		
		$r = $db->q('SELECT `permission_name` as `perm` FROM `group_permissions` gp,`permissions` p WHERE `group_id` = ? AND gp.`permission_id` = p.`permission_id`','i',$data->group->id);
		$data->permissions = array();
		foreach($r as $p){
			$data->permissions[] = $p['perm'];
		}
		$user = $data;
	}

	/*
	 * Creates a session
	 * checks login with POST: name, pass or phrase
	 * 		or with cookies or saved session data
	 */
	public function __construct(){
		global $db;
		
		session_start();
		$this->ip = $this->get_ip();
		$this->id = $this->get_id();
		
		if(isset($_POST['pass'],$_POST['name'])){
			if($this->check_attempts()){
				if(isset($_POST['persist']) && $_POST['persist']){
					$_SESSION['persist'] = 1;
				}
				$this->check_login($_POST['name'],$_POST['pass']);
			}else{
				$this->reason = "Too many attempts";
			}
			unset($_POST['name'],$_POST['pass'],$_POST['persist']);
		}elseif(isset($_POST['phrase'])){
			//checks to see if they are logged in and just need to resend password
			if($this->check_attempts()){
				$this->check_password_login($_POST['phrase']);
			}else{
				$this->reason = "Too many attempts";
			}
			unset($_POST['phrase']);
		}
		if($this->check_session()){
			if($this->valid_session()){
				$this->set_session();
				$db->select_one('user');
				$r = $db->q('select * from `user` `u` LEFT JOIN `group` `g` ON `g`.`group_id` = `u`.`group_id` WHERE `user_id` = ? limit 1', 'i', $_SESSION['user']);
				unset($r->pass);
				$this->generate_user($r);
			}
		}
	}
	
	/*
	 * Checks if the user has a permission or not
	 *
	 * $perm is the permission name
	 * returns (boolean)
	 */
	public function has_perm($perm){
		global $user;
		return in_array($perm,$user->permissions);
	}
	
	/*
	 * Checks if the user has a permission or not, if they don't it will return the index page
	 *
	 * $perm is the permission name
	 */
	public function require_perm($perm){
		if(!$this->has_perm($perm)){
			require('errors/404.inc.php');
			exit;
		}
	}

	/*
	 * Logs the user out
	 */
	public function logout(){
		//remove cookies and session data
		$this->remove_session();
	}
	
	/* PRIVATE
	 * Checks login attempts
	 */
	private function check_attempts(){
		global $core, $db;
		$data = $db->q('SELECT COUNT(*) as cnt FROM `stats_login_attempts` WHERE `ip` = ? AND `logged_in` = 0 AND `dt` BETWEEN TIMESTAMPADD(MINUTE,-'. ((int)$core['login_delay']) .',NOW()) AND NOW();','s',$this->ip);
		if(isset($data[0]['cnt']) && $data[0]['cnt'] > $core['login_attempts']){
			return FALSE;
		}else{
			return TRUE;
		}
	}

	/* PRIVATE
	 * Checks the login
	 *
	 * $name is the user name
	 * $pass is the user password
	 */
	private function check_login($name,$pass){
		global $db,$core;
		
		$r = $db->q('SELECT `user_id` FROM `user` WHERE `user_name` = ? AND `user_pass` = ?;','ss',$name,sha1($pass.$name.$core['salt']));
		
		//validates only 1 result
		if ( count($r) != 1 ) {
			$this->reason = "Invalid IP or Login";
			$this->log_events($name, false);
			return false;
		}

		//sets session data
		$this->log_events($name,true);
		$_SESSION['user'] = $r[0]['user_id'];
		$_SESSION['sess'] = $this->create_session($_SESSION['user']);
		$_SESSION['id'] = $this->id;
		$this->set_session();
		$this->redirect();
	}
	
	/* PRIVATE
	 * Checks the login, the user has been away for a while, we want them to retype the password
	 *
	 * $pass is the user password
	 */
	private function check_password_login($pass){
		global $db, $core;
		if( isset($_COOKIE['sess'],$_COOKIE['user'],$_COOKIE['id']) ) {
			$r = $db->q("SELECT TIME_TO_SEC(TIMEDIFF(NOW(),`sessTime`)) as `dt`, user_name FROM `user_session` s LEFT JOIN user u ON s.user_id = u.user_id WHERE s.sess = ? and s.user_id = ? AND u.user_pass = sha1(concat(?,`user_name`,?)) LIMIT 1", 'sis', $_COOKIE['sess'],$_COOKIE['user'],$pass, $core['salt']);
			if(isset($r[0]['dt'])){
				if($r[0]['dt'] <= $core['password_only_login']){
					set_session_cookies();
					$this->set_session();
					$this->log_events($r[0]['user_name'],true);
				}
			}else{
				$this->reason = "Invalid IP or Login";
				$this->log_events('',false);
			}
		}
	}
	
	/* PRIVATE
	 * Sets the session variables to the cookies variables
	 */
	private function set_session_cookies(){
		$_SESSION['sess']=$_COOKIE['sess'];
		$_SESSION['user']=$_COOKIE['user'];
		$_SESSION['id']=$_COOKIE['id'];		
		//grabbed the info from the cookies so it must be a persistant connection
		$_SESSION['persist'] = 1;
	}
	
	/* PRIVATE
	 * Checks the session
	 */
	private function check_session(){
		if ( isset($_SESSION['sess'],$_SESSION['user'],$_SESSION['id']) ) {
			return $this->check_id();
		} elseif ( isset($_COOKIE['sess'],$_COOKIE['user'],$_COOKIE['id']) ) {
			set_session_cookies();
			return $this->check_id();
		}
		return false;
	}
	
	/* PRIVATE
	 * Checks the ID
	 */
	private function check_id(){
		return ($this->id === $_SESSION['id']);
	}
	
	/* PRIVATE
	 * gets the id (if we want to make it unique per site, you can override this)
	 */
	private function get_id(){
		global $core;
		return md5($_SERVER['HTTP_USER_AGENT'].$this->ip.$core['salt']);
	}

	/* PRIVATE
	 * Creates a session
	 *
	 * $user_id is the users id
	 */
	private function create_session($user_id){
		global $db;
		$sess = md5(uniqid(rand(),true));
		$r = $db->q("SELECT sess FROM `user_session` WHERE `sess` = ? and user_id = ? LIMIT 1", 'si', $sess,$user_id);
		if(isset($r[0]['sess'])){
			return create_session();
		}else{
			return $sess;
		}
	}
	
	/* PRIVATE
	 * inserts session into the databse
	 *
	 * $user_id is the user id
	 * $sess is the session string
	 * $ip is the user ip
	 * $time (ESCAPE BEFORE USING) is the time to add it to
	 */
	private function insert_session($user_id,$sess,$ip,$time = 'NOW()'){
		global $db;
		$db->q('INSERT INTO user_session (user_id,sess,sessTime,ip) VALUES (?,?,'.$time.',?) ON DUPLICATE KEY UPDATE sessTime='.$time.', ip=?;', 'isss',$user_id,$sess,$ip,$ip);
	}
	
	/* PRIVATE
	 * Logs logins
	 *
	 * $user_name is the attempted user name
	 * $logged_in is if they are logged in or not (boolean)
	 */
	private function log_events($user_name, $logged_in = false){
		global $db;
//todo: log events if they tried more then 3 times (store info about them)
		$db->q('insert into stats_login_attempts (user_name,ip,logged_in,dt) values(?,?,?,NOW())','ssi',$user_name,$this->ip,$logged_in);
	}

	/* PRIVATE
	 * removes the session
	 */
	private function remove_session(){
		global $user;
		$this->insert_session($_SESSION['user'],$_SESSION['sess'],$this->ip,'0');
		session_destroy();
		if(isset($_COOKIE['sess']))
			setcookie ('sess', '', time() - 3600, null, null, null, true);
		if(isset($_COOKIE['user']))
			setcookie ('user', '', time() - 3600, null, null, null, true);
		if(isset($_COOKIE['id']))
			setcookie ('id', '', time() - 3600, null, null, null, true);
		$user = new anonymous_user;
	}

	/* PRIVATE
	 * sets the user to their session
	 */
	private function set_session(){
		//set session and cookie variables]
		global $core;
		if(isset($_SESSION['persist'])){
			$length = max($core['password_only_login'],$core['session_length']);
			setcookie('sess', $_SESSION['sess'], time()+$length, null, null, null, true);
			setcookie('user', $_SESSION['user'], time()+$length, null, null, null, true);
			setcookie('id', $_SESSION['id'], time()+$length, null, null, null, true);
		}
		$this->insert_session($_SESSION['user'],$_SESSION['sess'],$this->ip);
	}
	
	/* PRIVATE
	 * checks if the session the user has is a valid session
	 */
	private function valid_session(){
		global $db, $core;
		$r = $db->q('select TIME_TO_SEC(TIMEDIFF(NOW(),`sessTime`)) as `dt` from user_session WHERE `sess` = ? and user_id = ? and ip = ? LIMIT 1', 'sis', $_SESSION['sess'], $_SESSION['user'], $this->ip);
		if(isset($r[0]['dt'])){
			//session is valid
			if($r[0]['dt'] <= $core['session_length']){
				return true;			
			}else{
				$this->reason = "Session timedout";
				return false;
			}
		}else{
			//invalid session
			return false;
		}
	}
	
	/* PRIVATE
	 * sets $this->ip to the users ip
	 */
	private function get_ip(){
	    if (!empty($_SERVER['HTTP_CLIENT_IP'])){//check ip from share internet
			$ip=$_SERVER['HTTP_CLIENT_IP'];
		}elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){//to check ip is pass from proxy
			$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		}else{
			$ip=$_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
	
	/* 
	 * redirects a user
	 *
	 * $page where to send the user to otherwise it will redirect them back to the same page
	 */
	public function redirect($page = null, $error404 = false) {
		global $core,$url;
		if($page === null) $page = $core['website'].($url==='/'?'':$url);
		if($error404 === true){
			@header("HTTP/1.0 404 Not Found");
			@header("Status: 404 Not Found");
		}
		if (!@header("Location: ".$page))
			echo '<script type="text/javascript">window.location.replace("',$page,'");</script><a href="',$page,'">Click me</a> to redirect you to the proper page';
		
		echo $page;
		exit;
	}
	
	/* 
	 * Creates a token (you can check the token on the next page, good to use on forms)
	 *
	 * $salt is the tokens salt to check against.  Is mainly used if you have multiple forms that you want to distinguesh seperate tokens
	 * returns md5 token hash
	 */
	public function token($salt = '12345'){
		if(!isset($this->token)){
			$this->token = md5(uniqid(rand(),true));
			$_SESSION['token'] = $this->token;
			$_SESSION['token_time'] = time();
		}
		return md5($this->token.$salt);
	}
	
	/* 
	 * checks if the token is a valid
	 *
	 * $token the token to check
	 * $salt the salt used for the token
	 * returns if it is a valid token or not (boolean)
	 */
	public function isToken($token, $salt = '12345'){
		global $core;
		if( isset($_SESSION['token']) && md5($_SESSION['token'].$salt) == $token && time() - $_SESSION['token_time'] < $core['token_length'] ){
			unset($_SESSION['token']);
			unset($_SESSION['token_length']);
			return true;
		}
		return false;
	}
}

?>