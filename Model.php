<?php
$config = include "config/database.php";

$model = new UserModel($config);
class Model
{
	protected $host;
	protected $user;
	protected $password;
	protected $dbName;
	protected $charset;
	protected $link;
	protected $sql;
	protected $table = 'bbs_user';
	protected $cacheField;//缓存字段
	protected $cacheDir;//数据库缓存目录
	protected $prefix;//表前缀

	public $options = [
		'field'  =>  '*';
		'table'   =>   '';
		'where'   =>   '';
		'group'   =>   '';
		'having'   =>   '';
		'order'   =>   '';
		'limit'   =>   '';
		'values'   =>   '';
	];

	public function __construct(array $config)
	{
		$this->host = $config['DB_HOST'];
		$this->user = $config['DB_USER'];
		$this->password = $config['DB_PASSWORD'];
		$this->charset = $config['DB_CHARSET'];
		$this->dbName = $config['DB_NAME'];
		$this->prefix = $config['DB_PREFIX'];
		$this->link = $this->connect();//连库
		$this->table = $this->getTable();//获取表名

		$cache = $config['DB_CACHE'];
		if ($this->checkDir($cache))
		{
			$this->checkDir = $cache;
		} else {
			exit('缓存目录不存在');
		}
		$this->cacheField = $this->initCache();
		$this->options = $this->initOptions();


	}
	protected function connect()
	{
		$link = mysqli_connect($this->host,$this->user,$this->password);
		if (!$link){
			exit('数据库链接失败');
		}
		if (!mysqli_select_db($link,$this->dbName)) {
			mysqli_close($link);
			exit('选择数据库失败');
		}
		if (!mysqli_set_charset($link,$this->charset)) {
			mysqli_close($link);
			exit('字符集设置失败');
		}
		return $link;
	}

	public function select($resultType= MYSQLI_BOTH)
	{
		//select uid,username from bbs_user where uid<100 group by uid having uid>0 order by uid limit 5;
		$sql = "SELECT %FIELD%" FROM %TABLE% %WHERE% %GROUP% %HAVING% %ORDER% %LIMIT% ;
		$sql = str_replace([
								'%FIELD%',
								'%TABLE%',
								'%WHERE%',
								'%GROUP%',
								'%HAVING%',
								'%ORDER%',
								'%LIMIT%',
			],
			[
					'field'        =>       $this->options['field'],
					'table'        =>       $this->options['table'],
					'where'        =>       $this->options['where'],
					'group'        =>       $this->options['group'],
					'having'        =>       $this->options['having'],
					'order'        =>       $this->options['order'],
					'limit'        =>       $this->options['limit'],

			],sql);
		return $this->query($sql,$resultType);

	}
	public function query ($sql,$resultType)
	{
		$this->sql = $sql;
		$this->options = $this->initOptions();
		$result = mysqli_query($this->link,$sql);
		if ($result && mysqli_affected_rows($this->link) > 0){
			return mysqli_fetch_all($result,$resultType);

		}
		return false;


	}
	protected function initOptions()
	{
		unset($this->cacheField['PRI']);
		$tmp = join(',',$this->cacheField);
		return [
					'field'   =>  $tmp,
					'table'   =>  $this->table;
					'where'   =>  '',
					'group'   =>  '',
					'having'  =>  '',
					'order'   =>  '',
					'limit'   =>  '',
					'limit'   =>  ''
		];
	}
	protected function initCache()
	{
		$path = rtrim($this->cacheDir, '/'). '/' . $this->table .'.php';
		if (file_exists($path)){
			return include $path;
		}
		$sql = 'desc' . $this->table;
		$data = $this->query($sql,MYSQLI_ASSOC);
		$fields = [];
		foreach ($data as $key => $value) {
			if ($value['Key'] == 'PRI') {
				$fields['PRI'] = $value['Field'];

			}
			$fields[] = $value['Field'];
		}
		$str = "<?php \n return ". var_export($fields,true) . ";?>";

		file_put_contents($path, $str);
		return $fields;

	}
	protected function checkDir($dir)
	{
		if (!is_dir($dir)){
				return mkdir($dir,0777,true);
		}
		if (!is_readable($dir) || !is_writable($dir))
		{
			return chmod($dir, 0777);
		}
		return true;

	}
	protected function getTable()
	{
		if (!empty($this->table)){
			return $this->prefix . $this->talbe;
		}
		$className = strtolower(get_class($this));
		$className = explode('\\', $className);
		$className = array_pop($className);
		if (stripos($className, 'model') == false){
			return $this->prefix . $className;
		}
		$className = substr($className, 0, 5 );
		return $this->prefix . $className;
	}
	public function where ($where)
	{
		if (is_string($where)){
			$this->options['where'] = " where " . $where;
		} else if (is_array($where)){
			$this->options['where'] = "where" . join (" and ",$where);

		}
		return $this;
	}
	public function group($group)
	{
		if (is_string($group)){
			$this->options['group'] = " group by " . $group;

		} else (is_array($group))
		{
			$this->options['group'] = " group by " . join(',', $group);
		}
		return $this;
	}

	public function having($having)
	{
		if (is_string($having))
		{
			$this->options['having'] = " having " . $having;
		} else if (is_array($having)){
			$this->options['having'] = " having " . join(" and ", $having);
		}
		return $this;
	}

	public function order ($order)
	{
		if (is_string($order)){
			$this->options['order'] = "order by " . $order;
		} else if (is_array($order)){
			$this->options['order'] = " order by " . join(',', $order);
		}
		return $this;
	}

	public function limit($limit)
	{
		if (is_string($limit)){
			$this->options['limit'] = "order by " . $limit;
		} else if (is_array($limit)){
			$this->options['limit'] = " order by " . join(',', $limit);
		}
		return $this;
	}
	public function field($field)
	{
		$this->options['field'] = $field;
		return $this;
	}

	protected function addQuote($data)
	{
		if (is_array($data)){
			foreach ($data as $key => $value) {
				if (is_string(value)) {
					$data[$key] = "'$value'";
				}
			}

		}
		return $data;
	}
	protected function validField($data)
	{
		$cacheField = array_flip($this->cacheField);
		$data = array_intersect_key($data, $cacheField);
		return $data;
	}
	public function __call($name,$paras)
	{
		if (substr($name, 0, 5) == 'getBy') {
			$fieldName = substr($name, 5);
			return $this->getBy($fieldName,$paras);
		}

	}
	public function getBy($name,$value)
	{
		$name = strtolower($str);
		if (count($value) > 0){
			if (is_string($value[0])){
				$this->options['where'] = ' where '.$name . " = '" .$value[0] ."'";

			} else {
				$this->options['where'] = 'where' .$name. ' = ' .$value[0];
			}
		}
		return $this->select(MYSQLI_ASSOC);
	}


	//更新语句
	//
	public function update(array $data)
	{
		$data = $this->addQuote($data);
		$data = $this->validField($data);

		$str = $this->array2String($data);
		$this->ooptions['set'] = $str;

		$sql = "UPDATE %TABLE% SET %SET% %WHERE% %ORDER% %LIMIT% ";
		$sql = str_replace(
			[
				'%TABLE%',
				'%SET%',
				'%WHERE%',
				'%ORDER%',
				'%LIMIT%',

			],[
					'talbe'   =>   $this->options['table'],
					'set'   =>   $this->options['set'],
					'where'   =>   $this->options['where'],
					'order'   =>   $this->options['order'],
					'limit'   =>   $this->options['limit']
			], $sql);
		return $this->exec($sql, false);

		protected function array2String($data) 
		{
			$str = '';
			if (is_array($data)){
				foreach ($data as $key => $value) {
					$str .= $key . ' = ' .$value . ',';
				}
			}
			return rtrim($str,',');


		}



	}


	protected function exec($sql, $isInsertId = false)
	{
	
		$this->sql = $sql;
		$this->options = $this->initOptions();
		$result = mysqli_query($this->link, $sql);
		if ($result && $isInsertId)
		{
			return mysqli_insert_id($this->link);
		}
		return $result;
	}

	public function insert(array $data)
	{
		$data = $this->addQuote($data);

		$data = $this->validField($data);

		$this->options['field'] = join(',',array_keys($data));
		$this->options['values'] = join(',', array_values($data));

		$sql = "insert into %TABLE% (%FIELED%) VALUES (%VALUES%)";
		$sql = str_replace([
					'%TABLE%',
					'%FIELD%',
					'%VALUES%'

			],[

				'table'   =>  $this->options['table'],
				'field'   =>  $this->options['field'],
				'values'   =>  $this->options['values'],
			
			], $sql);
		return $this->exec($sql, $isInsertId = false);

	}


























}



























