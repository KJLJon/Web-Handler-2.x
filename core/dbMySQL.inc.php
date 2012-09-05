<?php defined('KJL_WEB') or die('Access Denied.');
/**
 * Publics:
 *
 * 	$affected_rows
 * 	$insert_id
 * 	$num_rows
 *
 * 	__construct($host, $username, $password, $database, $debug = false, $install = true)
 * 	is_connected()
 * 	select_one($var_name)
 * 	safe_names($array, $add_quotes = true)
 * 	insert($table,$array,$type)
 * 	update($table,$array,$type,$query = '')
 * 	q($query)
 ******* TODO PUT make_object in base class
 * 	make_object($r)
 * 	handle()
 */

$db = new dbMySQL($core['mysql']['host'], $core['mysql']['user'], $core['mysql']['password'], $core['mysql']['database'], $core['mysql']['debug']);

Class dbMySQL{
    private $_mysqli;
    protected $_debug;
	private $_select_one = false;
	private $output_var = null;
	private $connected = false;

	/**
	 * affected rows when using update
	 */
	public $affected_rows = -1;
	/**
	 * insert id if autoincrement is used when inserting
	 */
	public $insert_id = 0;
	/**
	 * number of rows the query returned
	 */
	public $num_rows = 0;

	/**
	 * Checks if the database is connected
	 *
	 * returns (boolean)
	 */
	public function is_connected(){
		return $this->connected;
	}

	/**
	 * connects to the database
	 * 
	 * $host mysql host
	 * $username mysql user name
	 * $password mysql password
	 * $database mysql database
	 * $debug (false) if you want it to show errors
	 * $install (true) if it can't connect and this is true then it figures it should install the website
	 */	
	public function __construct($host, $username, $password, $database, $debug = false, $install = true){
		$this->_mysqli = @new mysqli($host, $username, $password, $database);
		$this->_debug = (bool) $debug;
		if(mysqli_connect_errno()){
			if($this->_debug){
				echo mysqli_connect_error();
				debug_print_backtrace();
			}

			//requires install
			global $core;
			if($install && isset($core['install'])){
				@require('core/install.inc.php');
			}
			
			return;
		}
		$this->connected = true;
	}

	/**
	 * resets default public variables
	 */
	private function reset_variables(){
		$this->affected_rows = -1;
		$this->insert_id = 0;
		$this->num_rows = 0;
	}
	
	/**
	 * Tells the query to return an object (only returns first line)
	 * 
	 * $var_name is the variable name
	 *
	 * Example:
	 * 	so if you set $dog = $db->q('selects dog_name, dog_age, enabled, date_time_hour from `dogs` limit 1');
	 * 	the object will be
	 * 		$dog->name, $dog->age, $dog->enabled, $dog->date->time_hour
	 *
	 * 	it removes the variable name from the variable and replaces the first _ with ->
	 */
	public function select_one($var_name){
		$this->output_var = $var_name;
		$this->_select_one = true;
	}
	
	/**
	 * gives you a safe name for a table or refernce
	 *
	 * $array is an array of values to check
	 * $add_quotes (true) is to determin if you want to add `` around the $value or not
	 *
	 * returns an array of alphanumeric and _ string that can be surrounded by `
	 */
	public static function safe_names($array, $add_quotes = true){
		//returns alpha-numeric or _ characters
		$func = create_function('$key','return base::cast($key, "sqlname", '.((boolean) $add_quotes).');');
		return array_map($func, (array) $array);
	}

	/**
	 * inserts data into table
	 *
	 * $table the table name
	 * $array can be 2 formats
	 * 	'table column' => 'value'
	 * 		OR
	 * 	array('table column'), array('first insert value'), array('second insert value'), ..., array('N insert value')
	 * $type the types of the data inserted.  You need a type for every value inserted
	 *
	 * returns inserted id or false
	 * 	NOTE: inserted id can be 0, example is if it inserted something on a table w/o autoincrement.  So check for ===false
	 */
	public function insert($table,$array,$type){
		$q = 'INSERT INTO `'.$table.'` ';
		$vals = array();
		$count = count($array);
		if(isset($array[0]) && is_array($array[0])){
			$values = array();
			$q .= '('.implode(', ',self::safe_names(array_keys($array[0]))).') VALUES ';
			$cols = '('.implode(',', array_fill(0, count($array[0]), '?')).')';
			for($i=1;$i<$count;++$i){
				$values[] = $cols;
				$vals = array_merge($vals,$array[$i]);
			}
			$q .= implode(',',$values);
		}else{
			$q .= '('. implode(', ', self::safe_names(array_keys($array))) .') VALUES ('.implode(',', array_fill(0, $count, '?')).')';
			$vals = array_values($array);
		}

		return ( $this->q($q,$type,$vals) === true ) ? $this->insert_id : false;
	}
	
	/**
	 * updates a sql table
	 *
	 * $table the table name
	 * $array of values, setup like so:
	 * 	'table column' => 'value'
	 * $type is the insert types.  Need a type for every value in the array
	 * $query ('') the query to send after the update code [IE 'WHERE `user_id` = 1']
	 * 	MAKE SURE YOU ESCAPE THE QUERY BEFORE SETTING IT IN THIS FUNCTION
	 * 
	 * Additional note:  if you add ?'s to the query you can add the type's at the end of $type
	 *
	 * returns the number of rows affected or false
	 */
	public function update($table,$array,$type,$query = '', $queryArgs = array()){
		return ($this->q('UPDATE '.base::cast($table, "sqlname", true).' SET '.implode('=?, ', self::safe_names(array_keys($array))).'=? '.$query,$type,array_merge(array_values($array),(array)$queryArgs)) === true)?$this->num_rows:false;
	}
	
	/**
	 * queries the database (stanard SQL) allows for injection protection
	 *
	 * $query is the query to use
	 * $type is the variable types
	 * 	i = Integer
	 * 	d = double
	 * 	s = String
	 * 	b = blob
	 * $args if it is an array() it will be the args otherwise it can be $arg1,$arg2,...,$argN
	 */
	public function q($query){
		$this->reset_variables();
		if($query = $this->_mysqli->prepare($query)){
			if(func_num_args() > 2){
				$x = func_get_args();
				if( func_num_args() == 3 && is_array(func_get_arg(2)) ){
					$args = array_merge(array($x[1]), $x[2]);//array_merge(array(func_get_arg(1)), func_get_arg(2));
				}else{
					$args = array_merge(array($x[1]), array_slice($x, 2));//array_merge(array(func_get_arg(1)), array_slice($x, 2));
				}
				$args_ref = array();
				foreach($args as $k => &$arg){
					$args_ref[$k] = &$arg; 
				}
				call_user_func_array(array($query, 'bind_param'), $args_ref);
			}
			$query->execute();
 
			if($query->errno){
				if($this->_debug){
					echo mysqli_error($this->_mysqli);
					debug_print_backtrace();
				}
				return false;
			}

			if($query->affected_rows > -1 || $query->insert_id > 0){
				$this->affected_rows = $query->affected_rows;
				$this->insert_id = $query->insert_id;
				return true;//$query->affected_rows;
			}
			
			$params = array();
			$meta = $query->result_metadata();
			while ($field = $meta->fetch_field()){
				$params[] = &$row[$field->name];
			}
			call_user_func_array(array($query, 'bind_result'), $params);

			if($this->_select_one){
				$query->fetch();
				$this->num_rows = 1;
				$has_var = !is_null($this->output_var);
				foreach ($row as $key => $val){
					$k = explode('_', $key, 2);
					if(count($k)==2){
						if($has_var && $k[0] == $this->output_var){
							$result->$k[1] = $val;
						}else{
							$result->$k[0]->$k[1] = $val;
						}
					}else{
						$result->$k[0] = $val;
					}
				}
				$this->_select_one=false;
				$this->output_var=null;
			}else{
				$result = Array();
				$num_rows=0;
				while ($query->fetch()){
					$r = Array();
					foreach ($row as $key => $val){
						$r[$key] = $val;
					}
					$result[] = $r;
					++$num_rows;
				}
				$this->num_rows = $num_rows;
			}
			$query->close();

			return $result;
		}else{
			if($this->_debug){
				echo $this->_mysqli->error;
				debug_print_backtrace();
			}
			return false;
		}
	}
	
	/**
	 * converts an array 1 dimentional into an object
	 *
	 * $r is the array
	 *
	 * returns the obejct or false
	 */
	public function make_object($r){
		if(!empty($array)){
			$data = false;

			foreach ($array as $akey => $aval){
				$data->{$akey}= $aval;
			}

			return $data;
		}
		return false;
	}
 
	/**
	 * the mysqli handle
	 * returns the mysqli handle
	 */
    public function handle(){
        return $this->_mysqli;
    }
}
?>