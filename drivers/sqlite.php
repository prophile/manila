<?php

class manila_driver_sqlite extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $conn;
	private $tblconf;
	
	private function generate_table_meta ()
	{
		$this->conn->queryExec("CREATE TABLE __meta ( key TEXT PRIMARY KEY, value TEXT NOT NULL );");
	}
	
	private function generate_type ( $spec, $is_primary_key )
	{
		$parts = explode(' ', $spec);
		$main = array_shift($parts);
		if (fnmatch('integer-*', $main))
		{
			return 'INTEGER' . ($is_primary_key ? ' PRIMARY KEY' : '');
		}
		elseif (fnmatch('string-*', $main))
		{
			return 'TEXT' . ($is_primary_key ? ' PRIMARY KEY' : '');
		}
		elseif ($main == 'serial' && $is_primary_key)
		{
			return 'INTEGER PRIMARY KEY AUTOINCREMENT';
		}
		elseif ($main == 'blob')
		{
			return 'BLOB';
		}
		elseif ($main == 'boolean')
		{
			return 'INTEGER';
		}
		elseif ($main == 'timestamp' || $main == 'date')
		{
			return 'INTEGER';
		}
		elseif ($main == 'enum')
		{
			return 'TEXT';
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
		$sql = "CREATE TABLE $tbl ( key " . $this->generate_type($key, true) . ', ';
		foreach ($conf as $key => $type)
		{
			$sql .= "$key " . $this->generate_type($type, false) . ", ";
		}
		$sql = substr($sql, 0, -2);
		$sql .= ');';
		$this->conn->queryExec($sql);
		foreach ($indices as $index)
		{
			$sql = "CREATE UNIQUE INDEX $tbl" . "_$index ON $tbl($index)";
			$this->conn->queryExec($sql);
		}
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$path = $driver_config['path'];
		$need_generate = !file_exists($path);
		$this->conn = sqlite_factory($path);
		$this->tblconf = $table_config;
		if ($need_generate)
		{
			$this->generate_table_meta();
			foreach (array_keys($table_config) as $table)
			{
				$this->generate_table($table);
			}
		}
	}
	
	public function table_list_keys ( $tname )
	{
		$sql = "SELECT key FROM $tname";
		$result = $this->conn->query($sql);
		$keys = array();
		while ($row = $result->fetchArray(SQLITE3_NUM))
		{
			$keys[] = $row[0];
		}
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$sql = "SELECT key FROM $tname WHERE key = " . self::encode($key);
		if ($this->singleQuery($sql))
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
		
	public function table_insert ( $tname, $values )
	{
		$sql = sprintf("INSERT INTO $tname(%s) VALUES (%s)", implode(', ', array_keys($values)), implode(', ', array_map(array(get_class($this), 'encode'), $values)));
		$this->conn->queryExec($sql);
		return $this->conn->lastInsertRowid();
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$truevalues = array();
		foreach ($values as $val)
			$truevalues[] = self::encode($val);
		$sql = sprintf("INSERT OR REPLACE INTO $tname(key, %s) VALUES (%s, %s)", implode(', ', array_keys($values)), self::encode($key), implode(', ', $truevalues));
		$this->conn->queryExec($sql);
	}
	
	public function table_delete ( $tname, $key )
	{
		$sql = sprintf("DELETE FROM $tname WHERE key = %s", self::encode($key));
		$this->conn->queryExec($sql);
	}
	
	public function table_truncate ( $tname )
	{
		$this->conn->queryExec("DROP TABLE $tname");
		$this->generate_table($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		$sql = sprintf("SELECT * FROM $tname WHERE key = %s", self::encode($key));
		$row = $this->conn->singleQuery($sql, false);
		var_dump($row);
		unset($row['key']);
		return $row;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		// nop, sqlite maintains the index
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$sql = sprintf("SELECT key FROM $tname WHERE $field = %s", self::encode($value));
		return $this->conn->singleQuery($sql, false);
	}
	
	public function table_optimise ( $tname )
	{
		$this->conn->queryExec("ANALYZE");
		$this->conn->queryExec("VACUUM");
	}
	
	public function meta_write ( $key, $value )
	{
		$sql = sprintf("INSERT OR REPLACE INTO __meta(key, value) VALUES (%s, %s)", self::encode($key), self::encode($value));
		$this->conn->queryExec($sql);
	}
	
	public function meta_read ( $key )
	{
		$sql = sprintf("SELECT value FROM meta WERE key = %s", self::encode($key));
		$result = $this->conn->query($sql);
		$row = $result->fetchArray(SQLITE3_NUM);
		if ($row)
		{
			return $row[0];
		}
		else
		{
			return NULL;
		}
	}
	
	public function meta_list ( $pattern )
	{
		return array(); // not yet implemented
	}
}

?>
