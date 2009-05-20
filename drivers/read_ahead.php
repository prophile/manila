<?php

class manila_driver_read_ahead extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $child;
	private $readahead_table = NULL;
	private $readahead_base = NULL;
	private $readahead_length = NULL;
	private $ideal_length = 30;
	private $readahead_cache = array();
	
	private function reset_cache ()
	{
		$this->readahead_table = NULL;
		$this->readahead_base = NULL;
		$this->readahead_length = NULL;
		$this->readahead_cache = array();
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child'], $table_config, array('tables'));
		if (isset($driver_config['length']))
			$this->ideal_length = $driver_config['length'];
	}
	
	public function conforms ( $interface )
	{
		if ($interface == 'meta') return $this->child->conforms('meta');
		else return parent::conforms($interface);
	}
	
	public function table_list_keys ( $tname )
	{
		return $this->child->table_list_keys($tname);
	}
	
	public function table_key_exists ( $tname, $key )
	{
		return $this->child->table_key_exists($tname, $key);
	}

	public function table_insert ( $tname, $values )
	{
		$this->reset_cache();
		return $this->child->table_insert($tname, $values);
	}

	public function table_update ( $tname, $key, $values )
	{
		$this->reset_cache();
		$this->child->table_update($tname, $key, $values);
	}

	public function table_delete ( $tname, $key )
	{
		$this->reset_cache();
		$this->child->table_delete($tname, $key);
	}

	public function table_truncate ( $tname )
	{
		$this->reset_cache();
		$this->child->table_truncate($tname);
	}

	public function table_fetch ( $tname, $key )
	{
		if (!is_numeric($key))
			return $this->child->table_fetch($tname, $key);
		if ($tname == $this->readahead_table)
		{
			if ($key >= $this->readahead_base && $key < ($this->readahead_base + $this->readahead_length))
			{
				return $this->readahead_cache[$key];
			}
		}
		$this->readahead_table = $tname;
		$this->readahead_base = $key;
		$this->readahead_length = 0;
		for ($i = 0; $i < $this->ideal_length; $i++)
		{
			$k = $key + $i;
			$row = $this->child->table_fetch($tname, $key + $i);
			if ($i == 0)
				$result = $row;
			if ($row === NULL)
				break;
			$this->readahead_cache[$k] = $row;
			$this->readahead_length++;
		}
		return $result;
	}

	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->child->table_index_edit($tname, $field, $value, $key);
	}

	public function table_index_lookup ( $tname, $field, $value )
	{
		return $this->child->table_index_lookup($tname, $field, $value);
	}

	public function table_optimise ( $tname )
	{
		$this->child->table_optimise($tname);
	}

	public function meta_write ( $key, $value )
	{
		$this->child->meta_write($key, $value);
	}

	public function meta_read ( $key )
	{
		return $this->child->meta_read($key);
	}
	
	public function meta_list ( $pattern )
	{
		return $this->child->meta_list($pattern);
	}
}

?>
