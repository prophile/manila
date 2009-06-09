<?php

define('MANILA_INCLUDE_PATH', str_replace('/manila.php', '', __FILE__) . '/include');
define('MANILA_DRIVER_PATH', str_replace('/manila.php', '', __FILE__) . '/drivers');

require_once(MANILA_INCLUDE_PATH . '/driver.php');
require_once(MANILA_INCLUDE_PATH . '/sql.php');

/**
 * A single manila database
 */
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
	
	/**
	 * Execute one or more SQL statements.
	 * @param $statement The SQL to execute.
	 * @return array An array of results
	 */
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
	
	/**
	 * Open a Manila DB
	 * @param string $config_path The path to the configuration file
	 * @return manila The manila DB
	 */
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
	
	/**
	 * Migrate data between two config files
	 * @param string $old_config The path to the old config file
	 * @param string $new_config The path to the new config file
	 */
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
	
	/**
	 * Fetch a list of tables for this DB
	 * @return array Tables in this DB
	 */
	public function list_tables ()
	{
		return array_keys($this->table_config);
	}
	
	/**
	 * Get a specific table of the DB
	 * @param string $name The name of the table to fetch
	 * @return manila_table The table for use
	 */
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
	
	/**
	 * @see manila::table
	 */
	public function &__get ( $name )
	{
		return $this->table($name);
	}
	
	/**
	 * Get DB-global metadata
	 * @param string $meta The key to fetch
	 */
	public function get_meta ( $meta )
	{
		$metakey = "user:$meta";
		return $this->driver->meta_read($metakey);
	}
	
	/**
	 * Set DB-global metadata
	 * @param string $meta The key to write
	 * @param string $value The value to write for the given key
	 */
	public function write_meta ( $meta, $value )
	{
		$metakey = "user:$meta";
		$this->driver->meta_write($metakey, (string)$value);
	}
}

/**
 * One table in a Manila DB
 */
class manila_table
{
	private $driver;
	private $name;
	private $config;
	private $indices;
	private $is_serial_key;
	
	private $rowcache_key = null;
	private $rowcache_value = null;
	
	/**
	 * Not really public - do not use
	 */
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
	
	/**
	 * Get the name of the key field
	 * @return string The key field name
	 */
	public function get_key_field_name ()
	{
		if (isset($this->config['key_field']))
			return $this->config['key_field'];
		else
			return 'key';
	}
	
	/**
	 * Get the name of the table
	 * @return string The table name
	 */
	public function get_name ()
	{
		return $this->name;
	}
	
	/**
	 * Remove all data from the table
	 */
	public function truncate ()
	{
		$this->driver->table_truncate($this->name);
	}
	
	/**
	 * Whether or not this table has a serial (automatically incrementing) key
	 * @return bool Serial key?
	 */
	public function has_serial_key ()
	{
		return $this->is_serial_key;
	}
	
	/**
	 * Export the table to a CSV file
	 * @param string $file The path to which to export
	 */
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
	
	/**
	 * Import data from a CSV file
	 * @param string $file The name of the file
	 */
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
	
	/**
	 * Fetch the row for a given key
	 * @param mixed $key The row key
	 * @return array The associated row
	 */
	public function fetch ( $key ) // fetches row associated with key or NULL on failure
	{
		$v = $this->driver->table_fetch($this->name, $key);
		$this->rowcache_key = $key;
		$this->rowcache_value = $v;
		return $v;
	}
	
	/**
	 * Edit a row in the database.
	 * @param mixed $key The row key for editing, or NULL with serial tables to insert a new row.
	 * @param array $value The associative array of values for this row, or NULL to delete the row.
	 */
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
	
	/**
	 * List all keys in this table
	 * @return array All keys
	 */
	public function list_keys ()
	{
		return $this->driver->table_list_keys($this->name);
	}
	
	/**
	 * List all fields in this table, excluding the key
	 * @return array All fields
	 */
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
	
	/**
	 * Perform a value-key lookup in this table.
	 * @param string $field The field to use in the lookup
	 * @param string $value The value for which to search
	 * @return mixed $key The key, or NULL
	 */
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
	
	/**
	 * Run backend-specific optimisations on this table, as maintainance
	 */
	public function optimise ()
	{
		$this->driver->table_optimise($this->name);
	}
}

?>
