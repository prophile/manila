<?php

class manila_driver_cache_local extends manila_driver
{
	private $caches = array();
	private $child;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child']);
	}
	
	public function table_list_keys ( $tname )
	{
		$cachekey = "$tname:all-keys";
		if (isset($this->caches[$cachekey]))
			return $this->caches[$cachekey];
		$k = $this->child->table_list_keys($tname);
		$this->caches[$cachekey] = $k;
		return $k;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (isset($this->caches[$cachekey]))
			return true;
		return $this->child->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		$cachekey = "$tname:all-keys";
		if (isset($this->caches[$cachekey]))
			unset($this->caches[$cachekey]);
		return $this->child->table_insert($tname, $values);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$cachekey = "$tname:all-keys";
		if (isset($this->caches[$cachekey]))
			unset($this->caches[$cachekey]);
		$cachekey = "$tname:data:$key";
		$this->caches[$cachekey] = $values;
		$this->child->table_update($tname, $key, $values);
	}
	
	public function table_delete ( $tname, $key )
	{
		$cachekey = "$tname:all-keys";
		if (isset($this->caches[$cachekey]))
			unset($this->caches[$cachekey]);
		$cachekey = "$tname:data:$key";
		if (isset($this->caches[$cachekey]))
		{
			unset($this->caches[$cachekey]);
		}
		$this->child->table_delete($tname, $key);
	}
	
	public function table_truncate ( $tname )
	{
		$this->caches = array();
		$this->child->table_truncate($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (isset($this->caches[$cachekey]))
		{
			return $this->caches[$cachekey];
		}
		$v = $this->child->table_fetch($tname, $key);
		$this->caches[$cachekey] = $v;
		return $v;
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
		$this->caches = array();
		$this->child->table_optimise($tname);
	}
}

?>
