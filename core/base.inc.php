<?php defined('KJL_WEB') or die('Access Denied.');
//base::a, base::t, base::get, base::set
/**
* TODO: self::private_function()  (change them all
*/
class base {

	//scrubs input
	/*public function get($var, $type = 'string', $type = '_REQUEST'){
		return settype($$type[$var],$type);
	}
	*/
	
	//looks for whitelist, blacklist, replace all regex
	public static function scrub($var, $opts){
		$opts = array_merge(array(		//befor casting
			'regex'		=> false,		//use regex or not?
			'whitelist'	=> array(),
			'blacklist'	=> array(),
			'replace'	=> array(
				'from'	=>	array(),
				'to'	=>	array()
			)
			), $opts);
		
		if($opts['regex']){
			//check white list
			foreach($opts['whitelist'] as $val){
				if(!preg_match($val, $var)) return null;
			}

			//check blacklist
			foreach($opts['blacklist'] as $val){
				if(preg_match($val, $var)) return null;
			}
			
			//replace
			return preg_replace($opts['replace']['from'], $opts['replace']['to'], $var);
		}else{
			//check white list
			foreach($opts['whitelist'] as $val){
				if(strpos($val, $var) === false) return null;
			}
			
			//check blacklist
			foreach($opts['blacklist'] as $val){
				if(strpos($val, $var) !== false) return null;
			}
			
			//replace
			return str_replace($opts['replace']['from'], $opts['replace']['to'], $var);			
		}
	}
	
	public static function cast($var, $type, $params = null){
		$data = null;
		switch($type){
		case 'string':
		case 'boolean': case 'bool':
		case 'integer': case 'int':
		case 'float':
		case 'array': case 'object':
		case 'null':
			$data = $var;
			settype($data, $type);
			break;
		case 'code': case 'html':
			$data = htmlentities($var, ENT_QUOTES, 'UTF-8');
			break;
		case 'xml':
			$data = str_replace(array('&', '<', '>', '"', "'", '/'),
				array('&amp;', '&lt;', '&gt;', '&quot;', '&#x27;', '&#x2F;'), $var);
			break;
		case 'text':
			$data = htmlentities(strip_tags($var), ENT_QUOTES, 'UTF-8');
			break;
		case 'attr':
			$data = preg_replace('/[^a-zA-Z0-9\_\-\:\;\.\\\'\"\(\)]|(javascript:?)/', '', $var);
			break;
		case 'sqlname':	//params = add_quotes, does same thing as db::safe_name
			if(empty($params)){
				$data = preg_replace('/[^a-zA-Z0-9_`]/', '', $var);
			}else{
				$data = '`'. preg_replace('/[^a-zA-Z0-9_`]/', '', $var) .'`';
			}
			break;
		case 'datetime':
			if(empty($params)){
				preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $var, $data);
				$data = isset($data[0])?$data[0]:null;
			}else{
				$data = date( 'Y-m-d H:i:s', $var );
			}
			break;
		case 'date':
			if(empty($params)){
				preg_match('/\d{4}-\d{2}-\d{2}/', $var, $data);
				$data = isset($data[0])?$data[0]:null;
			}else{
				$data = date( 'Y-m-d', $var);
			}
			break;
		case 'time':
			if(empty($params)){
				preg_match('/\d{2}:\d{2}:\d{2}/', $var, $data);
				$data = isset($data[0])?$data[0]:null;
			}else{
				$data = date( 'H:i:s', $var );
			}
			break;
		case 'timestamp': //param is relative time
			$params = (int) $params;
			if( $params === 0 ){
				$data = strtotime( $var );
			}else{
				$data = strtotime( $var, $params );
			}
			break;
		case 'website':	case 'url':
			$data = base::cast_website($var,$params);
			break;
		case 'zip':
			$matches = '';
			$data = preg_match('/(\d{5})\-?(\d{4})?/', $var, $matches);
			if(isset($matches[1])){
				$data = $matches[1] . (isset($matches[2])?'-'.$matches[2]:'');
			}else{
				$data = '';
			}
		case 'email':
			$data = filter_var($var, FILTER_VALIDATE_EMAIL);
			break;
		case 'state':
			$states = array('AL'=>"Alabama", 'AK'=>"Alaska", 'AZ'=>"Arizona", 'AR'=>"Arkansas", 
			'CA'=>"California", 'CO'=>"Colorado", 'CT'=>"Connecticut", 'DE'=>"Delaware", 'DC'=>"District Of Columbia", 
			'FL'=>"Florida", 'GA'=>"Georgia", 'HI'=>"Hawaii", 'ID'=>"Idaho", 'IL'=>"Illinois", 'IN'=>"Indiana", 
			'IA'=>"Iowa", 'KS'=>"Kansas", 'KY'=>"Kentucky", 'LA'=>"Louisiana", 'ME'=>"Maine", 'MD'=>"Maryland", 
			'MA'=>"Massachusetts", 'MI'=>"Michigan", 'MN'=>"Minnesota", 'MS'=>"Mississippi", 'MO'=>"Missouri", 
			'MT'=>"Montana", 'NE'=>"Nebraska", 'NV'=>"Nevada", 'NH'=>"New Hampshire", 'NJ'=>"New Jersey",
			'NM'=>"New Mexico", 'NY'=>"New York", 'NC'=>"North Carolina", 'ND'=>"North Dakota", 'OH'=>"Ohio",  
			'OK'=>"Oklahoma", 'OR'=>"Oregon", 'PA'=>"Pennsylvania", 'RI'=>"Rhode Island", 'SC'=>"South Carolina",  
			'SD'=>"South Dakota", 'TN'=>"Tennessee", 'TX'=>"Texas", 'UT'=>"Utah", 'VT'=>"Vermont", 'VA'=>"Virginia",  
			'WA'=>"Washington", 'WV'=>"West Virginia", 'WI'=>"Wisconsin", 'WY'=>"Wyoming");
			
			$data = '';
			
			if(isset($states[$var])){
				if(empty($params)){
					$data = $var;
				}else{
					$data = $states[$var];
				}
			}elseif(in_array($var,$states)){
				if(empty($params)){
					$data = array_search($var,$states);
				}else{
					$data = $var;
				}
			}
		}
		return $data;
	}
	
	public static function cast_website($var, $params = null){
		$url = parse_url(strip_tags($var));
		
		//checks for the protocol and validates it
		if(isset($url['scheme'])){
			if(!in_array($url['scheme'],array('http','https','ftp','ftps'))){
				return false;
			}
		}else{
			$url['scheme'] = 'http';
		}
		
		//assigns host if there isn't one set
		if(!isset($url['host'])){
			//default host = first part of path
			if(isset($url['path'])){
				$path_parts = explode('/',$url['path']);
				$url['host'] = $path_parts[0];
				unset($path_parts[0]);
				$url['path'] = implode('/',$path_parts);
			}else{
				return false;
			}			
		}
		
		//checks if the host is an ip (if it is invalidate it)
		if(preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',$url['host'])){
			return false;
		}
		
		$url['host'] = preg_replace('/[^a-zA-Z0-9\-\.]/', '', $url['host']);
		
		if(!isset($url['path'])){
			$url['path'] = '/';
		}else{
			$url['path'] = strtr($url['path'], '"?*:|/\\', '');
		}		
		
		//checks for valid extention
		if($params !== null){
			$ext = pathinfo($url['path'], PATHINFO_EXTENSION);
			if(!in_array($ext, $params)){
				return false;
			}
		}
		
		//checks user name and password, encodes it
		if(isset($url['user'])){
			$url['userpass'] = rawurlencode($url['user']) . (isset($url['pass'])?':'. rawurlencode($url['pass']):'') .'@';
		}else{
			$url['userpass'] = '';
		}
		
		//re-encodes the query
		if(isset($url['query'])){
			$query = '';
			parse_str($url['query'],$query);
			$url['query'] = '?'.http_build_query($query);
		}else{
			$url['query'] = '';
		}
		
		//validates the fragment
		if(isset($url['fragment'])){
			$url['fragment'] = '#'. preg_replace('/[^a-zA-Z0-9]/', '', $url['fragment']);
		}else{
			$url['fragment'] = '';
		}
		
		return $url['scheme'] .'://'. 
			$url['userpass'] . 
			$url['host'] . (isset($url['port'])?':'.$url['port']:'') .
			$url['path'] . 
			$url['query'] . 
			$url['fragment'];
	}
	
	public static function get_global($array, $var, $default = null){
		switch($array){
		case '_POST': case 'POST':
			$var = isset($_POST[$var])?$_POST[$var]:$default;
			break;
		case '_GET': case 'GET':
			$var = isset($_GET[$var])?$_GET[$var]:$default;
			break;
		case '_REQUEST': case 'REQUEST':
			$var = isset($_REQUEST[$var])?$_REQUEST[$var]:$default;
			break;
		case '_COOKIE': case 'COOKIE':
			$var = isset($_COOKIE[$var])?$_COOKIE[$var]:$default;
			break;
		case '_FILE': case 'FILE':
			$var = isset($_FILE[$var])?$_FILE[$var]:$default;
			break;
		case '_SERVER': case 'SERVER':
			$var = isset($_SERVER[$var])?$_SERVER[$var]:$default;
			break;
		case '_ENV': case 'ENV':
			$var = isset($_ENV[$var])?$_ENV[$var]:$default;
			break;
		default:
			if(!empty($array)){
				if(is_array($array)){
					$var = isset($GLOBALS[$array][$var])?$GLOBALS[$array][$var]:$default;
				}else{
					$var = isset($GLOBALS[$array])?$GLOBALS[$array]:$default;
				}
			}
			break;
		}
		return $var;
	}
	
	//scrub type
	public static function get($var, $opts = array(), $echo = false){
		$opts = array_merge(array(
			'type'		=> 'string',	//predefined types, (string,int,boolean,float, etc), and (website,phonenumber,html, timestamp, datetime) these have predefined replacements (regex style) so it has to fit the critera
			'before'	=>	false,
			'after'		=>	false,		
			'default'	=>	null,
			'array'		=>	'REQUEST'
		), $opts);
		$data = $opts['default'];
		
		$var = base::get_global($opts['array'],$var,$opts['default']);
		
		if($opts['before'] !== false) $var = base::scrub($var, $opts['before']);

		$var = base::cast($var,$opts['type']);
		
		if($opts['after'] !== false) $var = base::scrub($var, $opts['after']);
		
		if($echo) echo $var;
		
		return $var;
	}

	//link
	public static function link($url, $vars = array(), $echo = true){return a($url, $vars, $echo);}
	public static function a($url, $vars = array(), $echo = true){
		global $core;
		if(empty($vars)){
			$site = $core['website'].$url;
		}else{
			$site = $core['website'].$url.'?'.http_build_query($vars);
		}
	
		if($echo) echo $site;
	
		return $site;
	}
	
	//text
	public static function text($txt, $echo = true){return t($txt,$echo);}
	public static function t($txt, $echo = true){
		$val = self::cast($txt, 'html');
		if($echo) echo $val;
		return $val;
	}
}
?>