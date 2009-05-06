<?php

define('MANILA_DRIVER_PATH', str_replace('/manila.php', '', __FILE__) . '/drivers');

class manila
{
	private $driver;
	private $table_config;
	private $global_config;
	private $tables = array();
	
	private function __construct ( &$drv, $tconf, $gconf )
	{
		$this->driver =& $drv;
		$this->table_config = $tconf;
		$this->global_config = $gconf;
	}
	
	private static function load_driver ( $driver ) // returns name of object
	{
		$driver = strtolower($driver);
		$path = MANILA_DRIVER_PATH . "/$driver.php";
		require_once($path);
		return "manila_driver_" . $driver;
	}
	
	private static $global_driver_config = array();
	private static $global_table_config = array();
	
	public static function get_driver ( $id ) // this is for driver use only
	{
		$cfg = self::$global_driver_config[$id];
		$obj = self::load_driver($cfg['driver']);
		$driver = new $obj($cfg, self::$global_table_config);
		return $driver;
	}
	
	public static function &open ( $config_path )
	{
		$cfg = parse_ini_file($config_path, true);
		//var_dump($cfg);
		$driver_cfgs = array();
		$table_cfgs = array();
		foreach ($cfg as $key => $value)
		{
			if (fnmatch('driver *', $key))
			{
				$driver_cfgs[substr($key, 7)] = $value;
			}
			elseif (fnmatch('table *', $key))
			{
				$table_cfgs[substr($key, 6)] = $value;
			}
		}
		self::$global_driver_config = $driver_cfgs;
		self::$global_table_config = $table_cfgs;
		$driver = self::get_driver("master");
		self::$global_driver_config = array();
		self::$global_table_config = array();
		$obj = new manila($driver, $table_cfgs, $driver_cfgs);
		return $obj;
	}
	
	public static function migrate ( $old_config, $new_config )
	{
		$tmpname = tempnam("/tmp", "manila-migrate");
		$old = manila::open($old_config);
		$new = manila::open($new_config);
		$tables = $old->list_tables();
		foreach ($tables as $table)
		{
			echo "Migrating table: $table.";
			$old->table($table)->export($tmpname);
			echo ".";
			$new->table($table)->import($tmpname);
			echo ". done.\n";
		}
		unlink($tmpname);
	}
	
	public function list_tables ()
	{
		return array_keys($this->table_config);
	}
	
	public function &table ( $name )
	{
		if (!isset($this->tables[$name]))
		{
			if (!isset($this->table_config[$name]))
			{
				$this->tables[$name] = NULL;
			}
			else
			{
				$this->tables[$name] = new manila_table($this->driver, $this->table_config[$name], $name);
			}
		}
		return $this->tables[$name];
	}
	
	public function &__get ( $name )
	{
		return $this->table($name);
	}
	
	public function get_meta ( $meta )
	{
		$metakey = "user:$meta";
		return $this->driver->meta_read($metakey);
	}
	
	public function write_meta ( $meta, $value )
	{
		$metakey = "user:$meta";
		$this->driver->meta_write($metakey, (string)$value);
	}
}

abstract class manila_driver
{
	abstract public function __construct ( $driver_config, $table_config );
	abstract public function table_list_keys ( $tname );
	abstract public function table_key_exists ( $tname, $key );
	abstract public function table_insert ( $tname, $values ); // return key, this only used for serial keys
	abstract public function table_update ( $tname, $key, $values );
	abstract public function table_delete ( $tname, $key );
	abstract public function table_truncate ( $tname ); // includes flushing any indices
	abstract public function table_fetch ( $tname, $key );
	abstract public function table_index_edit ( $tname, $field, $value, $key );
	abstract public function table_index_lookup ( $tname, $field, $value );
	abstract public function table_optimise ( $tname );
	abstract public function meta_write ( $key, $value ); // key is a string, value is a string or NULL (= delete)
	abstract public function meta_read ( $key );
	abstract public function meta_list ( $pattern );
	
	protected static function fs_escape ( $key )
	{
		$newkey = '';
		$len = strlen($key);
		for ($i = 0; $i < $len; $i++)
		{
			$c = $key[$i];
			$chr = ord($c);
			if ($chr >= 97 && $chr <= 122)
				$newkey .= $c;
			elseif ($chr >= 48 && $chr <= 57)
				$newkey .= $c;
			else
				$newkey .= sprintf('_%02x', $chr);
		}
		return $newkey;
		// this is commented out due to a PHP bug at the time of writing, see git history for the rest of the implementations
		//return preg_replace_callback('/[^a-z0-9]/', array('manila_driver', '__fs_escape_callback'), $key);
	}
	
	protected static function fs_unescape ( $key )
	{
		$newkey = '';
		$len = strlen($key);
		for ($i = 0; $i < $len; $i++)
		{
			$c = $key[$i];
			if ($c == '_')
			{
				$temp = $key[$i + 1] . $key[$i + 2];
				$newkey .= chr(hexdec($temp));
				$i += 2;
			}
			else
			{
				$newkey .= $c;
			}
		}
		return $newkey;
	}
}

class manila_table
{
	private $driver;
	private $name;
	private $config;
	private $indices;
	private $is_serial_key;
	
	private $rowcache_key = null;
	private $rowcache_value = null;
	
	public function __construct ( &$driver, $config, $name )
	{
		$this->driver =& $driver;
		$this->name = $name;
		$this->config = $config;
		$this->is_serial_key = ($config['key'] == 'serial');
		if (isset($config['index']))
			$this->indices = explode(' ', $config['index']);
		else
			$this->indices = array();
	}
	
	public function get_name ()
	{
		return $this->name;
	}
	
	public function truncate ()
	{
		$this->driver->table_truncate($this->name);
	}
	
	public function has_serial_key ()
	{
		return $this->is_serial_key;
	}
	
	public function export ( $file )
	{
		$fp = fopen($file, "w");
		$arr = $this->config;
		unset($arr['key']);
		if (isset($arr['index']))
			unset($arr['index']);
		$basic_fields = array_keys($arr);
		$fields = array_merge(array('key'), $basic_fields);
		fputcsv($fp, $fields);
		$keys = $this->driver->table_list_keys($this->name);
		foreach ($keys as $k)
		{
			$r = $this->driver->table_fetch($this->name, $k);
			reset($basic_fields);
			$row = array($k);
			foreach ($basic_fields as $field)
			{
				$row[] = $r[$field];
			}
			fputcsv($fp, $row);
		}
		fclose($fp);
	}
	
	public function import ( $file )
	{
		$this->driver->table_truncate($this->name);
		$fp = fopen($file, "r");
		$fields = fgetcsv($fp);
		$keyfield = $fields[0];
		while ($row = fgetcsv($fp))
		{
			$values = array_combine($fields, $row);
			$key = $values[$keyfield];
			unset($values[$keyfield]);
			$this->driver->table_update($this->name, $key, $values);
			foreach ($this->indices as $idx)
			{
				$this->driver->table_index_edit($this->name, $idx, $values[$idx], $key);
			}
		}
		fclose($fp);
	}
	
	public function fetch ( $key ) // fetches row associated with key or NULL on failure
	{
		$v = $this->driver->table_fetch($this->name, $key);
		$this->rowcache_key = $key;
		$this->rowcache_value = $v;
		return $v;
	}
	
	public function edit ( $key, $value ) // pass NULL for the key to insert a new row for auto-keys, and pass NULL for the value for a deletion; returns key or NULL on failure
	{
		if ($key == $this->rowcache_key)
		{
			$oldvalue = $this->rowcache_value;
		}
		elseif (count($this->indices) && $key)
		{
			$oldvalue = $this->driver->table_fetch($this->name, $key);
		}
		else
		{
			$oldvalue = NULL;
		}
		if ($value === NULL)
		{
			if ($key !== NULL)
			{
				$this->driver->table_delete($this->name, $key);
				foreach ($this->indices as $idx)
				{
					$this->driver->table_index_edit($this->name, $idx, $oldvalue[$idx], NULL);
				}
			}
		}
		else
		{
			if ($key === NULL)
			{
				$key = $this->driver->table_insert($this->name, $value);
				foreach ($this->indices as $idx)
				{
					$this->driver->table_index_edit($this->name, $idx, $value[$idx], $key);
				}
			}
			else
			{
				$this->driver->table_update($this->name, $key, $value);
				foreach ($this->indices as $idx)
				{
					if ($value[$idx] != $oldvalue[$idx])
					{
						$this->driver->table_index_edit($this->name, $idx, $oldvalue[$idx], NULL);
						$this->driver->table_index_edit($this->name, $idx, $value[$idx], $key);
					}
				}
			}
		}
		return $key;
	}
	
	public function list_keys ()
	{
		return $this->driver->table_list_keys($this->name);
	}
	
	public function list_fields ()
	{
		$arr = $this->config;
		unset($arr['key']);
		if (isset($arr['index']))
			unset($arr['index']);
		return array_keys($arr);
	}
	
	public function lookup ( $field, $value )
	{
		if (in_array($this->indices, $field))
		{
			return $this->driver->table_index_lookup($this->name, $field, $value);
		}
		else
		{
			// slow, but correct way
			$keys = $this->driver->table_list_keys($this->name);
			foreach ($keys as $key)
			{
				$row = $this->driver->table_fetch($this->name, $key);
				if ($row[$field] == $value)
					return $key;
			}
			return null;
		}
	}
	
	public function optimise ()
	{
		$this->driver->table_optimise($this->name);
	}
}

?>
