<?php

require_once(MANILA_DRIVER_PATH . '/cache.php');

class manila_driver_cache_local extends manila_driver_cache
{
	private $cache = array();
	
	public function cache_init ( $driver_config )
	{
	}
	
	public function cache_fetch ( $key )
	{
		return isset($this->cache[$key]) ? $this->cache[$key] : NULL;
	}
	
	public function cache_store ( $key, $value )
	{
		$this->cache[$key] = $value;
	}
	
	public function cache_delete ( $key )
	{
		if (isset($this->cache[$key]))
			unset($this->cache[$key]);
	}
	
	public function cache_clear ()
	{
		$this->cache = array();
	}
}

?>
