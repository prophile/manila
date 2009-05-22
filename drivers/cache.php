<?php

abstract class manila_driver_cache extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial, manila_interface_filesystem
{
	private $child = false;
	
	abstract public function cache_init ( $driver_config );
	abstract public function cache_fetch ( $key ); // return NULL on failure
	abstract public function cache_store ( $key, $value );
	abstract public function cache_delete ( $key );
	abstract public function cache_clear ();
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child'], $table_config);
		$this->cache_init($driver_config);
	}
	
	public function conforms ( $interface )
	{
		if ($interface == 'tables_serial') return $this->child->conforms('tables_serial');
		if ($interface == 'filesystem') return $this->child->conforms('filesystem');
		return parent::conforms($interface);
	}
	
	public function table_list_keys ( $tname )
	{
		$cachekey = "$tname:all-keys";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return $cv;
		$k = $this->child->table_list_keys($tname);
		$this->cache_store($cachekey, $k);
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
		$this->cache_store($cachekey, $values);
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
		$this->cache_store($cachekey, $v);
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
	
	public function meta_write ( $key, $value )
	{
		$this->child->meta_write($key, $value);
		$cachekey = "__meta:$key";
		if ($value === NULL)
		{
			$this->cache_delete($cachekey);
		}
		else
		{
			$this->cache_store($cachekey, $value);
		}
	}
	
	public function meta_read ( $key )
	{
		$cachekey = "__meta:$key";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return $cv;
		$val = $this->child->meta_read($key);
		$this->cache_store($cachekey, $val);
		return $val;
	}
	
	public function meta_list ( $pattern )
	{
		return $this->child->meta_list($pattern); // no caching... yet
	}
	
	public function file_exists ( $path )
	{
		$dir = dirname($path);
		$base = basename($path);
		$cachekey = "__files:index:$dir";
		if (($cv = $this->cache_fetch($cachekey)) === NULL)
		{
			$cv = array_fill_keys($this->child->file_directory_list($dir), true);
			$this->cache_store($cachekey, $cv);
		}
		return isset($cv[$base]);
	}
	
	public function file_read ( $path )
	{
		$cachekey = "__files:content:$path";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
			return $cv;
		$content = $this->child->file_read($path);
		$this->cache_store($cachekey, $content);
		return $content;
	}
	
	public function file_write ( $path, $data )
	{
		$cachekey = "__files:content:$path";
		$this->cache_store($cachekey, $data);
		$dir = dirname($path);
		$base = basename($path);
		$cachekey = "__files:index:$dir";
		if (($cv = $this->cache_fetch($cachekey)) === NULL)
		{
			$cv = array_fill_keys($this->child->file_directory_list($dir), true);
		}
		$cv[$base] = true;
		$this->cache_store($cachekey, $cv);
		$this->child->file_write($path, $data);
	}
	
	public function file_erase ( $path )
	{
		$cachekey = "__files:content:$path";
		$this->cache_delete($cachekey);
		$dir = dirname($path);
		$base = basename($path);
		$cachekey = "__files:index:$dir";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
		{
			unset($cv[$base]);
			if (count($cv) == 0)
			{
				$this->cache_delete($cachekey);
			}
			else
			{
				$this->cache_store($cachekey, $cv);
			}
		}
		$this->child->file_erase($path);
	}
	
	public function file_directory_list ( $dir )
	{
		$cachekey = "__files:index:$dir";
		if (($cv = $this->cache_fetch($cachekey)) !== NULL)
		{
			return array_keys($cv);
		}
		$v = $this->child->file_directory_list($dir);
		$this->cache_store($cachekey, array_fill_keys($v, true));
		return $v;
	}
}

?>
