<?php

class manila_driver_cache_apc extends manila_driver
{
	private $child;
	private $ttl = 0;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child']);
		if (isset($driver_config['ttl']))
			$this->ttl = $driver_config['ttl'];
	}
	
	public function table_list_keys ( $tname )
	{
		$cachekey = "$tname:all-keys";
		if (($cv = apc_fetch($cachekey)) !== false)
			return $cv;
		$k = $this->child->table_list_keys($tname);
		apc_store($cachekey, $k, $this->ttl);
		return $k;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (($cv = apc_fetch($cachekey)) !== false)
			return true;
		return $this->child->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		$cachekey = "$tname:all-keys";
		apc_delete($cachekey);
		return $this->child->table_insert($tname, $values);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$cachekey = "$tname:all-keys";
		apc_delete($cachekey);
		$cachekey = "$tname:data:$key";
		apc_store($cachekey, $values, $this->ttl);
		$this->child->table_update($tname, $key, $values);
	}
	
	public function table_delete ( $tname, $key )
	{
		$cachekey = "$tname:all-keys";
		apc_delete($cachekey);
		$cachekey = "$tname:data:$key";
		apc_delete($cachekey);
		$this->child->table_delete($tname, $key);
	}
	
	public function table_truncate ( $tname )
	{
		apc_clear_cache('user');
		$this->child->table_truncate($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (($cv = apc_fetch($cachekey)) !== false)
			return $cv;
		$v = $this->child->table_fetch($tname, $key);
		apc_store($cachekey, $v, $this->ttl);
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
		$this->child->table_optimise($tname);
	}
}

?>
