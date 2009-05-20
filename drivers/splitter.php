<?php

class manila_driver_splitter extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $targets = array();
	
	public function __construct ( $driver_config, $table_config )
	{
		unset($driver_config['driver']);
		$tables = array_keys($table_config);
		$patterns = array();
		foreach ($driver_config as $pattern => $target)
		{
			$patterns[$pattern] = manila::get_driver($target, $table_config);
		}
		$tables[] = '__meta';
		foreach ($tables as $table)
		{
			$found = false;
			foreach ($patterns as $pattern => &$target)
			{
				if (fnmatch($pattern, $table))
				{
					$this->targets[$table] =& $target;
					$found = true;
				}
			}
			if (!$found)
			{
				die("Splitter driver cannot target table: $table\n");
			}
		}
	}
	
	public function table_list_keys ( $tname )
	{
		return $this->targets[$tname]->table_list_keys($tname);
	}
	
	public function table_key_exists ( $tname, $key )
	{
		return $this->targets[$tname]->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		return $this->targets[$tname]->table_insert($tname, $values);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$this->targets[$tname]->table_update($tname, $key, $values);
	}
	
	public function table_delete ( $tname, $key )
	{
		$this->targets[$tname]->table_delete($tname, $key);
	}
	
	public function table_truncate ( $tname )
	{
		$this->targets[$tname]->table_truncate($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		return $this->targets[$tname]->table_fetch($tname, $key);
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->targets[$tname]->table_index_edit($tname, $field, $value, $key);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$this->targets[$tname]->table_index_lookup($tname, $field, $value);
	}
	
	public function table_optimise ( $tname )
	{
		return $this->targets[$tname]->table_optimise($tname);
	}
	
	public function meta_read ( $key )
	{
		return $this->targets['__meta']->meta_read($key);
	}
	
	public function meta_write ( $key, $value )
	{
		$this->targets['__meta']->meta_write($key, $value);
	}
	
	public function meta_list ( $pattern )
	{
		return $this->targets['__meta']->meta_list($pattern);
	}
}

?>
