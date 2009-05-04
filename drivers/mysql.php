<?php

class manila_driver_mysql extends manila_driver
{
	private $conn = NULL;
	private $tblconf;
	private $drvconf;
	private $meta = false;
	private $keysubs = array();
	
	private function get_key_field ( $table )
	{
		if (isset($this->keysubs[$table]))
			return $this->keysubs[$table];
		return 'key';
	}
	
	private function generate_table_meta ()
	{
		$this->conn->query("CREATE TABLE `__meta` ( `key` CHAR(128) PRIMARY KEY, `value` TEXT NOT NULL )");
	}
	
	private function generate_type ( $spec, $is_primary_key )
	{
		$parts = explode(' ', $spec);
		$main = array_shift($parts);
		if (fnmatch('integer-*', $main))
		{
			return 'INTEGER' . ($is_primary_key ? ' PRIMARY KEY' : '');
		}
		elseif ($main == 'text')
		{
			return 'TEXT';
		}
		elseif ($main == 'string-short')
		{
			return 'CHAR(40)' . ($is_primary_key ? ' PRIMARY KEY' : '');
		}
		elseif ($main == 'string-long')
		{
			return $is_primary_key ? 'CHAR(255) PRIMARY KEY' : 'VARCHAR(255)';
		}
		elseif (fnmatch('string-*', $main))
		{
			return 'TEXT' . ($is_primary_key ? ' PRIMARY KEY' : '');
		}
		elseif ($main == 'serial' && $is_primary_key)
		{
			return 'INTEGER PRIMARY KEY AUTO_INCREMENT';
		}
		elseif ($main == 'blob')
		{
			return 'BLOB';
		}
		elseif ($main == 'boolean')
		{
			return 'BOOLEAN';
		}
		elseif ($main == 'timestamp' || $main == 'date')
		{
			return 'INTEGER'; // for now
		}
		elseif ($main == 'enum')
		{
			return 'VARCHAR(128)';
		}
		elseif (fnmatch('float-*', $main) || $main == 'decimal')
		{
			return 'REAL';
		}
	}
	
	private function generate_table ( $tbl )
	{
		$conf = $this->tblconf[$tbl];
		$key = $conf['key'];
		unset($conf['key']);
		$indices = array();
		if (isset($conf['index']))
		{
			$indices = explode(' ', $conf['index']);
			unset($conf['index']);
		}
		$k = $this->get_key_field($tbl);
		$sql = "CREATE TABLE `$tbl` ( `$k` " . $this->generate_type($key, true) . ', ';
		foreach ($conf as $key => $type)
		{
			$sql .= "`$key` " . $this->generate_type($type, false) . ", ";
		}
		$sql = substr($sql, 0, -2);
		$sql .= ' )';
		$this->conn->query($sql);
		foreach ($indices as $index)
		{
			$sql = "CREATE UNIQUE INDEX `$tbl" . "_$index` ON `$tbl`(`$index`)";
			$this->conn->query($sql);
		}
	}
	
	private function init ()
	{
		$driver_config = $this->drvconf;
		$table_config = $this->tblconf;
		$this->conn = new MySQLi($driver_config['host'], $driver_config['username'], $driver_config['password'], $driver_config['database']);
		$result = $this->conn->query("SHOW TABLES");
		if ($result->num_rows == 0)
		{
			printf("[MySQL] setting up tables\n");
			if ($this->meta)
				$this->generate_table_meta();
			foreach (array_keys($table_config) as $table)
			{
				$this->generate_table($table);
			}
		}
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->drvconf = $driver_config;
		$this->tblconf = $table_config;
		$this->meta = isset($driver_config['meta']) ? $driver_config['meta'] : true;
		if (isset($driver_config['keysubs']))
		{
			$parts = explode(' ', $driver_config['keysubs']);
			foreach ($parts as $part)
			{
				$arr = explode(':', $part);
				$k = $arr[0];
				$v = $arr[1];
				$this->keysubs[$k] = $v;
			}
		}
	}
	
	public function table_list_keys ( $tname )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$sql = "SELECT `$k` FROM `$tname`";
		$result = $this->conn->query($sql);
		$keys = array();
		while ($row = $result->fetch_array())
		{
			$keys[] = $row[0];
		}
		return $keys;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$sql = "SELECT `$k` FROM `$tname` WHERE `key` = " . self::encode($key);
		$result = $this->query($sql);
		if ($result->num_rows > 0)
			return true;
		else
			return false;
	}
	
	public static function encode ( $value )
	{
		if (is_integer($value) || is_float($value))
			return (string)$value;
		elseif (is_string($value))
			return "'" . addslashes($value) . "'";
	}
	
	public static function enbacktick ( $value )
	{
		return '`' . $value . '`';
	}
	
	public function table_insert ( $tname, $values )
	{
		if (!$this->conn) $this->init();
		$sql = sprintf("INSERT INTO `$tname`(%s) VALUES (%s)", implode(', ', array_map(array('manila_driver_mysql', 'enbacktick'), array_keys($values))), implode(', ', array_map(array(get_class($this), 'encode'), $values)));
		$this->conn->query($sql);
		return $this->conn->insert_id;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$truevalues = array();
		foreach ($values as $val)
			$truevalues[] = self::encode($val);
		$sql = sprintf("REPLACE INTO `$tname`(`$k`, %s) VALUES (%s, %s)", implode(', ', array_map(array('manila_driver_mysql', 'enbacktick'), array_keys($values))), self::encode($key), implode(', ', $truevalues));
		$this->conn->query($sql);
	}
	
	public function table_delete ( $tname, $key )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$sql = sprintf("DELETE FROM `$tname` WHERE `$k` = %s", self::encode($key));
		$this->conn->query($sql);
	}
	
	public function table_truncate ( $tname )
	{
		if (!$this->conn) $this->init();
		$this->conn->query("TRUNCATE TABLE `$tname`");
		$this->conn->query("ALTER TABLE `$tname` AUTO_INCREMENT = 1");
	}
	
	public function table_fetch ( $tname, $key )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$sql = sprintf("SELECT * FROM `$tname` WHERE `$k` = %s", self::encode($key));
		$result = $this->conn->query($sql, false);
		$row = $result->fetch_assoc();
		if ($row)
		{
			unset($row['key']);
		}
		return $row;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		// nop, MySQL maintains the index
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		if (!$this->conn) $this->init();
		$k = $this->get_key_field($tname);
		$sql = sprintf("SELECT `$k` FROM `$tname` WHERE `$field` = %s", self::encode($value));
		$result = $this->conn->query($sql);
		$row = $result->fetch_array();
		if ($row)
			return $row[0];
		else
			return NULL;
	}
	
	public function table_optimise ( $tname )
	{
		if (!$this->conn) $this->init();
		$this->conn->query("CHECK TABLE `$tname`");
		$this->conn->query("ANALYZE TABLE `$tname`");
		$this->conn->query("OPTIMIZE TABLE `$tname`");
	}
	
	public function meta_write ( $key, $value )
	{
		if (!$this->meta) return;
		if (!$this->conn) $this->init();
		$sql = sprintf("INSERT OR REPLACE INTO `__meta`(`key`, `value`) VALUES (%s, %s)", self::encode($key), self::encode($value));
		$this->conn->query($sql);
	}
	
	public function meta_read ( $key )
	{
		if (!$this->meta) return NULL;
		if (!$this->conn) $this->init();
		$sql = sprintf("SELECT `value` FROM `__meta` WERE `key` = %s", self::encode($key));
		$result = $this->conn->query($sql);
		$row = $result->fetch_assoc();
		if ($row)
		{
			return $row[0];
		}
		else
		{
			return NULL;
		}
	}
}

?>
