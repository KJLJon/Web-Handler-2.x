<?php defined('KJL_WEB') or die('Access Denied.');
$settings=array();
class settings {
	/**
	 * Gets a "group" of variables from the database
	 * 
	 * @group is the group name set (usually plugin name or something simular)
	 * @global_var if = false it returns the array of settings otherwise it merges the $GLOBALS[@group] array
	 */
	public static function get($group, $global_var = false){
		global $settings, $db;
		if(!isset($settings[$group])){
			$vars = $db->q('SELECT `name`, `value` FROM `settings` WHERE `group` = "core";');

			$settings[$group] = array();
		
			foreach($vars as $val){
				$settings[$group][$val['name']] = $val['value'];
			}
		}

		if($global_var){
			$GLOBALS[$group] = array_merge($GLOBALS[$group],$settings[$group]);
		}		

		return $settings[$group];
	}
	
	public static function set($group, $name, $value){
		global $db;
		$settings[$group][$name] = $value;
		$db->insert('settings', array('group'=>substr($group,0,32), 'name'=>substr($name,0,32), 'value'=>substr($value,0,127)), 's');
	}
	
	public static function defaults(){
		global $core;
		//'US/Central'
		ini_set('date.timezone', $core['timezone']);		
		ini_set('display_errors', (int)$core['display_errors']);

		//tmp
		session_name($core['sess_name']);
	}
}
?>