<?php

require_once(MANILA_DRIVER_PATH . '/index_translator.php');

class manila_driver_serialize extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $child;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver('child', array(), array('filesystem'));
	}
	
	public function table_list_keys ( $tname )
	{
		$contents = $this->child->file_directory_list("tables/$tname");
		foreach ($paths as $key => $item)
		{
			if (fnmatch('*.obj', $item))
				$paths[$key] = manila_driver::fs_unescape(str_replace(array($path . '/', '.obj'), array('', ''), $item));
			else
				unset($paths[$key]);
		}
		return $paths;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		return $this->child->file_exists("tables/$tname/$key.obj");
	}
	
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$field = manila_driver::fs_escape($field);
		$old = $this->child->file_read("tables/$tname/$field.index");
		$c = new manila_index($old);
		$c->set($value, $key);
		if ($c->was_changed())
			$this->child->file_write("tables/$tname/$field.index", $c->to_data());
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$idx = $this->child->file_read("tables/$tname/$field.index");
		$c = new manila_index($idx);
		return $c->get($value);
	}
	
	public function table_insert ( $tname, $values )
	{
		$serialpath = "tables/$tname/serial.data";
		if (($data = $this->child->file_read($serialpath)))
		{
			$id = (int)$data;
			$this->child->file_write($serialpath, (string)($id + 1));
		}
		else
		{
			$id = 1;
			$this->child->file_write($serialpath, '2');
		}
		$this->table_update($tname, $id, $values);
		return $id;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$key = manila_driver::fs_escape($key);
		$data = serialize($values);
		$this->child->file_write("tables/$tname/$key.obj", $data);
	}
	
	public function table_delete ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		$this->child->file_erase("tables/$tname/$key.obj");
	}
	
	public function table_truncate ( $tname )
	{
		$files = $this->child->file_directory_list("tables/$tname");
		foreach ($files as $file)
		{
			$this->child->file_erase($file);
		}
	}
	
	public function table_fetch ( $tname, $key )
	{
		$key = manila_driver::fs_escape($key);
		$content = $this->child->file_read("tables/$tname/$key");
		if (!$content)
			return NULL;
		return unserialize($content);
	}
	
	public function table_optimise ( $tname )
	{
	}
	
	public function meta_read ( $key )
	{
		$key = manila_driver::fs_escape($key);
		return $this->child->file_read("meta/$key.txt");
	}
	
	public function meta_write ( $key, $value )
	{
		$key = manila_driver::fs_escape($key);
		if ($value === NULL)
		{
			$this->child->file_erase("meta/$key.txt");
		}
		else
		{
			$this->child->file_write("meta/$key.txt", $value);
		}
	}
	
	public function meta_list ( $pattern )
	{
		$list = $this->child->file_directory_list("meta");
		foreach ($list as $key => $value)
		{
			if (fnmatch('*.txt', $value))
			{
				$list[$key] = str_replace('.txt', '', $value);
			}
			else
			{
				unset($list[$key]);
			}
		}
		return $list;
	}
}

?>
