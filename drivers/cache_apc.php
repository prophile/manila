<?php

require_once(MANILA_DRIVER_PATH . '/cache.php');

class manila_driver_cache_apc extends manila_driver_cache
{
	private $ttl = 0;
	private $prefix = 'manila';
	
	public function cache_init ( $driver_config )
	{
		if (isset($driver_config['ttl']))
			$this->ttl = $driver_config['ttl'];
		if (isset($driver_config['prefix']))
			$this->prefix = $driver_config['prefix'] . ':';
	}
	
	public function cache_fetch ( $key )
	{
		$v = apc_fetch($this->prefix . $key, $found);
		if (!$found)
			return NULL;
		return $v;
	}
	
	public function cache_store ( $key, $value )
	{
		apc_store($this->prefix . $key, $value, $this->ttl);
	}
	
	public function cache_delete ( $key )
	{
		apc_delete($this->prefix . $key);
	}
	
	public function cache_clear ()
	{
		apc_clear_cache('user');
	}
}

?>
