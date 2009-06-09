<?php

require_once(MANILA_DRIVER_PATH . '/index_translator.php');

class manila_driver_fs_object_database extends manila_driver implements manila_interface_filesystem
{
	private $child;
	private $gc_count;
	private $refs = NULL;
	
	private function hash ( $data )
	{
		return hash("tiger192,4", $data);
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child'], $table_config, array('filesystem'));
		$this->gc_count = 200;
		if ($this->child->file_exists("info/gc-count"))
			$this->gc_count = (int)$this->child->file_read("info/gc-count");
	}
	
	private function get_refs ()
	{
		if ($this->refs !== NULL)
			return $this->refs;
		$content = $this->child->file_read("info/refs");
		if (!$content)
		{
			$this->refs = array();
			return array();
		}
		$lines = explode("\n", $content);
		$refs = array();
		foreach ($lines as $line)
		{
			$line = trim($line);
			if ($line == '')
				continue;
			$hash = substr($line, 0, 48);
			$name = substr($line, 49);
			$refs[$name] = $hash;
		}
		$this->refs = $refs;
		return $refs;
	}
	
	private function set_refs ( $db )
	{
		$data = '';
		foreach ($db as $name => $hash)
		{
			$data .= "$hash $name\n";
		}
		$this->child->file_write("info/refs", $data);
		$this->refs = $db;
	}
	
	private function write_object ( $data )
	{
		$hash = $this->hash($data);
		$path = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . substr($hash, 4);
		if (!$this->child->file_exists("objects/$path"))
		{
			$this->child->file_write("objects/$path", gzdeflate($data, 6));
		}
		return $hash;
	}
	
	private function read_object ( $hash )
	{
		$path = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . substr($hash, 4);
		$data = $this->child->file_read("objects/$path");
		if (!$data)
		{
			printf("Tried to read nonexistant object '%s'\n", $hash);
			return NULL;
		}
		return gzinflate($data);
	}
	
	private function gc ( $weight )
	{
		$this->gc_count -= $weight;
		if ($this->gc_count < 0)
		{
			$refs = $this->get_refs();
			$objects = $this->child->file_directory_list("objects");
			$objs_cull = array();
			foreach ($objects as $object)
			{
				$objs_cull[$object] = true;
			}
			foreach ($refs as $ref => $hash)
			{
				unset($objs_cull[$hash]);
			}
			foreach ($objs_cull as $obj => $val)
			{
				$this->child->file_erase("objects/$obj");
			}
			$this->gc_count = 200;
		}
		$this->child->file_write("info/gc-count", $this->gc_count);
	}
	
	private function dir_list ( $path )
	{
		$refs = $this->get_refs();
		$hash = isset($refs[$path]) ? $refs[$path] : NULL;
		if (!$hash)
			return array();
		$data = $this->read_object($hash);
		$lines = explode("\n", $data);
		$paths = array();
		foreach ($lines as $line)
		{
			$line = trim($line);
			if ($line == '')
				continue;
			$hash = substr($line, 0, 48);
			$path = substr($line, 49);
			$paths[$path] = $hash;
		}
		return $paths;
	}
	
	private function dir_write_list ( $path, $newlist )
	{
		$data = "";
		foreach ($newlist as $path => $hash)
		{
			$data .= "$hash $path\n";
		}
		if ($data)
		{
			$hash = $this->write_object($data);
			$refs = $this->get_refs();
			$refs[$path] = $hash;
			$this->set_refs($refs);
		}
		else
		{
			$refs = $this->get_refs();
			unset($refs[$path]);
			$this->set_refs($refs);
		}
		$this->gc(4);
	}
	
	public function file_exists ( $path )
	{
		$dir = dirname($path);
		$list = $this->dir_list($path);
		return isset($list[basename($path)]);
	}
	
	public function file_read ( $path )
	{
		$dir = dirname($path);
		$base = basename($path);
		$list = $this->dir_list($dir);
		$hash = isset($list[$base]) ? $list[$base] : NULL;
		if (!$hash)
			return NULL;
		return $this->read_object($hash);
	}
	
	public function file_write ( $path, $data )
	{
		$hash = $this->write_object($data);
		$dir = dirname($path);
		$base = basename($path);
		$list = $this->dir_list($dir);
		$list[$base] = $hash;
		$this->dir_write_list($dir, $list);
		$this->gc(3);
	}
	
	public function file_erase ( $path )
	{
		$dir = dirname($path);
		$base = basename($path);
		$list = $this->dir_list($dir);
		unset($list[$base]);
		$this->dir_write_list($dir, $list);
		$this->gc(10);
	}
	
	public function file_directory_list ( $dir )
	{
		return array_keys($this->dir_list($dir));
	}
}
