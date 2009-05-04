<?php

require_once(MANILA_DRIVER_PATH . '/cache.php');

class manila_driver_cache_memcache extends manila_driver_cache
{
	private $ttl = 0;
	private $prefix = '';
	private $memcache;
	
	public function cache_init ( $driver_config )
	{
		$this->memcache = new Memcache;
		if (isset($driver_config['ttl']))
			$this->ttl = $driver_config['ttl'];
		if (isset($driver_config['prefix']))
			$this->prefix = $driver_config['prefix'] . ':';
		$servers = (array)$driver_config['servers'];
		foreach ($servers as $server)
		{
			$parts = explode($server);
			$host = $parts[0];
			$port = isset($parts[1]) ? (integer)$parts[1] : 11211;
			$persistent = isset($parts[2]) ? (boolean)$parts[2] : true;
			$weight = isset($parts[3]) ? $parts[3] : 10;
			$this->memcache->addServer($host, $port, $persistent, $weight);
		}
	}
	
	public function cache_fetch ( $key )
	{
		$v = $this->memcache->get($this->prefix . $key);
		if ($v === false)
			return NULL;
		return $v;
	}
	
	public function cache_store ( $key, $value )
	{
		$this->memcache->set($this->prefix . $key, $value, 0, $this->ttl);
	}
	
	public function cache_delete ( $key )
	{
		$this->memcache->delete($this->prefix . $key);
	}
	
	public function cache_clear ()
	{
		$this->memcache->flush();
	}
}

?>
