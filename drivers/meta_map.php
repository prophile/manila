<?php

require_once(MANILA_DRIVER_PATH . '/index_translator.php');

class manila_driver_meta_map extends manila_driver
{
	private $child;
	private $tableconf;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child']);
		$this->tableconf = $table_config;
	}
	
	public function table_list_keys ( $tname )
	{
		return unserialize($this->child->meta_read("$tname:keys"));
	}
	
	public function table_key_exists ( $tname, $key )
	{
		return $this->child->meta_read("$tname:rows:$key") !== NULL;
	}
	
	public function table_insert ( $tname, $values )
	{
		if (!($serial = (int)($this->child->meta_read("$tname:serial"))))
		{
			$serial = 0;
		}
		$serial++;
		$this->child->meta_write("$tname:serial", $serial);
		$this->table_update($tname, $serial, $values);
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$this->child->meta_write("$tname:rows:$key", serialize($values));
		$rows = (array)unserialize($this->child->meta_read("$tname:keys"));
		if (!in_array($key, $rows))
		{
			$rows[] = $key;
			$this->child->meta_write("$tname:keys", serialize($rows));
		}
	}
	
	public function table_delete ( $tname, $key )
	{
		$this->child->meta_write("$tname:rows:$key", NULL);
		$rows = (array)unserialize($this->child->meta_read("$tname:keys"));
		if (in_array($key, $rows))
		{
			$rows[] = $key;
			foreach ($rows as $k => $value)
			{
				if ($value == $key)
				{
					unset($rows[$k]);
					break;
				}
			}
			$this->child->meta_write("$tname:keys", serialize($rows));
		}
	}
	
	public function table_truncate ( $tname )
	{
		$rows = (array)unserialize($this->child->meta_read("$tname:keys"));
		foreach ($rows as $k)
		{
			$this->child->meta_write("$tname:rows:$k", NULL);
		}
		$this->child->meta_write("$tname:keys", NULL);
		$this->child->meta_write("$tname:serial", NULL);
		$tconf = $this->tableconf[$tname];
		if (isset($tconf['index']))
		{
			$indices = explode(' ', $tconf['index']);
			foreach ($indices as $index)
			{
				$this->child->meta_write("$tname:index:$index", NULL);
			}
		}
	}
	
	public function table_fetch ( $tname, $key )
	{
		$rowdata = $this->child->meta_read("$tname:$key");
		if ($rowdata)
		{
			$row = unserialize($rowdata);
			return $row;
		}
		return NULL;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$raw = $this->child->meta_read("$tname:index:$field");
		$idx = new manila_index($raw);
		$idx->set($value, $key);
		if ($idx->was_changed())
			$this->child->meta_write("$tname:index:$field", $value);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$raw = $this->child->meta_read("$tname:index:$field");
		$idx = new manila_index($raw);
		return $idx->get($field);
	}
	
	public function table_optimise ( $tname )
	{
		// do nothing!
	}
	
	public function meta_write ( $key, $value )
	{
		$this->child->meta_write("__meta:$key", $value);
	}
	
	public function meta_read ( $key )
	{
		return $this->child->meta_read("__meta:$key");
	}
}

?>
