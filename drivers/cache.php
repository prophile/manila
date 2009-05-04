<?php

abstract class manila_driver_cache extends manila_driver
{
	private $child = false;
	
	abstract public function cache_init ( $driver_config );
	abstract public function cache_fetch ( $key ); // return NULL on failure
	abstract public function cache_store ( $key, $value );
	abstract public function cache_delete ( $key );
	abstract public function cache_clear ();
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child']);
		$this->cache_init($driver_config);
	}
	
	public function table_list_keys ( $tname )
	{
		$cachekey = "$tname:all-keys";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return $cv;
		$k = $this->child->table_list_keys($tname);
		$this->cache_store($cachekey, $k, $this->ttl);
		return $k;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return true;
		return $this->child->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		$cachekey = "$tname:all-keys";
		$this->cache_delete($cachekey);
		return $this->child->table_insert($tname, $values);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$cachekey = "$tname:all-keys";
		$this->cache_delete($cachekey);
		$cachekey = "$tname:data:$key";
		$this->cache_store($cachekey, $values, $this->ttl);
		$this->child->table_update($tname, $key, $values);
	}
	
	public function table_delete ( $tname, $key )
	{
		$cachekey = "$tname:all-keys";
		$this->cache_delete($cachekey);
		$cachekey = "$tname:data:$key";
		$this->cache_delete($cachekey);
		$this->child->table_delete($tname, $key);
	}
	
	public function table_truncate ( $tname )
	{
		$this->cache_clear();
		$this->child->table_truncate($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		$cachekey = "$tname:data:$key";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return $cv;
		$v = $this->child->table_fetch($tname, $key);
		$this->cache_store($cachekey, $v, $this->ttl);
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
