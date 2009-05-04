<?php

class manila_driver_partition extends manila_driver
{
	private $children = array();
	
	public function __construct ( $driver_config, $tablee_config )
	{
		foreach ($driver_config['child'] as $child)
		{
			$this->children[] = manila::get_driver($child);
		}
	}
	
	private function partition ( $key )
	{
		if (is_integer($key)) $key = $key / 128; // exploit locality of reference
		$crc = crc32((string)$key) & 0x7FFFFFFF;
		return $crc % count($this->children);
	}
	
	public function table_list_keys ( $tname )
	{
		$list = array();
		foreach ($this->children as &$child)
		{
			$sublist = $child->table_list_keys($tname);
			$list = array_merge($list, $sublist);
		}
		return $list;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$part = $this->partition($key);
		return $this->children[$part]->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		die("Unable to partition driver with serial keys.\n");
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$part = $this->partition($key);
		$this->children[$part]->table_update($tname, $key, $values);
	}
	
	public function table_delete ( $tname, $key )
	{
		$part = $this->partition($key);
		$this->children[$part]->table_update($tname, $key);
	}
	
	public function table_truncate ( $tname )
	{
		foreach ($this->children as &$child)
		{
			$child->table_truncate($tname);
		}
	}
	
	public function table_fetch ( $tname, $key )
	{
		$part = $this->partition($key);
		return $this->children[$part]->table_fetch($tname, $key);
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->children[0]->table_index_edit($tname, $field, $value, $key);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$count = count($this->children);
		for ($i = 0; $i < $count; $i++)
		{
			$value = $this->children[$i]->table_index_lookup($tname, $field, $value);
			if ($value !== NULL)
				return $value;
		}
		return NULL;
	}
	
	public function table_optimise ( $tname )
	{
		foreach ($this->children as &$child)
		{
			$child->table_optimise($tname);
		}
	}
	
	public function meta_read ( $key )
	{
		$part = $this->partition($key);
		return $this->children[$part]->meta_read($key);
	}
	
	public function meta_write ( $key, $value )
	{
		$part = $this->partition($key);
		$this->children[$part]->meta_write($key, $value);
	}
}

?>
