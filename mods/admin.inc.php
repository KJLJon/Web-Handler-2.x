<?php defined('KJL_WEB') or die('Access Denied.');
class admin{
	function delete_user($uid){
		global $db;
		$db->q('delete from `user` where `user_id` = ?','i',$uid);
	}
	function edit_user($uid, $data = array(),$fields=array('user_name','user_pass','info_first','info_last','info_email','group_id')){
		global $db,$core;
		if(isset($data['user_pass'])){
			if($data['user_pass'] == ''){
				unset($fields[array_search('user_pass',$fields)]);
			}else{
				$data['user_pass'] = sha1($data['user_pass'].$data['user_name'].$core['salt']);
			}
		}
			
		$insert = 'UPDATE `user` SET ';
		$types = '';
				
		$dataInsert=array();
		foreach($fields as $name){
			$dataInsert[] = isset($data[$name])?$data[$name]:'';
			$types .= 's';
			$insert .= '`'.$name.'`=?,';
		}
		
		$dataInsert[] = $uid;
		$db->q(substr($insert,0,-1) . ' WHERE `user_id` = ?',$types.'i',$dataInsert);
	}
	
	function add_user($data = array(),$fields=array('user_name','user_pass','info_first','info_last','info_email','group_id')){
		global $db,$core;
		if(!isset($data['user_name'],$data['user_pass'])) return false;
//		$pass = $data['user_pass'];
		$data['user_pass'] = sha1($data['user_pass'].$data['user_name'].$core['salt']);
		$insert = 'INSERT INTO `user` (';
		$vals = '';
		$types = '';
		
		$dataInsert=array();
		foreach($fields as $name){
			$dataInsert[] = isset($data[$name])?$data[$name]:'';
			$types .= 's';
			$vals .= '?,';
			$insert .= '`'. $name .'`,';
		}
		
		$insert = substr($insert,0,-1) .') VALUES ('. substr($vals,0,-1) .');';
		$db->q($insert,$types,$dataInsert);
		return true;
	}
}
?>