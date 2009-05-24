<?php

define('MANILA_INCLUDE_PATH', str_replace('/manila.php', '', __FILE__) . '/include');
define('MANILA_DRIVER_PATH', str_replace('/manila.php', '', __FILE__) . '/drivers');

require_once(MANILA_INCLUDE_PATH . '/driver.php');
require_once(MANILA_INCLUDE_PATH . '/sql.php');

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
	
	public function sql ( $statement )
	{
		__manila_sql($this, $statement);
	}
	
	private static function load_driver ( $driver ) // returns name of object
	{
		$driver = strtolower($driver);
		$path = MANILA_DRIVER_PATH . "/$driver.php";
		require_once($path);
		return "manila_driver_" . $driver;
	}
	
	private static $global_driver_config = array();
	
	public static function get_driver ( $id, $tableconf, $required_interfaces = array() ) // this is for driver use only
	{
		if (!isset(self::$global_driver_config[$id]))
			debug_print_backtrace();
		$cfg = self::$global_driver_config[$id];
		$obj = self::load_driver($cfg['driver']);
		$driver = new $obj($cfg, $tableconf);
		foreach ($required_interfaces as $ri)
		{
			if (!$driver->conforms($ri))
				die("Driver '$id' does not conform to required interface $ri");
		}
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
		$driver = self::get_driver("master", $table_cfgs, array("meta", "tables", "tables_serial"));
		self::$global_driver_config = array();
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
	
	public function get_key_field_name ()
	{
		if (isset($this->config['key_field']))
			return $this->config['key_field'];
		else
			return 'key';
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
		$arr = array();
		foreach ($this->config as $key => $value)
		{
			if (fnmatch('field.*', $key))
				$arr[] = substr($key, 6);
		}
		return $arr;
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
