<?php

require_once(MANILA_DRIVER_PATH . '/index_translator.php');

class manila_driver_iniconf extends manila_driver implements manila_interface_tables
{
	private $child;
	private $indices = array();
	private $files = array();
	
	private function table_get ( $tname, $file )
	{
		$path = "$tname:$file";
		if (isset($this->files[$path]))
			return $this->files[$path];
		$data = $this->child->file_read("$tname/$file.ini");
		$ini = parse_ini_string($data, true);
		$this->files[$path] = $ini;
		unset($data);
		return $ini;
	}
	
	private function table_index_regenerate ( $tname )
	{
		$index = new manila_index;
		$files = $this->child->file_directory_list("$tname");
		foreach ($files as $file)
		{
			if (fnmatch($file, "*.ini"))
			{
				$name = substr($file, 0, -4);
				$content = $this->table_get($tname, $name);
				$keys = array_keys($content);
				unset($content);
				foreach ($keys as $key)
				{
					$index->set($key, $name);
				}
			}
		}
		$this->indices[$tname] = $index;
	}
	
	private function table_index ( $tname )
	{
		if (isset($this->indices[$tname]))
			return $this->indices[$tname];
		if ($this->child->file_exists(".index/$tname.idx"))
		{
			$content = $this->child->file_read(".index/$tname.idx");
			$this->indices[$tname] = new manila_index($content);
		}
		else
		{
			$this->table_index_regenerate($tname);
		}
		return $this->indices[$tname];
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child'], array(), array('filesystem'));
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$idx = $this->table_index($tname);
		return isset($idx[$key]);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		// silently ignore writes
	}
	
	public function table_delete ( $tname, $key )
	{
	}
	
	public function table_truncate ( $tname )
	{
	}
	
	public function table_fetch ( $tname, $key )
	{
		$idx = $this->table_index($tname);
		if (isset($idx[$key]))
		{
			$source = $idx[$key];
			$content = $this->table_get($tname, $source);
			return $content[$key];
		}
		return NULL;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		return NULL; // just for the moment
	}
	
	public function table_optimise ( $tname )
	{
	}
}

?>
