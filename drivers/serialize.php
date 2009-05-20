<?php

require_once(MANILA_DRIVER_PATH . '/index_translator.php');

class manila_driver_serialize extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $root;
	
	private static function rm ( $fileglob )
	{
		if (is_string($fileglob))
		{
			if (is_file($fileglob))
			{
				return unlink($fileglob);
			}
			elseif (is_dir($fileglob))
			{
				$ok = self::rm("$fileglob/*");
				if (!$ok)
				{
					return false;
				}
				return rmdir($fileglob);
			}
			else
			{
				$matching = glob($fileglob);
				if ($matching === false)
				{
					trigger_error(sprintf('No files match supplied glob %s', $fileglob), E_USER_WARNING);
					return false;
				}       
				$rcs = array_map(array('manila_driver_serialize', 'rm'), $matching);
				if (in_array(false, $rcs))
				{
					return false;
				}
			}       
		}
		elseif (is_array($fileglob))
		{
			$rcs = array_map(array('manila_driver_serialize', 'rm'), $fileglob);
			if (in_array(false, $rcs))
			{
				return false;
			}
		}
		else
		{
			trigger_error('Param #1 must be filename or glob pattern, or array of filenames or glob patterns', E_USER_ERROR);
			return false;
		}

		return true;
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->root = $driver_config['directory'];
		if (!file_exists($this->root))
			mkdir($this->root);
		foreach (array_keys($table_config) as $tbl)
		{
			$path = $this->root . "/$tbl";
			if (!file_exists($path))
				mkdir($path);
		}
	}
	
	public function table_list_keys ( $tname )
	{
		$path = $this->root . '/' . $tname;
		$paths = glob($path . '/*.obj');
		foreach ($paths as $key => $item)
		{
			$paths[$key] = manila_driver::fs_unescape(str_replace(array($path . '/', '.obj'), array('', ''), $item));
		}
		return $paths;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		$path = $this->root . "/$tname/$key.obj";
		return file_exists($path);
	}
	
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$field = manila_driver::fs_escape($field);
		$path = $this->root . "/$tname/.index.$field";
		$c = file_exists($path) ? new manila_index(file_get_contents($path)) : new manila_index();
		$c->set($value, $key);
		if ($c->was_changed())
			file_put_contents($path, $c->to_data());
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$field = manila_driver::fs_escape($field);
		$path = $this->root . "/$tname/.index.$field";
		$c = file_exists($path) ? new manila_index(file_get_contents($path)) : new manila_index();
		return $c->get($value);
	}
	
	public function table_insert ( $tname, $values )
	{
		$serialpath = $this->root . "/$tname/.serial";
		if (file_exists($serialpath))
		{
			$id = file_get_contents($serialpath);
			$id = (int)$id;
			file_put_contents($serialpath, (string)($id + 1));
		}
		else
		{
			$id = 1;
			file_put_contents($serialpath, '2');
		}
		$this->table_update($tname, $id, $values);
		return $id;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$key = manila_driver::fs_escape($key);
		$data = serialize($values);
		$path = $this->root . "/$tname/$key.obj";
		file_put_contents($path, $data);
	}
	
	public function table_delete ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		$path = $this->root . "/$tname/$key.obj";
		@unlink($path);
	}
	
	public function table_truncate ( $tname )
	{
		self::rm($this->root . "/$tname/*.obj");
		self::rm($this->root . "/$tname/.index.*");
		self::rm($this->root . "/$tname/.serial");
		$this->fps = array();
	}
	
	public function table_fetch ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		$path = $this->root . "/$tname/$key.obj";
		if (!file_exists($path)) return NULL;
		$content = @file_get_contents($path);
		if (!$content) return NULL;
		return unserialize($content);
	}
	
	public function table_optimise ( $tname )
	{
	}
	
	public function meta_read ( $key )
	{
		$key = manila_driver::fs_escape($key);
		$path = $this->root . "/.meta.$key";
		return file_exists($path) ? file_get_contents($path) : NULL;
	}
	
	public function meta_write ( $key, $value )
	{
		$key = manila_driver::fs_escape($key);
		$path = $this->root . "/.meta.$key";
		if ($value === NULL && file_exists($path))
		{
			unlink($path);
		}
		else
		{
			file_put_contents($path, $value);
		}
	}
	
	public function meta_list ( $pattern )
	{
		$targs = array();
		$files = glob($this->root . "/.meta.*");
		foreach ($files as $file)
		{
			$file = str_replace($this->root . "/.meta.");
			$file = manila_driver::fs_unescape($file);
			if (fnmatch($pattern, $file))
				$targs[] = $file;
		}
		return $targs;
	}
}

?>
