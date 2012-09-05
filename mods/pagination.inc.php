<?php defined('KJL_WEB') or die('Access Denied.');
/* pagination class
 *
 * EXAMPLE:
 * 		$pg = new pagination(array('limit'=>100));
 * 		$r = $db->q('SELECT SQL_CALC_FOUND_ROWS * FROM `sess` limit ?, ?','ii',$pg->current,$pg->limit);
 * 		$cnt = $db->q('Select FOUND_ROWS() as `count`');
 * 		$pg->paginate($cnt[0]['count']);
 * CSS (assuming default css classes set):
 * 		.pages {margin: 40px 0 0 0; width: 100%; border:0; padding:0; }
 * 		.pages li{border:0; font-size:11px;list-style:none;margin:0; padding:0; }
 * 		.pages a{margin-right:3px;margin-left:3px;border:solid 1px #EEE;}
 * 		.pages a.next ,
 * 		.pages a.previous {font-weight:bold;border:solid 1px #FFFFFF;}
 * 		.pages .active{font-weight:bold;float:left;padding:5px 7px;color:#ff0074;display:block;}
 * 		.pages a:link,.pages a:visited {display:block;float:left;padding:3px 6px;color:#0063e3;text-decoration:none;}
 * 		.pages a:hover{border:solid 1px #F00;}
 */
class pagination{
	public $current;						//current record its on
	public $count;							//count of records
	public $page;							//current page number 
	public $total;							//total pages
	public $limit	= 20;					//limit of records
	public $var		= 's';					//get variable
	public $display	= 10;					//number of links to display
	public $records	= true;					//show records its on 
	public $goto	= true;					//show Goto page form
											//Text to display
	public $text = array();
											//CSS Classes (below)
	public $cls = array('ul'=>'pages','previous'=>'previous','next'=>'next','active'=>'active');
	
	/* Can overide the defaults by sending options in an array (send only what you want to override)
	 * array(
	 *	'limit'=>20,
	 * 	'var'=>'page',
	 * 	'display'=>10,
	 * 	'title'=>'showing records',
	 * 	'goto'='Goto Page:',
	 * 	'class'=>array(
	 * 		'ul'=>'pages',
	 * 		'prev'=>'previous',
	 * 		'next'=>'next',
	 * 		'active'=>'active'
	 * 	)
	 *	'text'=>array(
	 * 		'records'	=>	'showing records',
	 * 		'goto'		=>	'Goto Page:',
	 * 		'total'		=>	' of ',
	 * 		'newest'	=>	'« newest',
	 * 		'previous'	=>	'« previous '.$this->limit,
	 * 		'next'		=>	'next '.$this->limit.' »',
	 * 		'oldest'	=>	'oldest »'
	 * 	)
	 * )
	 */
	public function __construct(){
		if(func_num_args()===1){
			$opts = func_get_args();
			foreach($opts[0] as $key => $val){
				if(is_array($val)){
					$this->$key = array_merge_recursive($val);
				}else{
					$this->$key = $val;
				}
			}
		}
		$this->page = isset($_GET[$this->var])?(int)$_GET[$this->var]:1;
		$this->current = $this->limit*($this->page-1);

		$text = array(
			'records'	=>	'showing records',
			'goto'		=>	'Goto Page:',
			'total'		=>	' of ',
			'newest'	=>	'« newest',
			'previous'	=>	'« previous '.$this->limit,
			'next'		=>	'next '.$this->limit.' »',
			'oldest'	=>	'oldest »',
		);
		foreach($text as $var => $val)
			if(!isset($this->text[$var]))
				$this->text[$var] = $val;
		
		return $this;
	}
	
	/* creates a pagination
	 *
	 * $count (null) the total number of records
	 * $return (true) returns otherwise echo's the data [if it's false]
	 * returns the html for the pagination
	 */
	public function paginate($count = null, $return = true){
		if(!empty($count)) $this->count = $count;
		$this->total = @ceil($this->count/$this->limit);

		if($this->total<=1){
			return '';
		}else{
			$strPages = '<ul class="'.$this->cls['ul'].'">';

			if($this->total>0 && $this->page>1)
				$strPages.='<li><a href="?'.$this->var.'=1" class="'.$this->cls['previous'].'">'.$this->text['newest'].'</a></li><li><a href="?'.$this->var.'='.($this->page-1).'" class="'.$this->cls['previous'].'">'.$this->text['previous'].'</a></li> ';

			for($i = $this->page, $c = 0; $i < $this->total+1 && $c < $this->display ; ++$i, ++$c)
				$strPages .= ($i === $this->page)?
						'<li class="'.$this->cls['active'].'">'.$i.'</li>':
						'<li><a href="?'.$this->var.'='.$i.'">'.$i.'</a></li>';
			
			if($this->records)
				$strPages.='<li class="'.$this->cls['active'].'">'.$this->text['records'].' '.(!$this->current?'1':$this->current).' - '.($this->current+$this->limit).'</li>';

			if($this->goto)
				$strPages.='<li class="'.$this->cls['active'].'">'.$this->text['goto'].' <form method="get"><input name="'.$this->var.'" value="'.(($this->page+1>$this->total)?($this->total):($this->page+1)).'" type="text"/>'.$this->text['total'].$this->total.' <input type="submit"></form></li>';

			if($this->page<$this->total && $this->total>1)
				$strPages.='<li><a href="?'.$this->var.'='.($this->page+1).'" class="'.$this->cls['next'].'">'.$this->text['next'].'</a></li><li><a href="?'.$this->var.'='.($this->total).'" class="'.$this->cls['next'].'">'.$this->text['oldest'].'</a></li>';

			if($return){
				return $strPages.'</ul>';
			}else{
				echo $strPages .'</ul>';
			}
		}
	}

}